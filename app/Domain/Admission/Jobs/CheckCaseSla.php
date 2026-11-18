<?php

namespace App\Domain\Admission\Jobs;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseStageHistory;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Platform\Services\AssignmentSettings;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use App\Notifications\CaseRecalled;
use App\Notifications\SlaBreached;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Every 15 minutes (scheduler: crm:check-sla), per organization and per its AssignmentSettings:
 *
 * SLA alert  An open, owned case whose stage has sla_hours is past its deadline when `max(entered the stage, was
 *            assigned) + sla_hours` has passed with no call / note / message / meeting on it (or its applicant) since.
 *            The team leader (else the managers) get one SlaBreached notification per stage entry / assignment
 *            (cases.sla_alerted_at remembers it).
 * Recall     With recall_after_days = N, an open owned case with no activity for N days (and no planned next action)
 *            goes back to the shared pool of its team; the previous owner and the team leader are told.
 *
 * Won / lost cases and cases of a closed cycle are never touched.
 */
class CheckCaseSla implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** Entries written by people; the application's own entries (created, stage / owner changes) do not count. */
    private const CONTACT_TYPES = ['call', 'note', 'sms', 'zalo', 'email', 'meeting'];

    public function handle(AssignmentSettings $settings, CurrentOrganization $current): void
    {
        foreach (Organization::query()->get() as $organization) {
            $current->run($organization->id, function () use ($settings, $organization): void {
                if ($settings->slaAlerts($organization)) {
                    $this->alerts($organization);
                }

                if (($days = $settings->recallAfterDays($organization)) !== null) {
                    $this->recall($organization, $days);
                }
            });
        }
    }

    /** @return Builder<AdmissionCase> */
    private function openCases(): Builder
    {
        return AdmissionCase::query()->withoutDataScope()
            ->where('status', 'open')->whereNotNull('owner_id')
            ->whereHas('cycle', fn (Builder $q) => $q->where('status', '!=', 'closed'));
    }

    private function alerts(Organization $organization): void
    {
        $this->openCases()
            ->whereHas('stage', fn (Builder $q) => $q->whereNotNull('sla_hours'))
            ->with(['stage', 'owner:id,name', 'applicant:id,uuid,full_name'])
            ->chunkById(200, function (Collection $cases) use ($organization): void {
                foreach ($cases as $case) {
                    $since = $this->since($case);
                    $deadline = $since->copy()->addHours((int) $case->stage->sla_hours);

                    if (now()->lt($deadline) || ($case->sla_alerted_at !== null && $case->sla_alerted_at->gte($deadline)) || $this->contactedSince($case, $since)) {
                        continue;
                    }

                    $notification = new SlaBreached($case->applicant->full_name, $case->applicant->uuid, $case->stage->name, $case->owner?->name, (int) $case->stage->sla_hours, (int) $deadline->diffInHours(now()));

                    foreach ($this->leaders($case, $organization) as $user) {
                        $user->notify($notification);
                    }

                    AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['sla_alerted_at' => now()]);
                }
            });
    }

    private function recall(Organization $organization, int $days): void
    {
        $cutoff = now()->subDays($days);
        $assign = app(AssignCase::class);
        $record = app(RecordActivity::class);

        $this->openCases()
            ->where(fn (Builder $q) => $q->where('last_activity_at', '<', $cutoff)->orWhere(fn (Builder $n) => $n->whereNull('last_activity_at')->where('created_at', '<', $cutoff)))
            ->where(fn (Builder $q) => $q->whereNull('next_action_at')->orWhere('next_action_at', '<', now()))
            ->with(['owner:id,name', 'applicant:id,uuid,full_name'])
            ->chunkById(200, function (Collection $cases) use ($organization, $days, $assign, $record): void {
                foreach ($cases as $case) {
                    $previous = $case->owner;
                    $recipients = $this->leaders($case, $organization, withOwner: true);

                    $assign->handle($case, null, $case->team_id);
                    AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['sla_alerted_at' => null]);
                    $record->handle($case, ActivityType::System, [
                        'title' => "Thu hồi tự động về kho chung: không có hoạt động trong {$days} ngày",
                        'meta' => ['event' => 'recalled', 'previous_owner_id' => $previous?->id, 'days' => $days],
                    ]);

                    $notification = new CaseRecalled($case->applicant->full_name, $case->applicant->uuid, $previous?->name, $days);

                    foreach ($recipients as $user) {
                        $user->notify($notification);
                    }
                }
            });
    }

    /** When the case last entered its stage or was handed to its owner, whichever is later. */
    private function since(AdmissionCase $case): CarbonInterface
    {
        $entered = CaseStageHistory::query()->where('case_id', $case->id)->where('to_stage_id', $case->stage_id)->orderByDesc('changed_at')->value('changed_at') ?? $case->created_at;
        $entered = $entered instanceof CarbonInterface ? $entered : now()->parse($entered);

        return $case->assigned_at !== null && $case->assigned_at->gt($entered) ? $case->assigned_at : $entered;
    }

    private function contactedSince(AdmissionCase $case, CarbonInterface $since): bool
    {
        return Activity::query()->withoutGlobalScopes()
            ->whereIn('type', self::CONTACT_TYPES)
            ->where('occurred_at', '>=', $since)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $c) => $c->where('subject_type', 'admission_case')->where('subject_id', $case->id))
                ->orWhere(fn (Builder $a) => $a->where('subject_type', 'applicant')->where('subject_id', $case->applicant_id)))
            ->exists();
    }

    /**
     * The team leader of the case; without one, the people who manage assignment. With $withOwner, the owner too.
     *
     * @return list<User>
     */
    private function leaders(AdmissionCase $case, Organization $organization, bool $withOwner = false): array
    {
        $ids = [];
        $leaderId = $case->team_id === null ? null : Team::query()->withoutGlobalScopes()->whereKey($case->team_id)->value('leader_id');

        if ($leaderId !== null) {
            $ids[] = (int) $leaderId;
        }

        if ($withOwner && $case->owner_id !== null) {
            $ids[] = (int) $case->owner_id;
        }

        $users = $ids === [] ? collect() : User::query()->where('organization_id', $organization->id)->where('is_active', true)->whereIn('id', $ids)->get();

        if ($leaderId === null || ! $users->contains('id', (int) $leaderId)) {
            $managers = User::query()->where('organization_id', $organization->id)->where('is_active', true)->permission('assignment_rule.manage')->get();
            $users = $users->merge($managers)->unique('id');
        }

        return $users->reject(fn (User $u) => ! $withOwner && $u->id === $case->owner_id)->values()->all();
    }
}
