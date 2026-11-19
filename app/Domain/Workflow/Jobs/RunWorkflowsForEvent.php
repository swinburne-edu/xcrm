<?php

namespace App\Domain\Workflow\Jobs;

use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A record was created or changed: runs the active workflows of its organization that listen to that event.
 * `$event` is created, updated or stage_changed; for "updated" `$changed` lists the fields that changed and a
 * workflow only runs when it watches one of them.
 */
class RunWorkflowsForEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** @param  list<string>  $changed */
    public function __construct(public readonly string $event, public readonly string $subjectType, public readonly int $subjectId, public readonly array $changed = []) {}

    public function handle(WorkflowEngine $engine, CurrentOrganization $current): void
    {
        $class = Relation::getMorphedModel($this->subjectType);
        $subject = $class === null ? null : $class::query()->withoutGlobalScopes()->whereNull('deleted_at')->find($this->subjectId);

        if ($subject === null) {
            return;
        }

        $current->run($subject->organization_id, function () use ($engine, $subject): void {
            $workflows = Workflow::query()->where('is_active', true)->where('object', $this->subjectType)->get()
                ->filter(fn (Workflow $w) => ($w->trigger['type'] ?? null) === $this->event
                    && ($this->event !== 'updated' || in_array($w->trigger['field'] ?? null, $this->changed, true)));

            foreach ($workflows as $workflow) {
                $engine->run($workflow, $subject, $this->event);
            }
        });
    }
}
