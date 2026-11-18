<?php

namespace Database\Seeders;

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
        }
    }
}
