<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'code' => 'MON'.fake()->unique()->numberBetween(10, 9999),
            'name' => 'Môn '.fake()->word(),
            'is_core' => false,
        ];
    }

    public function math(): static
    {
        return $this->state(['code' => Subject::MATH, 'name' => 'Toán', 'is_core' => true]);
    }

    public function literature(): static
    {
        return $this->state(['code' => Subject::LITERATURE, 'name' => 'Ngữ văn', 'is_core' => true]);
    }
}
