<?php

namespace Database\Factories;

use App\Domain\Major\Enums\MajorLevel;
use App\Domain\Major\Enums\ProgramType;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Major>
 */
class MajorFactory extends Factory
{
    protected $model = Major::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'organization_id' => Organization::factory(),
            'moet_code' => str_pad((string) ($n % 10000000), 7, '7', STR_PAD_LEFT),
            'admission_code' => "N{$n}",
            'name' => "Ngành {$n}",
            'program_type' => ProgramType::Standard,
            'level' => MajorLevel::University,
            'duration_years' => 4,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
