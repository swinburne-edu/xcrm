<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Ward;
use App\Domain\Catalog\Models\WardMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WardMapping>
 */
class WardMappingFactory extends Factory
{
    protected $model = WardMapping::class;

    public function definition(): array
    {
        return [
            'old_province_name' => 'Tỉnh '.fake()->city(),
            'old_district_name' => 'Huyện '.fake()->city(),
            'old_ward_name' => 'Xã '.fake()->streetName(),
            'new_ward_id' => Ward::factory(),
        ];
    }
}
