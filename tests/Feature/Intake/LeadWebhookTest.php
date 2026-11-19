<?php

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Intake\Enums\LeadEventStatus;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Models\LeadWebhookEvent;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();

    $this->fb = LeadIntegration::factory()->create(['organization_id' => $this->org->id, 'secret' => 'app-secret', 'verify_token' => 'hello', 'access_token' => 'page-token']);
    $this->tt = LeadIntegration::factory()->tiktok()->create(['organization_id' => $this->org->id, 'secret' => 'tt-secret']);

    $this->fbBody = fn (string $leadgenId = '555') => json_encode(['object' => 'page', 'entry' => [['id' => '1', 'time' => 1, 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => $leadgenId, 'page_id' => '1', 'form_id' => '77', 'ad_id' => '88']]]]]]);
    $this->fbPost = fn (string $body, ?string $secret = 'app-secret', ?LeadIntegration $integration = null) => $this->call(
        'POST', '/api/webhooks/leads/'.($integration ?? $this->fb)->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret)], $body,
    );
    $this->graph = fn (?array $fields = null) => Http::fake(['graph.facebook.com/*' => Http::response([
        'id' => '555', 'created_time' => '2026-01-01T00:00:00+0000', 'ad_id' => '88', 'form_id' => '77',
        'field_data' => $fields ?? [
            ['name' => 'full_name', 'values' => ['Nguyễn Văn An']], ['name' => 'phone_number', 'values' => ['+84912345678']],
            ['name' => 'email', 'values' => ['An@Example.com']], ['name' => 'nganh_quan_tam', 'values' => ['Marketing']], ['name' => 'cau_hoi_khac', 'values' => ['Muốn học bổng']],
        ],
    ])]);
    $this->ttBody = fn (array $lead = []) => json_encode($lead + ['lead_id' => 'tt-1', 'fields' => [['name' => 'Họ và tên', 'value' => 'Trần Thị Bình'], ['name' => 'Số điện thoại', 'value' => '0988111222']]]);
    $this->ttPost = fn (string $body, ?int $time = null, string $secret = 'tt-secret') => $this->call(
        'POST', '/api/webhooks/leads/'.$this->tt->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TIKTOK_SIGNATURE' => 't='.($time ??= time()).',s='.hash_hmac('sha256', $time.'.'.$body, $secret)], $body,
    );
});

it('answers the Facebook subscription handshake only with the right verify token', function () {
    $url = '/api/webhooks/leads/'.$this->fb->uuid;

    $this->get("{$url}?hub.mode=subscribe&hub.verify_token=hello&hub.challenge=1158201444")->assertOk()->assertSee('1158201444', false);
    $this->get("{$url}?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1")->assertForbidden();
    $this->get('/api/webhooks/leads/'.$this->tt->uuid.'?hub.mode=subscribe&hub.verify_token=x&hub.challenge=1')->assertForbidden();
});

it('turns a Facebook lead into an applicant and case with source, campaign and touch', function () {
    ($this->graph)();
    $campaign = Campaign::factory()->create(['organization_id' => $this->org->id]);
    $this->fb->update(['campaign_id' => $campaign->id]);

    ($this->fbPost)(($this->fbBody)())->assertOk()->assertJson(['ok' => true, 'accepted' => 1]);

    $applicant = Applicant::query()->withoutGlobalScopes()->firstOrFail();
    $case = AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $applicant->id)->firstOrFail();
    $event = LeadWebhookEvent::query()->firstOrFail();
    $touch = CaseTouch::query()->where('case_id', $case->id)->firstOrFail();

    expect($applicant->full_name)->toBe('Nguyễn Văn An')->and($applicant->phone_normalized)->toBe('84912345678')->and($applicant->email)->toBe('an@example.com')
        ->and($applicant->first_source_id)->toBe(LeadSource::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('code', 'facebook_ads')->value('id'))
        ->and($case->campaign_id)->toBe($campaign->id)->and($case->utm_source)->toBe('facebook')
        ->and($touch->payload['external_id'])->toBe('555')->and($touch->payload['fields'])->toBe(['cau_hoi_khac' => 'Muốn học bổng'])->and($touch->payload['ad_id'])->toBe('88')
        ->and($event->status)->toBe(LeadEventStatus::Created)->and($event->applicant_id)->toBe($applicant->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/555') && $request['access_token'] === 'page-token' && $request['appsecret_proof'] === hash_hmac('sha256', 'page-token', 'app-secret'));
});

