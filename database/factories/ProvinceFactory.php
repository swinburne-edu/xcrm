<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProvinceType;
use App\Domain\Catalog\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(100, 9999),
            'name' => 'Tỉnh '.fake()->city(),
            'type' => ProvinceType::Province,
            'is_active' => true,
        ];
    }
}
