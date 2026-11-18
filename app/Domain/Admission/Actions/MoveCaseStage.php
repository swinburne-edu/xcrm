<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseStageHistory;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Catalog\Models\LostReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a case to another stage of its pipeline and records it in case_stage_histories (hours spent in the
 * previous stage included). The status follows the stage type by itself (R7, AdmissionCaseObserver).
 *  - R6: moving to a "lost" stage needs a lost reason of the organization.
 *  - R8: nothing moves in a closed cycle.
 *  - A stage may require case fields (pipeline_stages.required_fields) before a case can enter it.
 *  - Reaching a "won" stage stamps enrolled_at / enrolled_major_id; leaving won or lost clears those fields.
 */
class MoveCaseStage
{
    private const FIELD_LABELS = [
        'primary_major_id' => 'ngành quan tâm chính', 'source_id' => 'nguồn', 'campaign_id' => 'chiến dịch',
        'temperature' => 'mức độ quan tâm', 'owner_id' => 'người phụ trách', 'next_action_at' => 'ngày hành động tiếp theo',
    ];

    /**
     * @param  array{lost_reason_id?: int|string|null, lost_note?: string|null, enrolled_major_id?: int|string|null}  $data
     */
    public function handle(AdmissionCase $case, PipelineStage $to, ?User $by = null, array $data = []): AdmissionCase
    {
        return DB::transaction(function () use ($case, $to, $by, $data): AdmissionCase {
            $case = AdmissionCase::query()->withoutDataScope()->with('cycle')->lockForUpdate()->findOrFail($case->id);

            if ($case->cycle->isClosed()) {
                throw ValidationException::withMessages(['stage_id' => 'Mùa tuyển sinh đã đóng, không thể đổi giai đoạn.']);
            }

            if ($to->pipeline_id !== $case->pipeline_id) {
                throw ValidationException::withMessages(['stage_id' => 'Giai đoạn không thuộc pipeline của hồ sơ.']);
            }

            if ($to->id === $case->stage_id) {
                throw ValidationException::withMessages(['stage_id' => 'Hồ sơ đang ở giai đoạn này.']);
            }

            $lostReasonId = null;

            if ($to->type === StageType::Lost) {
                $lostReasonId = $data['lost_reason_id'] ?? null;

                if (blank($lostReasonId) || ! LostReason::query()->withoutGlobalScopes()->where('organization_id', $case->organization_id)->whereKey($lostReasonId)->exists()) {
                    throw ValidationException::withMessages(['lost_reason_id' => 'Chuyển sang giai đoạn thất bại bắt buộc chọn lý do.']);
                }
            }

            foreach ($to->required_fields ?? [] as $field) {
                if (in_array($field, PipelineStage::REQUIRABLE, true) && blank($case->getAttribute($field))) {
                    throw ValidationException::withMessages(['stage_id' => 'Cần nhập '.(self::FIELD_LABELS[$field] ?? $field)." trước khi chuyển sang \"{$to->name}\"."]);
                }
            }

            $now = now();
            $from = PipelineStage::query()->find($case->stage_id);
            $since = CaseStageHistory::query()->where('case_id', $case->id)->orderByDesc('changed_at')->orderByDesc('id')->value('changed_at') ?? $case->created_at;

            CaseStageHistory::query()->create([
                'case_id' => $case->id,
                'from_stage_id' => $from?->id,
                'to_stage_id' => $to->id,
                'user_id' => $by?->id,
                'hours_in_previous' => $since === null ? null : round(abs($now->diffInSeconds($since)) / 3600, 2),
                'changed_at' => $now,
            ]);

            $case->stage_id = $to->id;
            $case->last_activity_at = $now;
            $case->lost_reason_id = $lostReasonId;
            $case->lost_note = $to->type === StageType::Lost ? ($data['lost_note'] ?? null) : null;

            if ($to->type === StageType::Won) {
                $case->enrolled_at = $now;
                $case->enrolled_major_id = $data['enrolled_major_id'] ?? $case->enrolled_major_id ?? $case->primary_major_id;
            } else {
                $case->enrolled_at = null;
                $case->enrolled_major_id = null;
            }

            $case->save();

            return $case;
        });
    }
}
