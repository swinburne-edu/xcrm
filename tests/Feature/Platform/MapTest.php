<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.6: the map page, province heat numbers and school markers.
 * Module under test: MapController and MapData. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->hanoi = Province::factory()->create(['code' => '01', 'name' => 'Thành phố Hà Nội']);
    $this->hue = Province::factory()->create(['code' => '46', 'name' => 'Thành phố Huế']);
});

it('counts applicants, cases and enrolled per province and per school', function () {
    $school = HighSchool::factory()->for($this->org)->create(['province_id' => $this->hanoi->id, 'lat' => 21.03, 'lng' => 105.85]);
    HighSchool::factory()->for($this->org)->create(['province_id' => $this->hanoi->id]);

    $make = fn (array $attrs) => app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create($attrs), $this->cycle, ['owner_id' => null]);
    $a = $make(['province_id' => $this->hanoi->id, 'high_school_id' => $school->id]);
    $make(['province_id' => $this->hanoi->id, 'high_school_id' => $school->id]);
    $make(['province_id' => $this->hue->id]);
    Applicant::factory()->for($this->org)->create(['province_id' => $this->hue->id]);
    AdmissionCase::query()->whereKey($a->id)->update(['status' => 'won']);

    $this->actingAs($this->admin)->get('/map')->assertInertia(function (Assert $p) {
        $p->component('Map/Index')
            ->where('provinces', function ($rows) {
                $hanoi = collect($rows)->firstWhere('code', '01');
                $hue = collect($rows)->firstWhere('code', '46');

                return $hanoi['applicants'] === 2 && $hanoi['leads'] === 2 && $hanoi['enrolled'] === 1 && $hanoi['lat'] === 21.0285
                    && $hue['applicants'] === 2 && $hue['leads'] === 1 && $hue['enrolled'] === 0;
            })
            ->has('schools', 1)->where('schools.0.applicants', 2)->where('schools.0.enrolled', 1)->where('schools.0.lat', 21.03)
            ->where('without_coordinates', 1);
    });
});

it('ignores applicants of another organization', function () {
    Applicant::factory()->for(Organization::factory()->create())->create(['province_id' => $this->hanoi->id]);

    $this->actingAs($this->admin)->get('/map')->assertInertia(
        fn (Assert $p) => $p->where('provinces', fn ($rows) => collect($rows)->firstWhere('code', '01')['applicants'] === 0),
    );
});

it('is closed to people without report access', function () {
    $faculty = User::factory()->for($this->org)->create();
    $faculty->givePermissionTo('major.view');

    $this->actingAs($faculty)->get('/map')->assertForbidden();
});

it('limits numbers to the data scope of the user', function () {
    $mine = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $mine->assignRole('counselor');
    $mine->givePermissionTo('report.view');

    app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(['province_id' => $this->hanoi->id]), $this->cycle, ['owner_id' => $mine->id]);
    app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(['province_id' => $this->hanoi->id]), $this->cycle, ['owner_id' => null]);

    $this->actingAs($mine)->get('/map')->assertInertia(
        fn (Assert $p) => $p->where('provinces', fn ($rows) => collect($rows)->firstWhere('code', '01')['leads'] === 1),
    );
});
