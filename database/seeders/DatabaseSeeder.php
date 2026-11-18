<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Services\RoleDefinitions;
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
            RoleSeeder::class, // also creates the permissions
            CatalogSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            // Local administrator for development (password from UserFactory).
            $user = User::query()->where('email', 'test@example.com')->first()
                ?? User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);

            $user->forceFill(['data_scope' => DataScopeLevel::All])->save();
            $user->syncRoles([RoleDefinitions::ADMIN]);

            // Per-organization defaults (sources, lost reasons, call results). Any code that
            // creates an organization must call this action too.
            if ($user->organization) {
                app(SeedOrganizationCatalogs::class)->handle($user->organization);
            }
        }
    }
}
