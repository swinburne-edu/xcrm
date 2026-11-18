<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Admission\Enums\ApplicationStatus as S;
use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a nguyện vọng along its life (docs/05 F4): draft → submitted → eligible → admitted → confirmed,
 * or ineligible / rejected / withdrawn. Each step is recorded on the applicant's timeline and pulls the case
 * forward to the matching stage (never backwards, never out of a won/lost stage).
 *  - submit needs a complete score (or a direct-admission method); R8: not in a closed cycle.
 */
class TransitionApplication
{
    /** action => [allowed current statuses, resulting status] */
    public const ACTIONS = [
        'submit' => [[S::Draft], S::Submitted],
        'eligible' => [[S::Submitted], S::Eligible],
        'ineligible' => [[S::Submitted, S::Eligible], S::Ineligible],
        'conditional' => [[S::Submitted, S::Eligible], S::ConditionalAdmit],
        'admit' => [[S::Submitted, S::Eligible, S::ConditionalAdmit], S::Admitted],
        'reject' => [[S::Submitted, S::Eligible, S::ConditionalAdmit], S::Rejected],
        'confirm' => [[S::Admitted], S::Confirmed],
        'withdraw' => [[S::Draft, S::Submitted, S::Eligible, S::ConditionalAdmit, S::Admitted], S::Withdrawn],
    ];

    /** Decisions of the registrar (need application.review). */
    public const REVIEW_ACTIONS = ['eligible', 'ineligible', 'conditional', 'admit', 'reject'];

    /** Case stage (pipeline_stages.code) a status pulls the case to. */
    private const CASE_STAGE = ['submitted' => 'early_applied', 'eligible' => 'eligible', 'admitted' => 'admitted', 'confirmed' => 'confirmed'];

    public function __construct(private readonly RecordActivity $record, private readonly MoveCaseStage $move) {}

    public function handle(Application $application, string $action, ?User $by = null): Application
    {
        [$from, $to] = self::ACTIONS[$action] ?? throw ValidationException::withMessages(['action' => 'Thao tác không hợp lệ.']);

        $application->loadMissing(['case.cycle', 'case.applicant', 'major', 'cycleMethod']);

        if ($application->case->cycle->isClosed()) {
            throw ValidationException::withMessages(['action' => 'Mùa tuyển sinh đã đóng, không thể đổi trạng thái nguyện vọng.']);
        }

        if (! in_array($application->status, $from, true)) {
            throw ValidationException::withMessages(['action' => "Không thể thực hiện khi nguyện vọng đang ở trạng thái \"{$application->status->label()}\"."]);
        }

        if ($action === 'submit' && $application->final_score === null && ! $application->cycleMethod->is_direct) {
            $missing = implode(', ', $application->score_breakdown['missing'] ?? []);

            throw ValidationException::withMessages(['action' => 'Chưa đủ điểm để nộp'.($missing !== '' ? " (thiếu: {$missing})" : '').'.']);
        }

        DB::transaction(function () use ($application, $to, $by): void {
            $now = now();
            $application->status = $to;

            match ($to) {
                S::Submitted => $application->submitted_at = $now,
                S::Confirmed => $application->confirmed_at = $now,
                S::Eligible, S::Ineligible, S::ConditionalAdmit, S::Admitted, S::Rejected => $application->decided_at = $now,
                default => null,
            };

            $application->save();
            $this->syncCase($application, $to, $by);

            $this->record->handle($application->case->applicant, ActivityType::System, [
                'title' => "Nguyện vọng {$application->preference_order} — {$application->major->name}: {$to->label()}",
                'meta' => ['application_id' => $application->id, 'status' => $to->value, 'case_id' => $application->case_id],
            ], $by);
        });

        return $application->refresh();
    }

    private function syncCase(Application $application, S $to, ?User $by): void
    {
        $case = AdmissionCase::query()->withoutDataScope()->with('stage')->findOrFail($application->case_id);

        $rank = Application::query()->where('case_id', $case->id)
            ->whereIn('status', array_map(fn (S $s) => $s->value, [S::Submitted, S::Eligible, S::ConditionalAdmit, S::Admitted, S::Confirmed]))
            ->min('preference_order');
        $case->forceFill(['our_preference_rank' => $rank])->saveQuietly();

        $code = self::CASE_STAGE[$to->value] ?? null;
        $target = $code === null ? null : PipelineStage::query()->where('pipeline_id', $case->pipeline_id)->where('code', $code)->first();

        if ($target === null || $case->stage->type !== StageType::Open || $target->sort_order <= $case->stage->sort_order) {
            return;
        }

        try {
            $this->move->handle($case, $target, $by);
        } catch (ValidationException) {
            // A stage that asks for more fields must not block the application; the case can be moved by hand.
        }
    }
}
