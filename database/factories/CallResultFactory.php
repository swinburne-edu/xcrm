<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\CallResult;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallResult>
 */
class CallResultFactory extends Factory
{
    protected $model = CallResult::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Kết quả '.fake()->unique()->numberBetween(1, 99999),
            'is_contacted' => false,
            'sort_order' => 0,
        ];
    }

    public function contacted(): static
    {
        return $this->state(['is_contacted' => true]);
    }
}
