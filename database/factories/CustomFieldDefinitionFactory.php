<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Platform\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'organization_id' => Organization::factory(),
            'object' => CustomFieldObject::Applicants,
            'key' => "field_{$n}",
            'label' => "Trường {$n}",
            'type' => CustomFieldType::Text,
            'options' => null,
            'is_required' => false,
            'is_filterable' => true,
            'sort_order' => 0,
        ];
    }
}
