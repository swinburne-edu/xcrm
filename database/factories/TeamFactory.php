<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Nhóm '.fake()->city(),
            'leader_id' => null,
        ];
    }
}
