<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Enums\WorkflowRunStatus;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Runs one workflow on one record: when the conditions hold, every action in order (a failing action is logged and the
 * next one still runs) and one workflow_runs row records the outcome. Records changed by an action never start
 * workflows themselves (running()), so rules cannot trigger each other in a loop.
 */
class WorkflowEngine
{
    private static int $depth = 0;

    public function __construct(private readonly ConditionEvaluator $conditions, private readonly ActionRunner $actions) {}

    public static function running(): bool
    {
        return self::$depth > 0;
    }

    /** @return WorkflowRun|null null when the conditions do not hold (nothing to record) */
    public function run(Workflow $workflow, Model $subject, string $event): ?WorkflowRun
    {
        if (! $this->conditions->passes($workflow->conditions, $subject)) {
            return null;
        }

        $log = [];
        self::$depth++;

        try {
            foreach ($workflow->actions as $action) {
                try {
                    $log[] = ['action' => $action['type'], 'ok' => true, 'message' => $this->actions->run($action, $subject, $workflow, $event)];
                } catch (Throwable $e) {
                    $log[] = ['action' => $action['type'] ?? '?', 'ok' => false, 'message' => $e->getMessage()];
                }
            }
        } finally {
            self::$depth--;
        }

        $ok = count(array_filter($log, fn ($l) => $l['ok']));
        $status = match (true) {
            $ok === count($log) => WorkflowRunStatus::Success,
            $ok === 0 => WorkflowRunStatus::Failed,
            default => WorkflowRunStatus::Partial,
        };

        return WorkflowRun::query()->create([
            'organization_id' => $workflow->organization_id,
            'workflow_id' => $workflow->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'status' => $status,
            'log' => $log,
            'ran_at' => now(),
        ]);
    }
}
