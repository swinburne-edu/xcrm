<?php

namespace Database\Factories;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCase>
 */
class AdmissionCaseFactory extends Factory
{
    protected $model = AdmissionCase::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'applicant_id' => Applicant::factory(),
            'cycle_id' => AdmissionCycle::factory(),
            'pipeline_id' => Pipeline::factory(),
            'stage_id' => fn (array $attrs) => PipelineStage::factory()->create(['pipeline_id' => $attrs['pipeline_id']])->id,
        ];
    }
}
