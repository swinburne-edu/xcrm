<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\SubjectCombination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectCombination>
 */
class SubjectCombinationFactory extends Factory
{
    protected $model = SubjectCombination::class;

    public function definition(): array
    {
        return [
            'code' => 'X'.fake()->unique()->numberBetween(10, 9999),
            'name' => 'Tổ hợp thử nghiệm',
            'is_active' => true,
        ];
    }
}
