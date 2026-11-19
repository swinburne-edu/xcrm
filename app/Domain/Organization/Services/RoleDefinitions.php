<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Enums\DataScopeLevel;

/**
 * Permissions `{object}.{action}` and the default roles (docs/02-phan-quyen.md).
 * Each feature adds its objects here; DatabaseSeeder → RoleSeeder syncs them to spatie.
 */
final class RoleDefinitions
{
    public const ADMIN = 'admin';

    /** object => actions. "view_sensitive" shows full phone / citizen id. */
    private const OBJECTS = [
        'applicant' => ['view', 'create', 'update', 'delete', 'export', 'import', 'assign', 'merge', 'view_sensitive'],
        'case' => ['view', 'create', 'update', 'delete', 'export', 'assign'],
        'application' => ['view', 'create', 'update', 'delete', 'review'],
        'high_school' => ['view', 'create', 'update', 'delete', 'export', 'import'],
        'school_contact' => ['view', 'create', 'update', 'delete'],
        'school_activity' => ['view', 'create', 'update', 'delete'],
        'campaign' => ['view', 'create', 'update', 'delete', 'approve'],
        'form' => ['view', 'manage'],
        'major' => ['view', 'create', 'update', 'delete'],
        'cycle' => ['view', 'create', 'update', 'delete'],
        'task' => ['view', 'create', 'update', 'delete'],
        'appointment' => ['view', 'create', 'update', 'delete'],
        'report' => ['view', 'export'],
        'catalog' => ['view', 'manage'],
        'territory' => ['view', 'manage'],
        'pipeline' => ['view', 'manage'],
        'assignment_rule' => ['view', 'manage'],
        'custom_field' => ['view', 'manage'],
        'saved_view' => ['manage'],
        'lead_integration' => ['view', 'manage'],
        'message_template' => ['view', 'manage'],
        'message' => ['view', 'send'],
        'user' => ['view', 'create', 'update', 'delete'],
        'team' => ['view', 'create', 'update', 'delete'],
        'role' => ['view'],
    ];

    /**
     * role => label, default data scope and permissions. `*` = everything,
     * `object.*` = every action of an object, `!object.action` removes one.
     */
    private const ROLES = [
        'admin' => [
            'label' => 'Quản trị hệ thống',
            'scope' => DataScopeLevel::All,
            'permissions' => ['*'],
        ],
        'admission_head' => [
            'label' => 'Trưởng phòng tuyển sinh',
            'scope' => DataScopeLevel::All,
            'permissions' => ['*', '!user.create', '!user.update', '!user.delete', '!team.create', '!team.update', '!team.delete'],
        ],
        'team_lead' => [
            'label' => 'Trưởng nhóm khu vực',
            'scope' => DataScopeLevel::Territory,
            'permissions' => [
                'applicant.view', 'applicant.create', 'applicant.update', 'applicant.assign', 'applicant.merge', 'applicant.view_sensitive',
                'case.view', 'case.create', 'case.update', 'case.assign',
                'application.view', 'application.update',
                'high_school.view', 'school_contact.view', 'school_activity.view', 'major.view', 'campaign.view', 'cycle.view',
                'task.*', 'appointment.*', 'report.view', 'user.view', 'team.view', 'catalog.view', 'message_template.view', 'message.*',
            ],
        ],
        'counselor' => [
            'label' => 'Cán bộ tư vấn',
            'scope' => DataScopeLevel::Own,
            'permissions' => [
                'applicant.view', 'applicant.create', 'applicant.update', 'applicant.view_sensitive',
                'case.view', 'case.create', 'case.update',
                'application.view', 'application.create', 'application.update',
                'high_school.view', 'major.view', 'campaign.view', 'cycle.view',
                'task.*', 'appointment.*', 'catalog.view',
            ],
        ],
        'school_officer' => [
            'label' => 'Cán bộ phụ trách trường THPT',
            'scope' => DataScopeLevel::Territory,
            'permissions' => [
                'high_school.*', 'school_contact.*', 'school_activity.*',
                'applicant.view', 'applicant.create', 'case.view', 'major.view', 'campaign.view', 'cycle.view',
                'task.*', 'appointment.*', 'catalog.view',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing',
            'scope' => DataScopeLevel::All,
            'permissions' => [
                'campaign.*', 'form.*', 'report.*', 'message_template.*', 'message.*', 'applicant.view', 'case.view', 'high_school.view',
                'major.view', 'cycle.view', 'catalog.view',
            ],
        ],
        'admission_clerk' => [
            'label' => 'Thư ký xét tuyển',
            'scope' => DataScopeLevel::All,
            'permissions' => [
                'applicant.view', 'applicant.update', 'applicant.import', 'applicant.export', 'applicant.view_sensitive',
                'case.view', 'case.update', 'case.export', 'application.*', 'report.view', 'report.export',
                'major.view', 'cycle.view', 'high_school.view', 'catalog.view',
            ],
        ],
        'faculty_viewer' => [
            'label' => 'Khoa (chỉ xem)',
            'scope' => DataScopeLevel::All,
            'permissions' => ['major.view', 'cycle.view', 'report.view', 'catalog.view'],
        ],
        'ambassador' => [
            'label' => 'Cộng tác viên / Đại sứ',
            'scope' => DataScopeLevel::Own,
            'permissions' => ['applicant.view', 'applicant.create', 'case.view', 'catalog.view'],
        ],
    ];

    /** @return list<string> */
    public static function permissions(): array
    {
        $all = [];

        foreach (self::OBJECTS as $object => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$object}.{$action}";
            }
        }

        return $all;
    }

    /** @return list<string> role codes in display order */
    public static function roleCodes(): array
    {
        return array_keys(self::ROLES);
    }

    public static function label(string $role): string
    {
        return self::ROLES[$role]['label'] ?? $role;
    }

    public static function defaultScope(string $role): DataScopeLevel
    {
        return self::ROLES[$role]['scope'] ?? DataScopeLevel::Own;
    }

    /**
     * Expanded permission names of a role.
     *
     * @return list<string>
     */
    public static function permissionsOf(string $role): array
    {
        $granted = [];

        foreach (self::ROLES[$role]['permissions'] ?? [] as $rule) {
            $remove = str_starts_with($rule, '!');
            $names = self::expand(ltrim($rule, '!'));
            $granted = $remove ? array_diff($granted, $names) : array_merge($granted, $names);
        }

        return array_values(array_unique($granted));
    }

    /** @return list<string> */
    private static function expand(string $rule): array
    {
        if ($rule === '*') {
            return self::permissions();
        }

        if (str_ends_with($rule, '.*')) {
            $object = substr($rule, 0, -2);

            return array_map(fn (string $a) => "{$object}.{$a}", self::OBJECTS[$object] ?? []);
        }

        return [$rule];
    }
}
