<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organization\Actions\DeleteUser;
use App\Domain\Organization\Actions\SaveUser;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/users: users of the current organization (tab "Người dùng" of Settings/Users/Index).
 * Teams and roles are the other two tabs (TeamController, RoleController).
 */
class UserController extends Controller
{
    public function __construct(private readonly CurrentOrganization $organization) {}

    /** @return list<array{key: string, title: string, href: string}> */
    public static function tabs(): array
    {
        return [
            ['key' => 'users', 'title' => 'Người dùng', 'href' => '/settings/users'],
            ['key' => 'teams', 'title' => 'Nhóm', 'href' => '/settings/users/teams'],
            ['key' => 'roles', 'title' => 'Vai trò', 'href' => '/settings/users/roles'],
        ];
    }

    /** @return list<array{title: string, href?: string}> */
    public static function breadcrumbs(string $title): array
    {
        return [['title' => 'Cài đặt'], ['title' => 'Người dùng & vai trò', 'href' => '/settings/users'], ['title' => $title]];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = ListQuery::for($this->query(), $request)
            ->filters(
                Filter::search('q', ['name', 'email', 'phone']),
                Filter::callback(
                    'role',
                    fn (Builder $q, array $roles) => $q->whereHas('roles', fn (Builder $r) => $r->whereIn('name', $roles)),
                    fn (mixed $v) => array_values(array_intersect(is_array($v) ? $v : explode(',', (string) $v), RoleDefinitions::roleCodes())) ?: null,
                ),
                Filter::exact('data_scope'),
                Filter::boolean('is_active'),
            )
            ->sorts(['name', 'email', 'last_login_at', 'created_at'])
            ->defaultSort('name')
            ->toProps(fn (User $user) => $this->row($user, $request->user()));

        return Inertia::render('Users/Index', [
            'tab' => 'users',
            'tabs' => self::tabs(),
            'users' => $users,
            'roleOptions' => array_map(fn (string $c) => ['value' => $c, 'label' => RoleDefinitions::label($c)], RoleDefinitions::roleCodes()),
            'scopeOptions' => array_map(fn (DataScopeLevel $l) => ['value' => $l->value, 'label' => $l->label()], DataScopeLevel::cases()),
            'roleScopes' => collect(RoleDefinitions::roleCodes())->mapWithKeys(fn (string $c) => [$c => RoleDefinitions::defaultScope($c)->value])->all(),
            'can' => $this->can($request->user()),
            'breadcrumbs' => self::breadcrumbs('Người dùng'),
        ]);
    }

    public function store(UserRequest $request, SaveUser $save): RedirectResponse
    {
        $user = $save->handle(null, $request->validated(), $request->user());

        return $this->back('success', "Đã thêm người dùng {$user->name}.");
    }

    public function update(UserRequest $request, int $id, SaveUser $save): RedirectResponse
    {
        $user = $this->query()->findOrFail($id);
        $this->authorize('update', $user);

        $save->handle($user, $request->validated(), $request->user());

        return $this->back('success', "Đã cập nhật {$user->name}.");
    }

    public function destroy(Request $request, int $id, DeleteUser $delete): RedirectResponse
    {
        $user = $this->query()->findOrFail($id);
        $this->authorize('delete', $user);

        $delete->handle($user, $request->user());

        return $this->back('success', "Đã xoá người dùng {$user->name}.");
    }

    /** Users of the current organization (User has no organization scope: auth loads it). */
    private function query(): Builder
    {
        $organizationId = $this->organization->id();

        return User::query()
            ->with(['roles:id,name', 'teams:id,name'])
            ->when($organizationId === null, fn (Builder $q) => $q->whereRaw('1 = 0'), fn (Builder $q) => $q->where('organization_id', $organizationId));
    }

    /** @return array<string, mixed> */
    private function row(User $user, User $viewer): array
    {
        $role = $user->roles->first()?->name;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $role,
            'role_label' => $role ? RoleDefinitions::label($role) : null,
            'data_scope' => $user->data_scope->value,
            'data_scope_label' => $user->data_scope->label(),
            'is_active' => $user->is_active,
            'is_self' => $user->is($viewer),
            'team_ids' => $user->teams->pluck('id')->all(),
            'teams' => $user->teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name])->all(),
            'teams_label' => $user->teams->pluck('name')->implode(', '),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /** @return array<string, bool> */
    private function can(User $user): array
    {
        return [
            'create' => $user->can('create', User::class),
            'update' => $user->can('user.update'),
            'delete' => $user->can('user.delete'),
        ];
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back(fallback: '/settings/users');
    }
}
