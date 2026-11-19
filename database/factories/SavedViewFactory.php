<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Models\SavedView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    protected $model = SavedView::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'object' => SavedViewObject::Applicants,
            'name' => 'Chế độ xem '.fake()->unique()->numberBetween(1, 99999),
            'user_id' => null,
            'is_shared' => false,
            'view_type' => 'table',
            'config' => ['filters' => [], 'sort' => null, 'per_page' => null, 'columns' => null],
        ];
    }
}
