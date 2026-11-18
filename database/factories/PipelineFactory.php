<?php

namespace Database\Factories;

use App\Domain\Admission\Models\Pipeline;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'name' => 'Pipeline '.fake()->unique()->numberBetween(1, 99999), 'level' => 'university', 'is_default' => false];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
