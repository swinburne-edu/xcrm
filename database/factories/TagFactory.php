<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\TagColor;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Thẻ '.fake()->unique()->numberBetween(1, 99999),
            'color' => TagColor::Blue,
        ];
    }
}
