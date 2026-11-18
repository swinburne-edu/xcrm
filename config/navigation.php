<?php

/*
|--------------------------------------------------------------------------
| CRM sidebar menu (docs/06-giao-dien.md)
|--------------------------------------------------------------------------
|
| Single source for the sidebar: shared to Inertia by
| App\Domain\Platform\Services\NavigationMenu and used by routes/crm.php.
|
| Entry keys:
|   title        Vietnamese label
|   url          path (also the route URI)
|   route        route name the real feature must keep using
|   icon         top-level icon key (mapped in resources/js/lib/navigation.ts)
|   phase        'P1' / 'P2' for later-phase features (shown on the placeholder page)
|   placeholder  true = not built yet: routes/crm.php serves the "Đang phát triển" page.
|                When a task builds the screen, register the real route and remove this flag.
|   children     sub-items of a group
|
*/

return [

    'items' => [
        [
            'title' => 'Tổng quan',
            'url' => '/dashboard',
            'route' => 'dashboard',
            'icon' => 'dashboard',
        ],
        [
            'title' => 'Tuyển sinh',
            'icon' => 'admission',
            'children' => [
                ['title' => 'Hồ sơ tuyển sinh', 'url' => '/cases', 'route' => 'cases.index', 'placeholder' => true],
                ['title' => 'Thí sinh', 'url' => '/applicants', 'route' => 'applicants.index', 'placeholder' => true],
                ['title' => 'Nguyện vọng', 'url' => '/applications', 'route' => 'applications.index', 'phase' => 'P1', 'placeholder' => true],
                ['title' => 'Lịch hẹn', 'url' => '/appointments', 'route' => 'appointments.index', 'placeholder' => true],
            ],
        ],
        [
            'title' => 'Công việc của tôi',
            'url' => '/tasks',
            'route' => 'tasks.index',
            'icon' => 'tasks',
            'placeholder' => true,
        ],
        [
            'title' => 'Trường THPT',
            'icon' => 'school',
            'children' => [
                ['title' => 'Danh sách trường', 'url' => '/high-schools', 'route' => 'high-schools.index', 'placeholder' => true],
                ['title' => 'Người liên hệ', 'url' => '/school-contacts', 'route' => 'school-contacts.index', 'placeholder' => true],
                ['title' => 'Hoạt động tại trường', 'url' => '/school-activities', 'route' => 'school-activities.index', 'phase' => 'P1', 'placeholder' => true],
            ],
        ],
        [
            'title' => 'Marketing',
            'icon' => 'marketing',
            'children' => [
                ['title' => 'Chiến dịch', 'url' => '/campaigns', 'route' => 'campaigns.index', 'placeholder' => true],
                ['title' => 'Form thu thập', 'url' => '/forms', 'route' => 'forms.index', 'placeholder' => true],
            ],
        ],
        [
            'title' => 'Đào tạo & chỉ tiêu',
            'icon' => 'majors',
            'children' => [
                ['title' => 'Ngành', 'url' => '/majors', 'route' => 'majors.index', 'placeholder' => true],
                ['title' => 'Tiến độ chỉ tiêu', 'url' => '/quotas', 'route' => 'quotas.index', 'phase' => 'P1', 'placeholder' => true],
            ],
        ],
        [
            'title' => 'Báo cáo',
            'url' => '/reports',
            'route' => 'reports.index',
            'icon' => 'reports',
            'placeholder' => true,
        ],
        [
            'title' => 'Cài đặt',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Mùa tuyển sinh', 'url' => '/settings/cycles', 'route' => 'settings.cycles.index', 'placeholder' => true],
                ['title' => 'Pipeline', 'url' => '/settings/pipelines', 'route' => 'settings.pipelines.index', 'placeholder' => true],
                ['title' => 'Danh mục', 'url' => '/settings/catalogs', 'route' => 'settings.catalogs.index'],
                ['title' => 'Vùng phụ trách', 'url' => '/settings/territories', 'route' => 'settings.territories.index', 'placeholder' => true],
                ['title' => 'Người dùng & vai trò', 'url' => '/settings/users', 'route' => 'settings.users.index', 'placeholder' => true],
                ['title' => 'Trường tuỳ chỉnh', 'url' => '/settings/custom-fields', 'route' => 'settings.custom-fields.index', 'phase' => 'P1', 'placeholder' => true],
                ['title' => 'Quy tắc phân bổ', 'url' => '/settings/assignment-rules', 'route' => 'settings.assignment-rules.index', 'phase' => 'P1', 'placeholder' => true],
            ],
        ],
    ],

];
