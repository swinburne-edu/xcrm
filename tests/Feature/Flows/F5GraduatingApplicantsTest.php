<?php

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Actions\CreateCasesForGraduatingApplicants;
use App\Domain\Cycle\Actions\SetCurrentCycle;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Flow F5 (docs/05): when a cycle is active, applicants graduating in its year and without a case get one.
 * Module under test: CreateCasesForGraduatingApplicants, its job and the activation hooks. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->create(['year' => 2027]);

    $this->graduating = fn (array $attrs = []) => Applicant::factory()->for($this->org)->create(['graduation_year' => 2027, ...$attrs]);
});

it('opens a case in the shared pool for each graduating applicant without one', function () {
    $a = ($this->graduating)();
    $b = ($this->graduating)();
    ($this->graduating)(['graduation_year' => 2028]);
    $this->cycle->update(['status' => 'active']);

    $result = app(CreateCasesForGraduatingApplicants::class)->handle($this->cycle->fresh());

    expect($result)->toBe(['created' => 2, 'failed' => 0]);
    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->pluck('applicant_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->whereNull('owner_id')->count())->toBe(2);
});

it('is idempotent and only picks up newcomers on a second run', function () {
    ($this->graduating)();
    $this->cycle->update(['status' => 'active']);
    $action = app(CreateCasesForGraduatingApplicants::class);

    $action->handle($this->cycle->fresh());
    ($this->graduating)();

    expect($action->handle($this->cycle->fresh())['created'])->toBe(1);
    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->count())->toBe(2);
});

it('does nothing for a cycle that is not active', function () {
    ($this->graduating)();

    expect(app(CreateCasesForGraduatingApplicants::class)->handle($this->cycle))->toBe(['created' => 0, 'failed' => 0]);
    expect(AdmissionCase::query()->count())->toBe(0);
});

it('skips merged and deleted applicants and those of another organization', function () {
    $keeper = ($this->graduating)();
    ($this->graduating)(['merged_into_id' => $keeper->id]);
    ($this->graduating)()->delete();
    Applicant::factory()->for(Organization::factory()->create())->create(['graduation_year' => 2027]);
    $this->cycle->update(['status' => 'active']);

    expect(app(CreateCasesForGraduatingApplicants::class)->handle($this->cycle->fresh())['created'])->toBe(1);
});

it('runs when a cycle is made current, and from the daily command', function () {
    ($this->graduating)();

    app(SetCurrentCycle::class)->handle($this->cycle);
    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->count())->toBe(1);

    ($this->graduating)();
    $this->artisan('crm:create-graduating-cases')->assertSuccessful();
    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->count())->toBe(2);
});

it('runs when an admin saves a cycle as active', function () {
    ($this->graduating)();
    $admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->put("/settings/cycles/{$this->cycle->id}", [
        'name' => $this->cycle->name, 'year' => 2027, 'level' => 'university', 'status' => 'active',
    ])->assertRedirect();

    expect(AdmissionCase::query()->where('cycle_id', $this->cycle->id)->count())->toBe(1);
});

it('leaves applicants for later when there is no default pipeline', function () {
    Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->delete();
    ($this->graduating)();
    $this->cycle->update(['status' => 'active']);

    expect(app(CreateCasesForGraduatingApplicants::class)->handle($this->cycle->fresh()))->toBe(['created' => 0, 'failed' => 1]);
});
