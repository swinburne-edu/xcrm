<?php

namespace Database\Factories;

use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycle>
 */
class AdmissionCycleFactory extends Factory
{
    protected $model = AdmissionCycle::class;

    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2100, 9000);

        return [
            'organization_id' => Organization::factory(),
            'name' => "Tuyển sinh {$year}",
            'year' => $year,
            'level' => CycleLevel::University,
            'status' => CycleStatus::Preparing,
            'is_current' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => CycleStatus::Active]);
    }

    public function closed(): static
    {
        return $this->state(['status' => CycleStatus::Closed]);
    }

    public function current(): static
    {
        return $this->state(['status' => CycleStatus::Active, 'is_current' => true]);
    }
}
