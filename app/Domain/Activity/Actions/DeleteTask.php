<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Models\Task;

/**
 * Deletes a task and lets its case's next action fall back to the remaining open tasks.
 */
class DeleteTask
{
    public function __construct(private readonly CaseNextAction $nextAction) {}

    public function handle(Task $task): void
    {
        $task->delete();

        $this->nextAction->sync($task->subject_type, $task->subject_id);
    }
}
