<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ScoreSource;
use App\Domain\Catalog\Models\AdmissionMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionMethod>
 */
class AdmissionMethodFactory extends Factory
{
    protected $model = AdmissionMethod::class;

    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(600, 9999),
            'name' => 'Phương thức thử nghiệm',
            'score_source' => ScoreSource::Transcript,
        ];
    }
}
