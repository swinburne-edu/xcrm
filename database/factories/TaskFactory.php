<?php

namespace Database\Factories;

use App\Domain\Activity\Models\Task;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'title' => 'Việc '.fake()->unique()->numberBetween(1, 99999), 'type' => 'other', 'priority' => 'normal', 'status' => 'todo'];
    }
}
