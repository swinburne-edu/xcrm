<?php

use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.4, signed-in applicant: scores, preferences, submit, withdraw, confirm enrolment, isolation between applicants.
 * Module under test: PortalController. No earlier test file.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    config(['portal.organization_id' => $this->org->id]);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['status' => 'active']);
    $this->method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30]]);
    $this->major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
    $this->a00 = SubjectCombination::query()->where('code', 'A00')->firstOrFail();
    DB::table('major_cycle_methods')->insert(['major_id' => $this->major->id, 'cycle_method_id' => $this->method->id]);
    DB::table('major_combinations')->insert(['major_id' => $this->major->id, 'combination_id' => $this->a00->id, 'cycle_id' => $this->cycle->id]);
    $this->round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id, 'name' => 'Đợt 1']);

    $this->applicant = Applicant::factory()->for($this->org)->create(['phone' => '0912345678', 'graduation_year' => 2027]);
    $this->as = fn (?Applicant $a = null) => $this->withSession(['portal_applicant_id' => ($a ?? $this->applicant)->id]);
    $this->payload = ['major_id' => $this->major->id, 'cycle_method_id' => $this->method->id, 'combination_id' => $this->a00->id, 'round_id' => $this->round->id];
    $this->subjectId = fn (string $code) => DB::table('subjects')->where('code', $code)->value('id');
    $this->scores = fn () => $this->put('/cong-thi-sinh/diem', ['scores' => array_map(fn ($c) => ['subject_id' => ($this->subjectId)($c), 'thpt' => 8], ['TOAN', 'LY', 'HOA'])]);
});

it('saves own scores as unverified and shows them back', function () {
    ($this->as)()->put('/cong-thi-sinh/diem', ['scores' => [['subject_id' => ($this->subjectId)('TOAN'), 'transcript' => 8.5, 'thpt' => 9]]])->assertSessionHasNoErrors();

    $rows = ApplicantScore::query()->where('applicant_id', $this->applicant->id)->get();
    expect($rows)->toHaveCount(2)->and($rows->every(fn ($r) => $r->verified === false))->toBeTrue();
    expect($rows->firstWhere('score_type', 'transcript')->term)->toBe('12');

    ($this->as)()->get('/cong-thi-sinh')->assertInertia(fn (Assert $p) => $p->where('subjects.0.transcript', 8.5)->where('subjects.0.thpt', 9));
    ($this->as)()->put('/cong-thi-sinh/diem', ['scores' => [['subject_id' => ($this->subjectId)('TOAN'), 'thpt' => 11]]])->assertSessionHasErrors('scores.0.thpt');
});

it('adds a preference, submits it and confirms enrolment once admitted', function () {
    ($this->as)();
    ($this->scores)()->assertSessionHasNoErrors();

    $this->post('/cong-thi-sinh/nguyen-vong', $this->payload)->assertSessionHasNoErrors();
    $application = Application::query()->firstOrFail();
    expect($application->status->value)->toBe('draft')->and((float) $application->final_score)->toBe(24.0);

    $this->get('/cong-thi-sinh')->assertInertia(fn (Assert $p) => $p->has('applications', 1)->where('applications.0.actions.0.action', 'submit')->where('applications.0.can_delete', true));

    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/confirm")->assertSessionHasErrors();
    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/admit")->assertNotFound();
    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/submit")->assertSessionHasNoErrors();
    expect($application->refresh()->status->value)->toBe('submitted');
    expect(AdmissionCase::query()->firstOrFail()->stage->code)->toBe('early_applied');

    app(TransitionApplication::class)->handle($application, 'admit');
    $this->get('/cong-thi-sinh')->assertInertia(fn (Assert $p) => $p->where('applications.0.status', 'admitted'));
    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/confirm")->assertSessionHasNoErrors();
    expect($application->refresh()->status->value)->toBe('confirmed');
});

it('refuses a submit without scores and lets the applicant withdraw', function () {
    ($this->as)();
    $this->post('/cong-thi-sinh/nguyen-vong', $this->payload)->assertSessionHasNoErrors();
    $application = Application::query()->firstOrFail();

    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/submit")->assertSessionHasErrors();
    ($this->scores)();
    $this->post('/cong-thi-sinh/nguyen-vong', ['major_id' => 999] + $this->payload)->assertSessionHasErrors('major_id');
    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/submit")->assertSessionHasNoErrors();
    $this->post("/cong-thi-sinh/nguyen-vong/{$application->id}/withdraw")->assertSessionHasNoErrors();
    expect($application->refresh()->status->value)->toBe('withdrawn');
});

it('never touches the preferences of another applicant', function () {
    ($this->as)();
    ($this->scores)();
    $this->post('/cong-thi-sinh/nguyen-vong', $this->payload);
    $mine = Application::query()->firstOrFail();

    $other = Applicant::factory()->for($this->org)->create(['phone' => '0987654321']);
    ($this->as)($other)->post("/cong-thi-sinh/nguyen-vong/{$mine->id}/withdraw")->assertNotFound();
    ($this->as)($other)->delete("/cong-thi-sinh/nguyen-vong/{$mine->id}")->assertNotFound();
    ($this->as)($other)->get('/cong-thi-sinh')->assertInertia(fn (Assert $p) => $p->has('applications', 0));
    expect($mine->refresh()->status->value)->toBe('draft');
});

it('is read only once the cycle is closed', function () {
    ($this->as)();
    ($this->scores)();
    $this->post('/cong-thi-sinh/nguyen-vong', $this->payload);
    $this->cycle->update(['status' => 'closed']);
    app()->forgetInstance(CurrentCycle::class);

    $this->get('/cong-thi-sinh')->assertInertia(fn (Assert $p) => $p->where('cycle.closed', true)->where('applications.0.actions', [])->where('applications.0.can_delete', false)->where('options.majors', []));
    $this->post('/cong-thi-sinh/nguyen-vong', $this->payload)->assertSessionHasErrors();
});
