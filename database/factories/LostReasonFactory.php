<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\LostReason;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LostReason>
 */
class LostReasonFactory extends Factory
{
    protected $model = LostReason::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Lý do '.fake()->unique()->numberBetween(1, 99999),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
