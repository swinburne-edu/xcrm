<?php

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->me = User::factory()->for($this->org)->create(['name' => 'Nguyễn Văn Admin']);
});

it('searches active users of my organization', function () {
    User::factory()->for($this->org)->create(['name' => 'Trần Thị Tư Vấn', 'email' => 'tuvan@example.com']);
    User::factory()->for($this->org)->create(['name' => 'Lê Văn Khoá', 'is_active' => false]);
    User::factory()->create(['name' => 'Trần Ngoài Tổ Chức']);

    $this->actingAs($this->me)
        ->getJson('/lookups/users?q=Trần')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => User::where('email', 'tuvan@example.com')->value('id'), 'label' => 'Trần Thị Tư Vấn', 'description' => 'tuvan@example.com'],
        ]]);
});

it('lists users without a search term, alphabetically', function () {
    User::factory()->for($this->org)->create(['name' => 'An']);

    $this->actingAs($this->me)
        ->getJson('/lookups/users')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'An')
        ->assertJsonCount(2, 'data');
});

it('returns labels for selected ids', function () {
    $team = Team::factory()->for($this->org)->create(['name' => 'Nhóm Bắc', 'leader_id' => $this->me->id]);
    $foreignTeam = Team::factory()->create(['name' => 'Nhóm tổ chức khác']);

    $this->actingAs($this->me)
        ->getJson("/lookups/teams?ids={$team->id},{$foreignTeam->id}")
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $team->id, 'label' => 'Nhóm Bắc', 'description' => 'Trưởng nhóm: Nguyễn Văn Admin'],
        ]]);
});

it('returns 404 for unknown lookups', function () {
    $this->actingAs($this->me)->getJson('/lookups/applications')->assertNotFound();
    $this->actingAs($this->me)->getJson('/lookups/secrets')->assertNotFound();
});

it('requires login for lookups', function () {
    $this->getJson('/lookups/users')->assertUnauthorized();
});

it('renders the component showcase with a filtered, sorted list', function () {
    User::factory()->for($this->org)->create(['name' => 'Bình', 'is_active' => false]);

    $this->actingAs($this->me)
        ->get('/dev/components?filter[is_active]=0&sort=-name')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dev/Components')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Bình')
            ->where('users.meta.total', 1)
            ->where('users.query.filters', ['is_active' => false])
            ->where('users.query.sort', '-name')
            ->has('dataScopeOptions', 4));
});
