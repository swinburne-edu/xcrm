<?php

use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The menu page /school-contacts (all contacts of the schools a user can see). Module under test:
 * SchoolContactListController. No earlier test file for the list.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chu Văn An']);
});

it('lists contacts with their school, filters them and masks the phone without the sensitive permission', function () {
    SchoolContact::factory()->for($this->org)->create(['high_school_id' => $this->school->id, 'full_name' => 'Cô Lan', 'role' => 'career_counselor', 'phone' => '0912345678', 'is_primary' => true]);
    SchoolContact::factory()->for($this->org)->create(['high_school_id' => $this->school->id, 'full_name' => 'Thầy Hùng', 'role' => 'principal']);

    $this->actingAs($this->admin)->get('/school-contacts')->assertInertia(fn (Assert $p) => $p->component('SchoolContacts/Index')->has('contacts.data', 2)->where('contacts.data.0.high_school_name', 'THPT Chu Văn An'));
    $this->actingAs($this->admin)->get('/school-contacts?filter[role]=principal')->assertInertia(fn (Assert $p) => $p->has('contacts.data', 1)->where('contacts.data.0.full_name', 'Thầy Hùng'));
    $this->actingAs($this->admin)->get('/school-contacts?filter[q]=Lan')->assertInertia(fn (Assert $p) => $p->has('contacts.data', 1)->where('contacts.data.0.is_primary', true));

    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo(['school_contact.view', 'high_school.view']);
    $phone = $this->actingAs($viewer)->get('/school-contacts?filter[q]=Lan')->viewData('page')['props']['contacts']['data'][0]['phone'];
    expect($phone)->not->toBe('0912345678');
});

it('is closed to people without the permission and hides contacts of other organizations', function () {
    SchoolContact::factory()->for(Organization::factory()->create())->create(['full_name' => 'Người ngoài']);
    $faculty = User::factory()->for($this->org)->create();
    $faculty->assignRole('faculty_viewer');

    $this->actingAs($faculty)->get('/school-contacts')->assertForbidden();
    $this->actingAs($this->admin)->get('/school-contacts')->assertInertia(fn (Assert $p) => $p->has('contacts.data', 0));
});

it('is a real screen in the menu now', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('navigation.3.children.1.placeholder', false)->where('navigation.3.children.1.url', '/school-contacts'));
});
