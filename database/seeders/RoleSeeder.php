<?php

namespace Database\Seeders;

use App\Domain\Organization\Services\RoleDefinitions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Default roles (docs/02-phan-quyen.md). Permissions of a default role are reset to the
 * definition each run, so changes in RoleDefinitions reach existing databases.
 * Roles are shared by all organizations.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        foreach (RoleDefinitions::roleCodes() as $code) {
            Role::findOrCreate($code, 'web')->syncPermissions(RoleDefinitions::permissionsOf($code));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
