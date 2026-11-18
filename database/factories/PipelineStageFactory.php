<?php

namespace Database\Factories;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return ['pipeline_id' => Pipeline::factory(), 'code' => "stage-{$n}", 'name' => "Giai đoạn {$n}", 'type' => StageType::Open, 'sort_order' => 0, 'probability' => 10];
    }

    public function type(StageType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
