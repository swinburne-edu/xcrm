<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['name' => 'Tuyển sinh năm 2027']);
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
});

it('shows the applicant\'s cases with stage and owner on the applicant page', function () {
    $applicant = Applicant::factory()->for($this->org)->create();
    app(CreateAdmissionCase::class)->handle($applicant, $this->cycle, ['owner_id' => $this->admin->id], $this->admin);

    $this->actingAs($this->admin)->get("/applicants/{$applicant->uuid}")->assertInertia(fn (Assert $page) => $page->component('Applicants/Show')
        ->has('cases', 1)->where('cases.0.cycle', 'Tuyển sinh năm 2027')->where('cases.0.stage', 'Mới')->where('cases.0.owner', $this->admin->name)->where('cases.0.status', 'open'));
});

it('shows no cases for an applicant without one', function () {
    $applicant = Applicant::factory()->for($this->org)->create();

    $this->actingAs($this->admin)->get("/applicants/{$applicant->uuid}")->assertInertia(fn (Assert $page) => $page->has('cases', 0));
});

it('lists the applicants of a high school on the school page', function () {
    $school = HighSchool::factory()->for($this->org)->create();
    Applicant::factory()->for($this->org)->count(3)->create(['high_school_id' => $school->id]);
    Applicant::factory()->for($this->org)->create();

    $this->actingAs($this->admin)->get("/high-schools/{$school->id}")->assertInertia(fn (Assert $page) => $page->component('HighSchools/Show')
        ->where('applicants.total', 3)->has('applicants.items', 3)->where('applicants.items.0.uuid', fn ($u) => strlen($u) === 36));
});
