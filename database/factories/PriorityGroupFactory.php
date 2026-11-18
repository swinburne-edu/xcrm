<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\PriorityGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriorityGroup>
 */
class PriorityGroupFactory extends Factory
{
    protected $model = PriorityGroup::class;

    public function definition(): array
    {
        return [
            'code' => 'UT'.fake()->unique()->numberBetween(10, 999),
            'name' => 'Nhóm ưu tiên thử nghiệm',
            'bonus_score' => '1.00',
        ];
    }
}
