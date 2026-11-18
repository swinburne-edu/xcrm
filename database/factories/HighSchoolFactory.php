<?php

namespace Database\Factories;

use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HighSchool>
 */
class HighSchoolFactory extends Factory
{
    protected $model = HighSchool::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'organization_id' => Organization::factory(),
            'code' => "TH{$n}",
            'name' => "THPT Số {$n}",
            'type' => SchoolType::Public,
            'partnership_status' => PartnershipStatus::NotContacted,
        ];
    }
}
