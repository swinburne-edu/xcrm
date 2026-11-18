<?php

namespace App\Domain\Major\Jobs;

use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\QuotaSnapshot;
use App\Domain\Major\Services\QuotaProgress;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use App\Notifications\QuotaAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hourly (scheduler: crm:aggregate-quotas): for every active cycle of every organization it stores today's quota
 * snapshot per major and, when a major has just moved into "short" or "over", notifies the people who manage the
 * cycle (permission cycle.update: administrators, admission head) once, through the bell.
 */
class AggregateQuotaProgress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(QuotaProgress $progress, CurrentOrganization $current): void
    {
        foreach (Organization::query()->get() as $organization) {
            $current->run($organization->id, function () use ($progress, $organization): void {
                foreach (AdmissionCycle::query()->where('status', CycleStatus::Active->value)->get() as $cycle) {
                    $this->aggregate($progress, $organization->id, $cycle);
                }
            });
        }
    }

    private function aggregate(QuotaProgress $progress, int $organizationId, AdmissionCycle $cycle): void
    {
        $today = now(config('app.display_timezone'))->toDateString();
        $recipients = null;

        foreach ($progress->rows($cycle) as $row) {
            $previous = QuotaSnapshot::query()->where('cycle_id', $cycle->id)->where('major_id', $row['major_id'])->orderByDesc('snapshot_date')->value('level');

            QuotaSnapshot::query()->updateOrCreate(
                ['cycle_id' => $cycle->id, 'major_id' => $row['major_id'], 'snapshot_date' => $today],
                ['organization_id' => $organizationId, 'quota_total' => $row['quota'], 'target' => $row['target'], 'interested' => $row['interested'], 'submitted' => $row['submitted'],
                    'eligible' => $row['eligible'], 'admitted' => $row['admitted'], 'confirmed' => $row['confirmed'], 'enrolled' => $row['enrolled'], 'secured' => $row['secured'], 'level' => $row['level']],
            );

            if ($row['level'] === 'ok' || $row['level'] === ($previous ?? 'ok')) {
                continue;
            }

            $recipients ??= User::query()->where('organization_id', $organizationId)->where('is_active', true)->permission('cycle.update')->get();

            foreach ($recipients as $user) {
                $user->notify(new QuotaAlert($row, $cycle->name));
            }
        }
    }
}
