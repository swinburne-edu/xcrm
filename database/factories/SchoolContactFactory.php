<?php

namespace Database\Factories;

use App\Domain\HighSchool\Enums\ContactRole;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolContact>
 */
class SchoolContactFactory extends Factory
{
    protected $model = SchoolContact::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'high_school_id' => HighSchool::factory(),
            'full_name' => fake()->name(),
            'role' => ContactRole::CareerCounselor,
            'is_primary' => false,
        ];
    }
}
