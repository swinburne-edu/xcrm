<?php

namespace Database\Factories;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Enums\SchoolActivityType;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SchoolActivity>
 */
class SchoolActivityFactory extends Factory
{
    protected $model = SchoolActivity::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'cycle_id' => AdmissionCycle::factory(),
            'high_school_id' => HighSchool::factory(),
            'type' => SchoolActivityType::SchoolCounseling,
            'title' => 'Tư vấn tuyển sinh '.fake()->unique()->numberBetween(1, 99999),
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(3),
            'status' => 'planned',
            'checkin_token' => Str::random(40),
        ];
    }
}