it('rejects a delivery with a wrong or missing signature and creates nothing', function () {
    ($this->graph)();
    $body = ($this->fbBody)();

    ($this->fbPost)($body, 'wrong-secret')->assertForbidden();
    $this->call('POST', '/api/webhooks/leads/'.$this->fb->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertForbidden();

    expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(LeadWebhookEvent::query()->pluck('status')->unique()->map->value->all())->toBe(['rejected']);
    Http::assertNothingSent();
});

it('never accepts a delivery when the integration has no secret', function () {
    $this->fb->update(['secret' => null]);

    ($this->fbPost)(($this->fbBody)(), '')->assertForbidden();
});

it('handles the same Facebook lead delivered twice once', function () {
    ($this->graph)();
    $body = ($this->fbBody)();

    ($this->fbPost)($body)->assertJson(['accepted' => 1]);
    ($this->fbPost)($body)->assertJson(['accepted' => 0]);

    expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1)->and(LeadWebhookEvent::query()->count())->toBe(1)
        ->and(CaseTouch::query()->count())->toBe(1);
});

it('adds a touch when the same person comes in through another lead', function () {
    ($this->graph)();

    ($this->fbPost)(($this->fbBody)('555'));
    ($this->fbPost)(($this->fbBody)('556'));

    $case = AdmissionCase::query()->withoutGlobalScopes()->firstOrFail();
    expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1)->and(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(CaseTouch::query()->where('case_id', $case->id)->count())->toBe(2)
        ->and(LeadWebhookEvent::query()->orderBy('id')->pluck('status')->map->value->all())->toBe(['created', 'merged']);
});

it('logs a lead without phone or email as failed instead of retrying', function () {
    ($this->graph)([['name' => 'full_name', 'values' => ['Chỉ có tên']]]);

    ($this->fbPost)(($this->fbBody)())->assertOk();

    $event = LeadWebhookEvent::query()->firstOrFail();
    expect($event->status)->toBe(LeadEventStatus::Failed)->and($event->error)->toContain('số điện thoại')->and(Applicant::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('uses the mapping of the integration for custom field names and joins first and last name', function () {
    ($this->graph)([['name' => 'first_name', 'values' => ['An']], ['name' => 'last_name', 'values' => ['Nguyễn Văn']], ['name' => 'sdt_zalo', 'values' => ['0912345678']], ['name' => 'lop', 'values' => ['12A1']]]);
    $this->fb->update(['field_map' => ['sdt_zalo' => 'phone', 'lop' => 'note']]);

    ($this->fbPost)(($this->fbBody)());

    $applicant = Applicant::query()->withoutGlobalScopes()->firstOrFail();
    expect($applicant->full_name)->toBe('Nguyễn Văn An')->and($applicant->phone_normalized)->toBe('84912345678');
});

it('turns a signed TikTok lead into an applicant with the tiktok source', function () {
    Http::fake();

    ($this->ttPost)(($this->ttBody)())->assertOk()->assertJson(['accepted' => 1]);

    $applicant = Applicant::query()->withoutGlobalScopes()->firstOrFail();
    expect($applicant->full_name)->toBe('Trần Thị Bình')->and($applicant->phone_normalized)->toBe('84988111222')
        ->and($applicant->first_source_id)->toBe(LeadSource::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('code', 'tiktok_ads')->value('id'))
        ->and(LeadWebhookEvent::query()->firstOrFail()->external_id)->toBe('tt-1');
    Http::assertNothingSent();
});

it('rejects a TikTok delivery with a bad or stale signature', function () {
    $body = ($this->ttBody)();

    ($this->ttPost)($body, null, 'other-secret')->assertForbidden();
    ($this->ttPost)($body, time() - 3600)->assertForbidden();

    expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('accepts several TikTok leads in one delivery', function () {
    $body = json_encode(['leads' => [
        ['lead_id' => 'a', 'fields' => ['name' => 'Lê An', 'phone' => '0911000001']],
        ['lead_id' => 'b', 'fields' => ['name' => 'Lê Bình', 'phone' => '0911000002']],
    ]]);

    ($this->ttPost)($body)->assertJson(['accepted' => 2]);

    expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('answers 404 for an unknown or inactive integration', function () {
    $this->postJson('/api/webhooks/leads/'.fake()->uuid(), [])->assertNotFound();

    $this->fb->update(['is_active' => false]);
    ($this->fbPost)(($this->fbBody)())->assertNotFound();
});

it('keeps each integration inside its own organization', function () {
    ($this->graph)();
    $other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($other);
    AdmissionCycle::factory()->for($other)->current()->create();

    ($this->fbPost)(($this->fbBody)());

    expect(Applicant::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->count())->toBe(1)
        ->and(Applicant::query()->withoutGlobalScopes()->where('organization_id', $other->id)->count())->toBe(0);
});
