<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Platform\Models\AssignmentRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentRule>
 */
class AssignmentRuleFactory extends Factory
{
    protected $model = AssignmentRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Quy tắc '.fake()->unique()->numberBetween(1, 99999),
            'priority' => 100,
            'strategy' => AssignmentStrategy::RoundRobin,
            'conditions' => null,
            'user_ids' => [],
            'is_active' => true,
        ];
    }
}
