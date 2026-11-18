<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\HighSchool\Models\ActivityAttendee;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Validation\ValidationException;

/**
 * Flow F2 steps 2–3: a student scans the QR code and fills the short form.
 *  - The lead goes through IntakeLead like any web lead: duplicate check (phone → email → CCCD), applicant (school and
 *    priority area taken from the activity's school), the case of the current cycle with source "Tư vấn tại trường…",
 *    the activity and campaign, a touch, the owner by assignment rules / territory of the school, the first-call task.
 *  - The student is recorded once per activity (activity_attendees); scanning again refreshes the form data only.
 *  - Open only while the activity is planned or confirmed and inside its time window (2 h before the start until
 *    12 h after the end; 6 h long when no end is set).
 */
class CheckInToActivity
{
    public const OPENS_BEFORE_HOURS = 2;

    public const CLOSES_AFTER_HOURS = 12;

    public function __construct(private readonly IntakeLead $intake, private readonly CurrentOrganization $current) {}

    /** Why check-in is not possible right now: cancelled | done | not_started | ended; null = open. */
    public function closedReason(SchoolActivity $activity): ?string
    {
        return match (true) {
            $activity->status->value === 'cancelled' => 'cancelled',
            $activity->status->value === 'done' => 'done',
            $activity->start_at !== null && now()->lt($activity->start_at->copy()->subHours(self::OPENS_BEFORE_HOURS)) => 'not_started',
            $activity->start_at !== null && now()->gt(($activity->end_at ?? $activity->start_at->copy()->addHours(6))->copy()->addHours(self::CLOSES_AFTER_HOURS)) => 'ended',
            default => null,
        };
    }

    /**
     * @param  array{full_name: string, phone: string, class_name?: string|null, major?: int|string|null, email?: string|null}  $input
     * @return array{applicant: Applicant, attendee: ActivityAttendee, new_applicant: bool, repeat: bool}
     */
    public function handle(SchoolActivity $activity, array $input): array
    {
        if ($this->closedReason($activity) !== null) {
            throw ValidationException::withMessages(['activity' => 'Hoạt động này hiện không nhận điểm danh.']);
        }

        $organizationId = $activity->organization_id;
        $sourceId = LeadSource::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('code', $activity->type->sourceCode())->value('id');

        $result = $this->intake->handle($organizationId, [
            'full_name' => $input['full_name'],
            'phone' => $input['phone'],
            'email' => $input['email'] ?? null,
            'major' => $input['major'] ?? null,
            'high_school' => $activity->high_school_id === null ? null : (string) $activity->high_school_id,
            'consent' => true,
        ], [
            'source_id' => $sourceId,
            'campaign_id' => $activity->campaign_id,
            'school_activity_id' => $activity->id,
            'label' => "Điểm danh tại hoạt động “{$activity->title}”",
            'payload' => ['activity' => $activity->title, 'class_name' => $input['class_name'] ?? null],
        ]);

        return $this->current->run($organizationId, function () use ($activity, $input, $result): array {
            /** @var Applicant $applicant */
            $applicant = $result['applicant'];
            $school = $activity->high_school_id === null ? null : HighSchool::query()->withoutGlobalScopes()->find($activity->high_school_id);

            if (filled($input['class_name'] ?? null) && blank($applicant->class_name)) {
                $applicant->class_name = mb_substr(trim($input['class_name']), 0, 50);
            }

            if ($applicant->priority_area_id === null && $school?->priority_area_id !== null) {
                $applicant->priority_area_id = $school->priority_area_id; // KV comes from the school
            }

            if ($applicant->isDirty()) {
                $applicant->save();
            }

            $attendee = ActivityAttendee::query()->where('school_activity_id', $activity->id)->where('applicant_id', $applicant->id)->first();
            $formData = array_filter(['class_name' => $input['class_name'] ?? null, 'major' => $input['major'] ?? null, 'email' => $input['email'] ?? null], fn ($v) => $v !== null && $v !== '');

            if ($attendee === null) {
                $attendee = ActivityAttendee::query()->create([
                    'organization_id' => $activity->organization_id,
                    'school_activity_id' => $activity->id,
                    'applicant_id' => $applicant->id,
                    'checked_in_at' => now(),
                    'form_data' => $formData ?: null,
                ]);

                return ['applicant' => $applicant, 'attendee' => $attendee, 'new_applicant' => $result['created'], 'repeat' => false];
            }

            $attendee->forceFill(['form_data' => $formData + ($attendee->form_data ?? [])])->save();

            return ['applicant' => $applicant, 'attendee' => $attendee, 'new_applicant' => false, 'repeat' => true];
        });
    }
}
