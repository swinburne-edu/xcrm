<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\WardType;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    protected $model = Ward::class;

    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'code' => (string) fake()->unique()->numberBetween(10000, 99999),
            'name' => 'Phường '.fake()->streetName(),
            'type' => WardType::Ward,
            'is_active' => true,
        ];
    }
}
