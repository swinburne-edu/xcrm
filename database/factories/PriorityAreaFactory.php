<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\PriorityArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriorityArea>
 */
class PriorityAreaFactory extends Factory
{
    protected $model = PriorityArea::class;

    public function definition(): array
    {
        return [
            'code' => 'KV'.fake()->unique()->numberBetween(10, 999),
            'name' => 'Khu vực thử nghiệm',
            'bonus_score' => '0.25',
        ];
    }
}
