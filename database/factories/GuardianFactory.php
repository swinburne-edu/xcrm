<?php

namespace Database\Factories;

use App\Domain\Applicant\Models\Guardian;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'full_name' => fake('vi_VN')->name()];
    }
}
