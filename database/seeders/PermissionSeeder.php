<?php

namespace Database\Seeders;

use App\Domain\Organization\Services\RoleDefinitions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission `{object}.{action}` listed in RoleDefinitions.
 * Roles and their permissions: RoleSeeder.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleDefinitions::permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
