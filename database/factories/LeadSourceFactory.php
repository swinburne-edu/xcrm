<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\LeadChannel;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSource>
 */
class LeadSourceFactory extends Factory
{
    protected $model = LeadSource::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => 'source_'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Nguồn '.fake()->word(),
            'channel' => LeadChannel::Online,
            'is_active' => true,
        ];
    }
}
