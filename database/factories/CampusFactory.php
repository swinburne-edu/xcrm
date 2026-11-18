<?php

namespace Database\Factories;

use App\Domain\Major\Models\Campus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campus>
 */
class CampusFactory extends Factory
{
    protected $model = Campus::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return ['organization_id' => Organization::factory(), 'code' => "CS{$n}", 'name' => "Cơ sở {$n}", 'province_id' => null, 'address' => null];
    }
}
