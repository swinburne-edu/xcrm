<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Enums\SchoolActivityStatus;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Shared\Support\DisplayTime;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits an activity with a high school (task 2.1, flow F2 step 1).
 *  - A new activity gets its check-in token (the QR code points at /checkin/{token}); the token never changes after.
 *  - R8: not in a closed cycle. "Done" is set by CompleteSchoolActivity, not here.
 *  - The staff are the users listed; the first listed is the lead unless lead_id names one.
 */
class SaveSchoolActivity
{
    /**
     * @param  array{cycle_id: int|string, high_school_id?: int|string|null, campaign_id?: int|string|null, type: string, title: string, start_at?: string|null (YYYY-MM-DDTHH:mm in display time), end_at?: string|null, location?: string|null, status?: string|null, expected_attendees?: int|string|null, staff_ids?: list<int|string>|null, lead_id?: int|string|null}  $data
     */
    public function handle(?SchoolActivity $activity, array $data, ?User $by = null): SchoolActivity
    {
        $cycle = AdmissionCycle::query()->find($data['cycle_id']) ?? throw ValidationException::withMessages(['cycle_id' => 'Mùa tuyển sinh không tồn tại.']);

        if ($cycle->isClosed()) {
            throw ValidationException::withMessages(['cycle_id' => 'Mùa tuyển sinh đã đóng, không thể thêm hoặc sửa hoạt động.']);
        }

        $status = SchoolActivityStatus::tryFrom((string) ($data['status'] ?? $activity?->status->value ?? 'planned')) ?? SchoolActivityStatus::Planned;

        if ($activity?->status === SchoolActivityStatus::Done && $status !== SchoolActivityStatus::Done) {
            throw ValidationException::withMessages(['status' => 'Hoạt động đã diễn ra, không đổi lại trạng thái.']);
        }

        if ($status === SchoolActivityStatus::Done && $activity?->status !== SchoolActivityStatus::Done) {
            throw ValidationException::withMessages(['status' => 'Dùng "Báo cáo sau hoạt động" để chuyển sang đã diễn ra.']);
        }

        return DB::transaction(function () use ($activity, $data, $status): SchoolActivity {
            $activity ??= new SchoolActivity(['checkin_token' => Str::random(40)]);
            $activity->fill([
                'cycle_id' => $data['cycle_id'],
                'high_school_id' => filled($data['high_school_id'] ?? null) ? (int) $data['high_school_id'] : null,
                'campaign_id' => filled($data['campaign_id'] ?? null) ? (int) $data['campaign_id'] : null,
                'type' => $data['type'],
                'title' => trim($data['title']),
                'start_at' => DisplayTime::toUtc($data['start_at'] ?? null),
                'end_at' => DisplayTime::toUtc($data['end_at'] ?? null),
                'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
                'status' => $status,
                'expected_attendees' => filled($data['expected_attendees'] ?? null) ? (int) $data['expected_attendees'] : null,
            ])->save();

            $ids = array_values(array_unique(array_map('intval', array_filter((array) ($data['staff_ids'] ?? []), fn ($v) => is_numeric($v)))));
            $lead = filled($data['lead_id'] ?? null) ? (int) $data['lead_id'] : ($ids[0] ?? null);
            $activity->staff()->sync(collect($ids)->mapWithKeys(fn (int $id) => [$id => ['role' => $id === $lead ? 'lead' : 'member']])->all());

            return $activity;
        });
    }
}
