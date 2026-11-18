<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;
use App\Models\User;

/**
 * Completes, cancels or reopens a task. Done stamps completed_at; reopening clears it. A finished task
 * of a case is noted on the case's timeline and the case's next action moves on to the next open task.
 */
class SetTaskStatus
{
    public function __construct(private readonly CaseNextAction $nextAction, private readonly RecordActivity $record) {}

    public function handle(Task $task, TaskStatus $status, User $by): Task
    {
        $task->status = $status;
        $task->completed_at = $status === TaskStatus::Done ? now() : null;
        $task->save();

        $this->nextAction->sync($task->subject_type, $task->subject_id);

        if ($status !== TaskStatus::Todo && $task->subject_type === 'admission_case') {
            $case = AdmissionCase::query()->withoutGlobalScopes()->find($task->subject_id);

            if ($case !== null) {
                $this->record->handle($case, ActivityType::System, [
                    'title' => ($status === TaskStatus::Done ? 'Hoàn thành việc: ' : 'Huỷ việc: ').$task->title,
                    'meta' => ['task_id' => $task->id],
                ], $by);
            }
        }

        return $task;
    }
}
