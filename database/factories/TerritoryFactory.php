<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    protected $model = Territory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Vùng '.fake()->unique()->numberBetween(1, 99999),
            'user_id' => null,
            'team_id' => null,
        ];
    }
}
