<?php

namespace Database\Factories;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'case_id' => AdmissionCase::factory(),
            'major_id' => Major::factory(),
            'cycle_method_id' => CycleMethod::factory(),
            'round_id' => AdmissionRound::factory(),
            'preference_order' => 1,
            'status' => 'draft',
        ];
    }
}
