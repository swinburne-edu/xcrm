<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Tab "Vai trò" of /settings/users: read-only overview of the default roles, their data scope
 * and permissions. Roles are defined in RoleDefinitions (seeded), not edited in the UI.
 */
class RoleController extends Controller
{
    private const OBJECT_LABELS = [
        'applicant' => 'Thí sinh', 'case' => 'Hồ sơ tuyển sinh', 'application' => 'Nguyện vọng',
        'high_school' => 'Trường THPT', 'school_contact' => 'Người liên hệ', 'school_activity' => 'Hoạt động tại trường',
        'campaign' => 'Chiến dịch', 'form' => 'Form thu thập', 'major' => 'Ngành', 'cycle' => 'Mùa tuyển sinh',
        'task' => 'Công việc', 'appointment' => 'Lịch hẹn', 'report' => 'Báo cáo', 'catalog' => 'Danh mục',
        'territory' => 'Vùng phụ trách', 'pipeline' => 'Pipeline', 'assignment_rule' => 'Quy tắc phân bổ',
        'custom_field' => 'Trường tuỳ chỉnh', 'user' => 'Người dùng', 'team' => 'Nhóm', 'role' => 'Vai trò',
    ];

    public function index(Request $request, CurrentOrganization $organization): Response
    {
        abort_unless($request->user()->can('role.view'), 403);

        $organizationId = $organization->id();
        $roles = Role::query()->whereIn('name', RoleDefinitions::roleCodes())->with('permissions:id,name')->get()->keyBy('name');

        $userCounts = User::query()
            ->where('organization_id', $organizationId)
            ->with('roles:id,name')
            ->get()
            ->flatMap(fn (User $u) => $u->roles->pluck('name'))
            ->countBy();

        $rows = array_map(function (string $code) use ($roles, $userCounts): array {
            $grouped = [];

            foreach ($roles[$code]?->permissions->pluck('name') ?? [] as $name) {
                [$object, $action] = explode('.', $name, 2);
                $grouped[$object][] = $action;
            }

            return [
                'code' => $code,
                'label' => RoleDefinitions::label($code),
                'scope' => RoleDefinitions::defaultScope($code)->value,
                'scope_label' => RoleDefinitions::defaultScope($code)->label(),
                'users_count' => $userCounts[$code] ?? 0,
                'permissions_count' => count($roles[$code]?->permissions ?? []),
                'permissions' => collect($grouped)->map(fn (array $actions, string $object) => [
                    'object' => $object,
                    'label' => self::OBJECT_LABELS[$object] ?? $object,
                    'actions' => $actions,
                ])->values()->all(),
            ];
        }, RoleDefinitions::roleCodes());

        return Inertia::render('Users/Index', [
            'tab' => 'roles',
            'tabs' => UserController::tabs(),
            'roles' => $rows,
            'can' => [],
            'breadcrumbs' => UserController::breadcrumbs('Vai trò'),
        ]);
    }
}
