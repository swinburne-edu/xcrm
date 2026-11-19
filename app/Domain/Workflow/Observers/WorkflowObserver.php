<?php

namespace App\Domain\Workflow\Observers;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Workflow\Enums\WorkflowObject;
use App\Domain\Workflow\Jobs\RunWorkflowsForEvent;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Domain\Workflow\Services\WorkflowSchema;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered on cases and applicants: turns "created", "stage changed" and "field changed" into a queued
 * RunWorkflowsForEvent, but only when the organization has an active workflow for that object, and never for changes
 * made by a workflow action itself (no loops).
 */
class WorkflowObserver
{
    public function created(Model $model): void
    {
        $this->dispatch($model, 'created');
    }

    public function updated(Model $model): void
    {
        if ($model instanceof AdmissionCase && $model->wasChanged('stage_id')) {
            $this->dispatch($model, 'stage_changed');
        }

        $object = WorkflowObject::tryFrom($model->getMorphClass());
        $changed = $object === null ? [] : array_values(array_intersect(array_keys($model->getChanges()), app(WorkflowSchema::class)->watchable($object)));

        if ($changed !== []) {
            $this->dispatch($model, 'updated', $changed);
        }
    }

    /** @param  list<string>  $changed */
    private function dispatch(Model $model, string $event, array $changed = []): void
    {
        if (WorkflowEngine::running() || ! $this->listening($model)) {
            return;
        }

        RunWorkflowsForEvent::dispatch($event, $model->getMorphClass(), (int) $model->getKey(), $changed)->afterCommit();
    }

    private function listening(Model $model): bool
    {
        return Workflow::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where('organization_id', $model->getAttribute('organization_id'))
            ->where('object', $model->getMorphClass())->where('is_active', true)->exists();
    }
}
