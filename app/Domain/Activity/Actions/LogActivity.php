<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\CallResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * What a person types into the timeline: a note, a call (with its result), a message, a meeting.
 *
 * On a case, a call whose result means "reached the person" (call_results.is_contacted):
 *  - sets first_contacted_at when it is still empty;
 *  - moves a case sitting in stage "new" to "contacted" (flow F4 step 1).
 * A call with callback_at also books a "Gọi lại" task. An entry typed on an applicant lands on the applicant's
 * case of the given cycle when there is one (so the case is "touched"), else on the applicant.
 */
class LogActivity
{
    public function __construct(
        private readonly RecordActivity $record,
        private readonly SaveTask $tasks,
        private readonly MoveCaseStage $move,
    ) {}

    /**
     * @param  array<string, mixed>  $data  type, body, title, direction, call_result_id, duration_sec, occurred_at, callback_at
     */
    public function handle(Model $subject, array $data, User $by, ?int $cycleId = null): Activity
    {
        $type = ActivityType::from($data['type']);

        if (! $type->manual()) {
            throw ValidationException::withMessages(['type' => 'Loại hoạt động này do hệ thống ghi.']);
        }

        $subject = $this->target($subject, $cycleId);
        $result = null;

        if ($type === ActivityType::Call && ! empty($data['call_result_id'])) {
            $result = CallResult::query()->withoutGlobalScopes()->where('organization_id', $subject->getAttribute('organization_id'))->find($data['call_result_id'])
                ?? throw ValidationException::withMessages(['call_result_id' => 'Kết quả cuộc gọi không hợp lệ.']);
        }

        $activity = $this->record->handle($subject, $type, [
            'title' => $data['title'] ?? ($result !== null ? "Cuộc gọi: {$result->name}" : null),
            'body' => $data['body'] ?? null,
            'direction' => $data['direction'] ?? ($type === ActivityType::Call ? 'out' : null),
            'call_result_id' => $result?->id,
            'duration_sec' => $data['duration_sec'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ], $by);

        if ($subject instanceof AdmissionCase && $type === ActivityType::Call && $result?->is_contacted) {
            $this->markContacted($subject, $activity, $by);
        }

        if ($type === ActivityType::Call && ! empty($data['callback_at']) && $subject instanceof AdmissionCase) {
            $this->tasks->handle(null, ['title' => 'Gọi lại', 'type' => 'call_back', 'due_at' => $data['callback_at']], $by, $subject);
        }

        return $activity;
    }

    private function target(Model $subject, ?int $cycleId): Model
    {
        if ($subject instanceof Applicant && $cycleId !== null) {
            return AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $subject->id)->where('cycle_id', $cycleId)->first() ?? $subject;
        }

        return $subject;
    }

    private function markContacted(AdmissionCase $case, Activity $activity, User $by): void
    {
        $case->refresh();

        if ($case->first_contacted_at === null) {
            AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['first_contacted_at' => $activity->occurred_at]);
        }

        $case->loadMissing('stage');

        if ($case->stage?->code === 'new') {
            $contacted = PipelineStage::query()->where('pipeline_id', $case->pipeline_id)->where('code', 'contacted')->first();

            if ($contacted !== null) {
                $this->move->handle($case, $contacted, $by);
            }
        }
    }
}
