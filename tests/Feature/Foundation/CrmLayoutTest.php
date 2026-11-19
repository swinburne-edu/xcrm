<?php

use App\Domain\Platform\Services\NavigationMenu;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/** Menu URLs from docs/06-giao-dien.md. */
dataset('menu urls', [
    '/dashboard',
    '/cases', '/applicants', '/applications', '/appointments',
    '/tasks',
    '/high-schools', '/school-contacts', '/school-activities',
    '/campaigns', '/forms',
    '/majors', '/quotas',
    '/reports',
    '/settings/cycles', '/settings/pipelines', '/settings/catalogs', '/settings/territories',
    '/settings/users', '/settings/custom-fields', '/settings/assignment-rules',
]);

it('opens every menu screen for a logged-in user', function (string $url) {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin'); // built screens check permissions

    $this->actingAs($user)
        ->get($url)
        ->assertOk();
})->with('menu urls');

it('redirects guests from menu screens to login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('menu urls');

it('shows the under-construction page with title, phase and breadcrumbs', function () {
    $this->actingAs(User::factory()->create())
        ->get('/school-contacts')
        ->assertInertia(fn (Assert $page) => $page
            ->component('UnderConstruction')
            ->where('title', 'Người liên hệ')
            ->where('phase', null)
            ->where('breadcrumbs', [['title' => 'Trường THPT'], ['title' => 'Người liên hệ', 'href' => '/school-contacts']]));
});

it('registers placeholder routes under the names the real screens will use', function () {
    foreach (app(NavigationMenu::class)->placeholders() as $screen) {
        expect(route($screen['route'], absolute: false))->toBe($screen['url']);
    }

    expect(route('cases.index', absolute: false))->toBe('/cases')
        ->and(route('settings.cycles.index', absolute: false))->toBe('/settings/cycles');
});

it('shares the sidebar menu in the order of the docs', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // the full menu; other roles see less (UsersManagementTest)

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('navigation', 9)
            ->where('navigation.0.title', 'Tổng quan')
            ->where('navigation.0.url', '/dashboard')
            ->where('navigation.0.placeholder', false)
            ->where('navigation.1.title', 'Tuyển sinh')
            ->has('navigation.1.children', 5)
            ->where('navigation.1.children.0', [
                'title' => 'Hồ sơ tuyển sinh',
                'url' => '/cases',
                'phase' => null,
                'placeholder' => false,
            ])
            ->where('navigation.2.title', 'Công việc của tôi')
            ->where('navigation.3.title', 'Trường THPT')
            ->where('navigation.4.title', 'Marketing')
            ->where('navigation.5.title', 'Đào tạo & chỉ tiêu')
            ->where('navigation.6.title', 'Báo cáo')
            ->where('navigation.7.title', 'Cài đặt')
            ->has('navigation.7.children', 11)
            ->where('navigation.8.title', 'Hướng dẫn sử dụng')
            ->where('navigation.8.url', '/help'));
});

it('does not share the menu with guests', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation', []));
});
