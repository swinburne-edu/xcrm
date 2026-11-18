<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\HighSchool\Enums\SchoolActivityStatus;
use App\Domain\HighSchool\Models\SchoolActivity;
use Illuminate\Validation\ValidationException;

/**
 * The report after an activity (flow F2 step 4): actual attendance, cost and a result note; the status becomes "done"
 * and the check-in link stops taking new students. Without an attendance figure the number of check-ins is used.
 * A report can be corrected later; a cancelled activity has nothing to report.
 */
class CompleteSchoolActivity
{
    /**
     * @param  array{actual_attendees?: int|string|null, cost?: int|string|null, result_note?: string|null}  $data
     */
    public function handle(SchoolActivity $activity, array $data): SchoolActivity
    {
        if ($activity->status === SchoolActivityStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Hoạt động đã huỷ, không có gì để báo cáo.']);
        }

        $activity->forceFill([
            'status' => SchoolActivityStatus::Done,
            'actual_attendees' => filled($data['actual_attendees'] ?? null) ? (int) $data['actual_attendees'] : $activity->attendees()->count(),
            'cost' => filled($data['cost'] ?? null) ? (int) $data['cost'] : null,
            'result_note' => filled($data['result_note'] ?? null) ? trim($data['result_note']) : null,
        ])->save();

        return $activity;
    }
}
