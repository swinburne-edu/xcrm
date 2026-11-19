<?php

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
});

it('shows the user guide to every signed-in role with the user\'s own roles', function (string $role) {
    $user = User::factory()->for($this->org)->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/help')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help/Index')->where('myRoles', [$role]));
})->with(RoleDefinitions::roleCodes());

it('has a guide for every default role', function () {
    $source = file_get_contents(resource_path('js/pages/Help/guides.ts'));

    foreach (RoleDefinitions::roleCodes() as $role) {
        expect($source)->toContain("code: '{$role}'");
    }
});

it('lists the guide in the sidebar for a user without extra permissions', function () {
    $user = User::factory()->for($this->org)->create();
    $user->assignRole('ambassador');

    $this->actingAs($user)->get('/help')->assertInertia(fn (Assert $page) => $page
        ->where('navigation', fn ($nav) => collect($nav)->contains(fn ($item) => $item['url'] === '/help')));
});

it('requires login', function () {
    $this->get('/help')->assertRedirect(route('login'));
});
