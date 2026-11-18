<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions `{object}.{action}` (docs/02-phan-quyen.md).
 * Each feature adds its own here; roles and their permissions are seeded in task 1.3.
 */
class PermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        // System catalogs (task 1.1)
        'catalog.view',
        'catalog.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
