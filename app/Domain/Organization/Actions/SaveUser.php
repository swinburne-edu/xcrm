<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a user of the current organization: profile, role, data scope, teams.
 * Role / scope changes are written to the audit log (docs/02-phan-quyen.md).
 */
class SaveUser
{
    public function __construct(private readonly CurrentOrganization $organization) {}

    /**
     * @param  array<string, mixed>  $data  validated by UserRequest
     */
    public function handle(?User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $isNew = $user === null;
            $user ??= new User(['organization_id' => $this->organization->id()]);

            $role = (string) $data['role'];
            $scope = filled($data['data_scope'] ?? null)
                ? DataScopeLevel::from($data['data_scope'])
                : RoleDefinitions::defaultScope($role);
            $active = (bool) ($data['is_active'] ?? true);

            $oldRole = $isNew ? null : $user->roles->first()?->name;
            $oldScope = $isNew ? null : $user->data_scope;

            if (! $isNew) {
                $this->guardLastAdmin($user, stays: $role === RoleDefinitions::ADMIN && $active);
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'data_scope' => $scope,
                'is_active' => $active,
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            if ($isNew) {
                $user->email_verified_at = now(); // created by an administrator, not self-registered
            }

            $user->save();
            $user->syncRoles([$role]);
            $user->teams()->sync($data['team_ids'] ?? []);

            if ($oldRole !== $role) {
                activity()->causedBy($actor)->performedOn($user)
                    ->withProperties(['from' => $oldRole, 'to' => $role])
                    ->log($isNew ? 'Tạo người dùng' : 'Đổi vai trò');
            }

            if (! $isNew && $oldScope !== $scope) {
                activity()->causedBy($actor)->performedOn($user)
                    ->withProperties(['from' => $oldScope?->value, 'to' => $scope->value])
                    ->log('Đổi phạm vi dữ liệu');
            }

            return $user;
        });
    }

    /**
     * An organization must keep at least one active administrator.
     */
    public function guardLastAdmin(User $user, bool $stays): void
    {
        if ($stays || ! $user->hasRole(RoleDefinitions::ADMIN) || ! $user->is_active) {
            return;
        }

        $others = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->role(RoleDefinitions::ADMIN)
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'role' => 'Không thể bỏ quyền hoặc khoá quản trị viên cuối cùng của tổ chức.',
            ]);
        }
    }
}
