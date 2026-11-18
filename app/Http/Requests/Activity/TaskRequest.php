<?php

namespace App\Http\Requests\Activity;

use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskType;
use App\Domain\Activity\Models\Task;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / update a task. due_at is Vietnam time (Y-m-d\TH:i). The subject (a case or applicant,
 * resolved through the data scope by the controller) can only be set when creating.
 */
class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', Task::class);
        }

        $task = Task::query()->find($this->route('id'));

        return $task !== null && (bool) $user?->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('id') === null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(TaskType::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', app(CurrentOrganization::class)->id())->where('is_active', true)],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'subject_type' => [$creating ? 'nullable' : 'prohibited', Rule::in(['applicant', 'case', 'high_school'])],
            'subject' => [$creating ? 'nullable' : 'prohibited', 'required_with:subject_type', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'tiêu đề', 'type' => 'loại', 'priority' => 'mức ưu tiên', 'assignee_id' => 'người thực hiện', 'due_at' => 'hạn'];
    }
}
