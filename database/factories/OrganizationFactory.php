<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'Trường Đại học '.fake()->lastName().' '.fake()->numerify('##'),
            'code' => 'ORG'.strtoupper(Str::random(8)),
            'settings' => null,
        ];
    }
}
