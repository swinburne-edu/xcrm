<?php

namespace App\Http\Requests\Activity;

use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Activity\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /tasks/{id}/status: done, cancelled, or todo again.
 */
class TaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = Task::query()->find($this->route('id'));

        return $task !== null && (bool) $this->user()?->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(TaskStatus::class)]];
    }
}
