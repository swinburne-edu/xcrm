<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\SaveApplication;
use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Models\Application;
use App\Domain\Api\Models\Webhook;
use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Task 3.5, outgoing webhooks: events on admit and confirm, signature, retries, address safety.
 * Module under test: WebhookDispatcher, DeliverWebhook and ApplicationWebhookObserver. No earlier test file.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    config(['workflows.resolve_dns' => false]);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['status' => 'active']);
    $method = CycleMethod::factory()->create(['cycle_id' => $cycle->id, 'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30]]);
    $major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin', 'moet_code' => '7480201']);
    $a00 = SubjectCombination::query()->where('code', 'A00')->firstOrFail();
    DB::table('major_cycle_methods')->insert(['major_id' => $major->id, 'cycle_method_id' => $method->id]);
    DB::table('major_combinations')->insert(['major_id' => $major->id, 'combination_id' => $a00->id, 'cycle_id' => $cycle->id]);
    $round = AdmissionRound::factory()->create(['cycle_id' => $cycle->id]);

    $this->hook = fn (array $attrs = []) => Webhook::query()->create(['organization_id' => $this->org->id, 'name' => 'Đào tạo', 'url' => 'https://lms.example.com/hook', 'events' => ['case.enrolled', 'application.admitted'], 'secret' => 'top-secret', ...$attrs]);
    $this->application = function () use ($cycle, $major, $method, $a00, $round): Application {
        $applicant = Applicant::factory()->for($this->org)->create(['full_name' => 'Lê Minh', 'phone' => '0912345678', 'citizen_id' => '012345678901']);

        foreach (['TOAN', 'LY', 'HOA'] as $code) {
            ApplicantScore::query()->create(['applicant_id' => $applicant->id, 'score_type' => 'thpt_exam', 'subject_id' => DB::table('subjects')->where('code', $code)->value('id'), 'score' => 8, 'year' => 2026]);
        }

        $case = app(CreateAdmissionCase::class)->handle($applicant, $cycle, ['owner_id' => null]);
        $application = app(SaveApplication::class)->handle($case, null, ['major_id' => $major->id, 'cycle_method_id' => $method->id, 'combination_id' => $a00->id, 'round_id' => $round->id]);

        return app(TransitionApplication::class)->handle($application, 'submit');
    };
    $this->step = fn (Application $a, string $action) => app(TransitionApplication::class)->handle($a, $action);
});

it('sends application.admitted and case.enrolled with a signed body and no citizen id', function () {
    Http::fake(['lms.example.com/*' => Http::response('ok', 200)]);
    ($this->hook)();
    $application = ($this->application)();

    ($this->step)($application, 'admit');
    ($this->step)($application, 'confirm');

    expect(WebhookDelivery::query()->pluck('event')->sort()->values()->all())->toBe(['application.admitted', 'case.enrolled'])->and(WebhookDelivery::query()->where('status', 'delivered')->count())->toBe(2);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->hasHeader('X-Xcrm-Event', 'case.enrolled')
            && $request->header('X-Xcrm-Signature')[0] === 'sha256='.hash_hmac('sha256', $body, 'top-secret')
            && $request['data']['applicant']['full_name'] === 'Lê Minh' && $request['data']['applicant']['phone'] === '84912345678'
            && $request['data']['application']['major_code'] === '7480201' && $request['data']['application']['status'] === 'confirmed'
            && ! str_contains($body, '012345678901');
    });
});

it('sends only the events a webhook listens to, to active webhooks of the same organization', function () {
    Http::fake(['*' => Http::response('ok')]);
    ($this->hook)(['events' => ['case.enrolled']]);
    ($this->hook)(['name' => 'Tắt', 'is_active' => false]);
    Webhook::query()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Khác', 'url' => 'https://other.example.com', 'events' => ['application.admitted'], 'secret' => 'x']);
    $application = ($this->application)();

    ($this->step)($application, 'admit');
    expect(WebhookDelivery::query()->count())->toBe(0);

    ($this->step)($application, 'confirm');
    expect(WebhookDelivery::query()->count())->toBe(1);
});

it('retries a failing receiver, then marks the delivery failed and lets staff send it again', function () {
    Http::fake(['lms.example.com/*' => Http::sequence()->push('down', 500)->push('down', 500)->push('down', 500)->push('down', 500)->push('ok', 200)]);
    $hook = ($this->hook)(['events' => ['case.enrolled']]);
    $application = ($this->application)();
    ($this->step)($application, 'admit');
    ($this->step)($application, 'confirm');

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->status)->toBe('failed')->and($delivery->attempts)->toBe(4)->and($delivery->response_code)->toBe(500)->and($delivery->error)->toContain('500');
    Http::assertSentCount(4);

    \App\Domain\Api\Jobs\DeliverWebhook::dispatchSync($delivery->id);
    expect($delivery->refresh()->status)->toBe('delivered');
    $hook->delete();
});

it('refuses to call internal addresses even if one was stored', function () {
    Http::fake();
    ($this->hook)(['url' => 'https://127.0.0.1/hook', 'events' => ['case.enrolled']]);
    $application = ($this->application)();
    ($this->step)($application, 'admit');
    ($this->step)($application, 'confirm');

    expect(WebhookDelivery::query()->firstOrFail()->error)->toContain('nội bộ');
    Http::assertNothingSent();
});
