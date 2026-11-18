<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Admission\Models\AdmissionCase;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Writes one timeline entry on a record and keeps the case's last_activity_at current (a case is "touched"
 * by any entry on it). The single place timeline rows are created, for people and for the application alike.
 */
class RecordActivity
{
    /**
     * @param  array<string, mixed>  $data  title, body, direction, call_result_id, duration_sec, recording_url, meta, occurred_at
     */
    public function handle(Model $subject, ActivityType $type, array $data = [], ?User $by = null): Activity
    {
        $alias = $subject->getMorphClass();

        if (! in_array($alias, Activity::SUBJECTS, true)) {
            throw new InvalidArgumentException("Cannot put a timeline on [{$alias}].");
        }

        $occurredAt = $data['occurred_at'] ?? now();

        $activity = Activity::query()->create([
            'organization_id' => $subject->getAttribute('organization_id'),
            'subject_type' => $alias,
            'subject_id' => $subject->getKey(),
            'type' => $type,
            'direction' => $data['direction'] ?? null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'call_result_id' => $data['call_result_id'] ?? null,
            'duration_sec' => $data['duration_sec'] ?? null,
            'recording_url' => $data['recording_url'] ?? null,
            'meta' => $data['meta'] ?? null,
            'occurred_at' => $occurredAt,
            'user_id' => $by?->id,
        ]);

        if ($subject instanceof AdmissionCase) {
            $this->touch($subject, $occurredAt);
        }

        return $activity;
    }

    /** last_activity_at only moves forward; written without events so no observer loop. */
    private function touch(AdmissionCase $case, CarbonInterface|string $at): void
    {
        $at = $at instanceof CarbonInterface ? $at : now();

        if ($case->last_activity_at === null || $case->last_activity_at->lt($at)) {
            AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['last_activity_at' => $at]);
            $case->last_activity_at = $at;
        }
    }
}
