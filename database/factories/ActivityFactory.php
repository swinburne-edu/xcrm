<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subject_type' => 'applicant',
            'subject_id' => Applicant::factory(),
            'type' => ActivityType::Note,
            'body' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
