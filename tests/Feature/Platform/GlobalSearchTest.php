<?php

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
});

it('finds applicants by name, code and phone typed in local format', function () {
    $school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Lê Quý Đôn']);
    $a = Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Văn Tèo', 'phone' => '0912345678', 'phone_normalized' => '84912345678', 'high_school_id' => $school->id]);
    Applicant::factory()->for($this->org)->create(['full_name' => 'Trần Thị Khác', 'phone_normalized' => '84900000001']);

    $this->actingAs($this->admin);

    $byName = $this->getJson('/search?q=Tèo')->assertOk()->json('applicants');
    expect($byName)->toHaveCount(1)->and($byName[0])->toMatchArray(['id' => $a->uuid, 'label' => 'Nguyễn Văn Tèo', 'url' => "/applicants/{$a->uuid}"])
        ->and($byName[0]['description'])->toContain($a->code)->toContain('THPT Lê Quý Đôn');

    expect($this->getJson('/search?q='.$a->code)->json('applicants'))->toHaveCount(1)
        ->and($this->getJson('/search?q=0912345678')->json('applicants'))->toHaveCount(1)
        ->and($this->getJson('/search?q=0912%20345%20678')->json('applicants'))->toHaveCount(1);
});

it('never returns the phone number', function () {
    Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Văn Tèo', 'phone' => '0912345678', 'phone_normalized' => '84912345678']);

    expect($this->actingAs($this->admin)->getJson('/search?q=Tèo')->getContent())->not->toContain('912345678');
});

it('finds high schools by name or code', function () {
    $school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chuyên Hà Nội', 'code' => 'HN001']);

    $this->actingAs($this->admin);

    expect($this->getJson('/search?q=Chuyên')->json('high_schools.0'))->toMatchArray(['id' => $school->id, 'label' => 'THPT Chuyên Hà Nội', 'url' => "/high-schools/{$school->id}"])
        ->and($this->getJson('/search?q=HN001')->json('high_schools'))->toHaveCount(1);
});

it('returns nothing for a term shorter than two characters, and matches % literally', function () {
    Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Văn Tèo']);

    $this->actingAs($this->admin);

    expect($this->getJson('/search?q=N')->json())->toBe(['applicants' => [], 'high_schools' => []])
        ->and($this->getJson('/search?q=%25%25')->json('applicants'))->toBe([]);
});

it('leaves out merged applicants and other organizations', function () {
    $keep = Applicant::factory()->for($this->org)->create(['full_name' => 'Lê Gốc']);
    Applicant::factory()->for($this->org)->create(['full_name' => 'Lê Đã Gộp', 'merged_into_id' => $keep->id]);
    Applicant::factory()->for(Organization::factory()->create())->create(['full_name' => 'Lê Tổ Chức Khác']);

    $labels = collect($this->actingAs($this->admin)->getJson('/search?q=Lê')->json('applicants'))->pluck('label')->all();

    expect($labels)->toBe(['Lê Gốc']);
});

it('applies the data scope: a counselor only finds their own applicants', function () {
    $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $counselor->assignRole('counselor');
    Applicant::factory()->for($this->org)->create(['full_name' => 'Phạm Của Tôi', 'referred_by_user_id' => $counselor->id]);
    Applicant::factory()->for($this->org)->create(['full_name' => 'Phạm Của Người Khác', 'referred_by_user_id' => $this->admin->id]);

    $labels = collect($this->actingAs($counselor)->getJson('/search?q=Phạm')->json('applicants'))->pluck('label')->all();

    expect($labels)->toBe(['Phạm Của Tôi']);
});

it('omits groups the user has no view permission for, and rejects guests', function () {
    HighSchool::factory()->for($this->org)->create(['name' => 'THPT Nguyễn Huệ']);
    Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Huệ']);
    $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $user->givePermissionTo('high_school.view');

    $json = $this->actingAs($user)->getJson('/search?q=Huệ')->json();

    expect($json['applicants'])->toBe([])->and($json['high_schools'])->toHaveCount(1);

    auth()->logout();
    $this->getJson('/search?q=Huệ')->assertUnauthorized();
});
