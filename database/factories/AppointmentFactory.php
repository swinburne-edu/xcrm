<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\AppointmentType;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'case_id' => AdmissionCase::factory(),
            'type' => AppointmentType::Counseling,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
        ];
    }
}
