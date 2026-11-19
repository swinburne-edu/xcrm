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
                ['title' => 'Hồ sơ tuyển sinh', 'url' => '/cases', 'route' => 'cases.index', 'permission' => 'case.view'],
                ['title' => 'Thí sinh', 'url' => '/applicants', 'route' => 'applicants.index', 'permission' => 'applicant.view'],
                ['title' => 'Có thể trùng', 'url' => '/applicants/duplicates', 'route' => 'applicants.duplicates', 'permission' => 'applicant.merge'],
                ['title' => 'Nguyện vọng', 'url' => '/applications', 'route' => 'applications.index', 'permission' => 'application.view'],
                ['title' => 'Lịch hẹn', 'url' => '/appointments', 'route' => 'appointments.index', 'permission' => 'appointment.view'],
            ],
        ],
        [
            'title' => 'Công việc của tôi',
            'url' => '/tasks',
            'route' => 'tasks.index',
            'icon' => 'tasks',
            'permission' => 'task.view',
        ],
        [
            'title' => 'Trường THPT',
            'icon' => 'school',
            'children' => [
                ['title' => 'Danh sách trường', 'url' => '/high-schools', 'route' => 'high-schools.index', 'permission' => 'high_school.view'],
                ['title' => 'Người liên hệ', 'url' => '/school-contacts', 'route' => 'school-contacts.index', 'placeholder' => true],
                ['title' => 'Bản đồ', 'url' => '/map', 'route' => 'map', 'permission' => 'report.view'],
                ['title' => 'Hoạt động tại trường', 'url' => '/school-activities', 'route' => 'school-activities.index', 'permission' => 'school_activity.view'],
            ],
        ],
        [
            'title' => 'Marketing',
            'icon' => 'marketing',
            'children' => [
                ['title' => 'Chiến dịch', 'url' => '/campaigns', 'route' => 'campaigns.index', 'permission' => 'campaign.view'],
                ['title' => 'Form thu thập', 'url' => '/forms', 'route' => 'forms.index', 'permission' => 'form.view'],
                ['title' => 'Cuộc gọi', 'url' => '/calls', 'route' => 'calls.index', 'permission' => 'call.view'],
                ['title' => 'Tin nhắn đã gửi', 'url' => '/messages', 'route' => 'messages.index', 'permission' => 'message.view'],
            ],
        ],
        [
            'title' => 'Đào tạo & chỉ tiêu',
            'icon' => 'majors',
            'children' => [
                ['title' => 'Ngành', 'url' => '/majors', 'route' => 'majors.index', 'permission' => 'major.view'],
                ['title' => 'Tiến độ chỉ tiêu', 'url' => '/quotas', 'route' => 'quotas.index', 'permission' => 'major.view'],
            ],
        ],
        [
            'title' => 'Báo cáo',
            'url' => '/reports',
            'route' => 'reports.index',
            'icon' => 'reports',
            'permission' => 'report.view',
        ],
        [
            'title' => 'Cài đặt',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Mùa tuyển sinh', 'url' => '/settings/cycles', 'route' => 'settings.cycles.index', 'permission' => 'cycle.view'],
                ['title' => 'Pipeline', 'url' => '/settings/pipelines', 'route' => 'settings.pipelines.index', 'permission' => 'pipeline.view'],
                ['title' => 'Danh mục', 'url' => '/settings/catalogs', 'route' => 'settings.catalogs.index', 'permission' => 'catalog.view'],
                ['title' => 'Vùng phụ trách', 'url' => '/settings/territories', 'route' => 'settings.territories.index', 'permission' => 'territory.view'],
                ['title' => 'Người dùng & vai trò', 'url' => '/settings/users', 'route' => 'settings.users.index', 'permission' => 'user.view'],
                ['title' => 'Trường tuỳ chỉnh', 'url' => '/settings/custom-fields', 'route' => 'settings.custom-fields.index', 'permission' => 'custom_field.view'],
                ['title' => 'Tích hợp lead ads', 'url' => '/settings/lead-integrations', 'route' => 'settings.lead-integrations.index', 'permission' => 'lead_integration.view'],
                ['title' => 'Mẫu tin nhắn', 'url' => '/settings/message-templates', 'route' => 'settings.message-templates.index', 'permission' => 'message_template.view'],
                ['title' => 'Tích hợp tổng đài', 'url' => '/settings/call-integrations', 'route' => 'settings.call-integrations.index', 'permission' => 'call_integration.view'],
                ['title' => 'Workflow tự động', 'url' => '/settings/workflows', 'route' => 'settings.workflows.index', 'permission' => 'workflow.view'],
                ['title' => 'Quy tắc phân bổ', 'url' => '/settings/assignment-rules', 'route' => 'settings.assignment-rules.index', 'permission' => 'assignment_rule.view'],
            ],
        ],
        [
            'title' => 'Hướng dẫn sử dụng',
            'url' => '/help',
            'route' => 'help',
            'icon' => 'help',
        ],
    ],

];
