<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Safe to run more than once.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            CatalogSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            // Local login for development (password from UserFactory). Roles come in task 1.3.
            $user = User::query()->where('email', 'test@example.com')->first()
                ?? User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);

            $user->givePermissionTo(PermissionSeeder::PERMISSIONS);

            // Per-organization defaults (sources, lost reasons, call results). New organizations
            // created later must call this action too (organization setup, task 1.3).
            if ($user->organization) {
                app(SeedOrganizationCatalogs::class)->handle($user->organization);
            }
        }
    }
}
