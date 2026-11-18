<?php

namespace Database\Factories;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'full_name' => fake('vi_VN')->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'graduation_year' => 2027,
            'current_grade' => 12,
        ];
    }
}
