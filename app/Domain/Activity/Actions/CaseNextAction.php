<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;

/**
 * Keeps admission_cases.next_action_at equal to the earliest due date of the case's open tasks
 * (null when it has none). Called whenever a task of a case is created, changed, completed or deleted.
 */
class CaseNextAction
{
    public function sync(?string $subjectType, ?int $subjectId): void
    {
        if ($subjectType !== 'admission_case' || $subjectId === null) {
            return;
        }

        $next = Task::query()->withoutGlobalScopes()->where('subject_type', 'admission_case')->where('subject_id', $subjectId)
            ->where('status', TaskStatus::Todo->value)->whereNotNull('due_at')->min('due_at');

        AdmissionCase::query()->withoutGlobalScopes()->whereKey($subjectId)->update(['next_action_at' => $next]);
    }
}
