<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Task 3.5, REST API for preferences: add, list, run steps with the right permission. No earlier test file.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['status' => 'active']);
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');

    $method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30]]);
    $major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
    $a00 = SubjectCombination::query()->where('code', 'A00')->firstOrFail();
    DB::table('major_cycle_methods')->insert(['major_id' => $major->id, 'cycle_method_id' => $method->id]);
    DB::table('major_combinations')->insert(['major_id' => $major->id, 'combination_id' => $a00->id, 'cycle_id' => $this->cycle->id]);
    $round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id]);
    $this->payload = ['major_id' => $major->id, 'cycle_method_id' => $method->id, 'combination_id' => $a00->id, 'round_id' => $round->id];

    $this->api = function (User $u) {
        app('auth')->forgetGuards();

        return $this->withToken($u->createToken('t', ['read', 'write'])->plainTextToken)->withHeader('Accept', 'application/json');
    };
    $this->caseFor = function (User $owner) {
        $applicant = Applicant::factory()->for($this->org)->create();

        foreach (['TOAN', 'LY', 'HOA'] as $code) {
            ApplicantScore::query()->create(['applicant_id' => $applicant->id, 'score_type' => 'thpt_exam', 'subject_id' => DB::table('subjects')->where('code', $code)->value('id'), 'score' => 8, 'year' => 2026]);
        }

        return app(CreateAdmissionCase::class)->handle($applicant, $this->cycle, ['owner_id' => $owner->id]);
    };
});

it('adds a scored preference to a case and lists it', function () {
    $case = ($this->caseFor)($this->counselor);

    $res = ($this->api)($this->counselor)->postJson("/api/v1/cases/{$case->uuid}/applications", $this->payload)->assertCreated();
    expect($res->json('data.status'))->toBe('draft')->and($res->json('data.final_score'))->toEqual(24)->and($res->json('data.major.name'))->toBe('Công nghệ thông tin');
    $id = $res->json('data.id');

    ($this->api)($this->counselor)->getJson('/api/v1/applications?filter[status]=draft')->assertOk()->assertJsonPath('meta.total', 1);
    ($this->api)($this->counselor)->getJson("/api/v1/applications/{$id}")->assertOk()->assertJsonPath('data.case_uuid', $case->uuid);
    ($this->api)($this->admin)->postJson("/api/v1/cases/{$case->uuid}/applications", ['major_id' => 999] + $this->payload)->assertUnprocessable();
});

it('runs steps with the permission each one needs', function () {
    $case = ($this->caseFor)($this->counselor);
    $id = ($this->api)($this->counselor)->postJson("/api/v1/cases/{$case->uuid}/applications", $this->payload)->json('data.id');

    ($this->api)($this->counselor)->postJson("/api/v1/applications/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    ($this->api)($this->counselor)->postJson("/api/v1/applications/{$id}/admit")->assertForbidden();
    ($this->api)($this->admin)->postJson("/api/v1/applications/{$id}/admit")->assertOk()->assertJsonPath('data.status', 'admitted');
    ($this->api)($this->admin)->postJson("/api/v1/applications/{$id}/nonsense")->assertNotFound();
    ($this->api)($this->counselor)->postJson("/api/v1/applications/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    expect(Application::query()->findOrFail($id)->status->value)->toBe('confirmed');
});

it('does not expose preferences outside the data scope', function () {
    $theirs = ($this->caseFor)($this->admin);
    $id = ($this->api)($this->admin)->postJson("/api/v1/cases/{$theirs->uuid}/applications", $this->payload)->json('data.id');

    ($this->api)($this->counselor)->getJson("/api/v1/applications/{$id}")->assertNotFound();
    ($this->api)($this->counselor)->getJson('/api/v1/applications')->assertJsonPath('meta.total', 0);
    ($this->api)($this->counselor)->postJson("/api/v1/cases/{$theirs->uuid}/applications", $this->payload)->assertNotFound();
});
