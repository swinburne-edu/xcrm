<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Task;
use App\Domain\Shared\Support\DisplayTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Create or update a task. due_at arrives in Vietnam time (Y-m-d\TH:i). A new task is assigned to its
 * creator unless an assignee is given; the subject (a case or an applicant) is fixed at creation.
 * Tasks about a case keep the case's next_action_at in step (CaseNextAction).
 */
class SaveTask
{
    public function __construct(private readonly CaseNextAction $nextAction, private readonly RecordActivity $record) {}

    /**
     * @param  array<string, mixed>  $data  title, type, priority, assignee_id, due_at
     */
    public function handle(?Task $task, array $data, User $by, ?Model $subject = null): Task
    {
        $isNew = $task === null;
        $task ??= new Task([
            'organization_id' => $by->organization_id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        $task->fill([
            'title' => $data['title'],
            'type' => $data['type'] ?? 'other',
            'priority' => $data['priority'] ?? 'normal',
            'assignee_id' => $data['assignee_id'] ?? ($isNew ? $by->id : $task->assignee_id),
            'due_at' => array_key_exists('due_at', $data) ? DisplayTime::toUtc($data['due_at']) : $task->due_at,
        ])->save();

        $this->nextAction->sync($task->subject_type, $task->subject_id);

        if ($isNew && $subject !== null && $subject->getMorphClass() === 'admission_case') {
            $this->record->handle($subject, ActivityType::System, ['title' => "Tạo việc: {$task->title}", 'meta' => ['task_id' => $task->id]], $by);
        }

        return $task;
    }
}
