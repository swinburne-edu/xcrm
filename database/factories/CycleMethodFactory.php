<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleMethod>
 */
class CycleMethodFactory extends Factory
{
    protected $model = CycleMethod::class;

    public function definition(): array
    {
        return [
            'cycle_id' => AdmissionCycle::factory(),
            'method_id' => AdmissionMethod::factory(),
            'is_direct' => false,
        ];
    }

    public function direct(): static
    {
        return $this->state(['is_direct' => true]);
    }
}
