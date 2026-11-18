<?php

namespace Database\Factories;

use App\Domain\Major\Models\Faculty;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    protected $model = Faculty::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return ['organization_id' => Organization::factory(), 'code' => "K{$n}", 'name' => "Khoa {$n}"];
    }
}
