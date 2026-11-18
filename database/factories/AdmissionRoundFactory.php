<?php

namespace Database\Factories;

use App\Domain\Cycle\Enums\RoundType;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionRound>
 */
class AdmissionRoundFactory extends Factory
{
    protected $model = AdmissionRound::class;

    public function definition(): array
    {
        return [
            'cycle_id' => AdmissionCycle::factory(),
            'name' => 'Đợt '.fake()->unique()->numberBetween(1, 99999),
            'type' => RoundType::MoetSystem,
            'sort_order' => 0,
        ];
    }
}
