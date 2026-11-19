<?php

namespace App\Domain\Workflow\Jobs;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Workflow\Enums\WorkflowObject;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Once a day (crm:run-workflows): workflows with the trigger "schedule" run on every record that meets their
 * conditions and has not been through that workflow yet (so a record is handled once, not every day).
 * Cases: open ones in a cycle that is not closed. Applicants: those not merged into another.
 */
class RunScheduledWorkflows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(WorkflowEngine $engine, CurrentOrganization $current): void
    {
        $workflows = Workflow::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)->get()
            ->filter(fn (Workflow $w) => ($w->trigger['type'] ?? null) === 'schedule');

        foreach ($workflows as $workflow) {
            $current->run($workflow->organization_id, function () use ($workflow, $engine): void {
                $this->subjects($workflow)->chunkById(200, function ($subjects) use ($workflow, $engine): void {
                    foreach ($subjects as $subject) {
                        $engine->run($workflow, $subject, 'schedule');
                    }
                });
            });
        }
    }

    /** @return Builder<*> */
    private function subjects(Workflow $workflow): Builder
    {
        $notRunYet = fn ($q, string $type) => $q->whereNotExists(fn ($r) => $r->select(DB::raw(1))->from('workflow_runs')
            ->where('workflow_runs.workflow_id', $workflow->id)->where('workflow_runs.subject_type', $type)
            ->whereColumn('workflow_runs.subject_id', $type === 'applicant' ? 'applicants.id' : 'admission_cases.id'));

        if ($workflow->object === WorkflowObject::Applicant) {
            return $notRunYet(Applicant::query()->withoutGlobalScopes()->where('organization_id', $workflow->organization_id)->whereNull('deleted_at')->whereNull('merged_into_id'), 'applicant');
        }

        return $notRunYet(AdmissionCase::query()->withoutGlobalScopes()->where('organization_id', $workflow->organization_id)->whereNull('deleted_at')->where('status', 'open')
            ->whereIn('cycle_id', DB::table('admission_cycles')->where('status', '!=', 'closed')->select('id')), 'admission_case');
    }
}
