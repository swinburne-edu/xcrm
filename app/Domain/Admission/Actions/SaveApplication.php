<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Services\ScoreCalculator;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a draft nguyện vọng of a case and scores it.
 *  - R3: at most 15 per case; preference_order 1–15, unique per (case, round). Without an order the next free one is used.
 *  - R4: the combination belongs to the major in the case's cycle, the method to the major and to the same cycle,
 *        and the round to the same cycle.
 *  - R8: nothing changes in a closed cycle. Only drafts are edited; later steps go through TransitionApplication.
 */
class SaveApplication
{
    public function __construct(private readonly ScoreCalculator $calculator) {}

    /**
     * @param  array{major_id: int|string, cycle_method_id: int|string, combination_id?: int|string|null, round_id: int|string, preference_order?: int|string|null, encourage_bonus?: int|float|string|null}  $data
     */
    public function handle(AdmissionCase $case, ?Application $application, array $data, ?User $by = null): Application
    {
        $case->loadMissing('cycle');

        if ($case->cycle->isClosed()) {
            throw ValidationException::withMessages(['major_id' => 'Mùa tuyển sinh đã đóng, không thể sửa nguyện vọng.']);
        }

        if ($application !== null && $application->status !== ApplicationStatus::Draft) {
            throw ValidationException::withMessages(['major_id' => 'Chỉ sửa được nguyện vọng ở trạng thái nháp.']);
        }

        $major = Major::query()->find($data['major_id']) ?? throw ValidationException::withMessages(['major_id' => 'Ngành không tồn tại.']);
        $method = CycleMethod::query()->where('cycle_id', $case->cycle_id)->find($data['cycle_method_id'])
            ?? throw ValidationException::withMessages(['cycle_method_id' => 'Phương thức không thuộc mùa tuyển sinh của hồ sơ.']);
        $round = AdmissionRound::query()->where('cycle_id', $case->cycle_id)->find($data['round_id'])
            ?? throw ValidationException::withMessages(['round_id' => 'Đợt xét tuyển không thuộc mùa tuyển sinh của hồ sơ.']);
        $combinationId = filled($data['combination_id'] ?? null) ? (int) $data['combination_id'] : null;

        $this->checkRule4($case, $major, $method, $combinationId);

        return DB::transaction(function () use ($case, $application, $data, $major, $method, $round, $combinationId): Application {
            $others = Application::query()->where('case_id', $case->id)->when($application, fn ($q) => $q->whereKeyNot($application->id));

            if ((clone $others)->count() >= Application::MAX_PER_CASE) {
                throw ValidationException::withMessages(['preference_order' => 'Mỗi hồ sơ chỉ có tối đa '.Application::MAX_PER_CASE.' nguyện vọng.']);
            }

            if ((clone $others)->where('status', '!=', ApplicationStatus::Withdrawn->value)->where('round_id', $round->id)->where('major_id', $major->id)->where('cycle_method_id', $method->id)->where('combination_id', $combinationId)->exists()) {
                throw ValidationException::withMessages(['major_id' => 'Nguyện vọng này (ngành, phương thức, tổ hợp) đã có trong đợt (có thể rút nguyện vọng cũ để thêm lại).']);
            }

            $taken = (clone $others)->where('round_id', $round->id)->pluck('preference_order')->all();
            $order = filled($data['preference_order'] ?? null) ? (int) $data['preference_order'] : $this->nextOrder($taken);

            if ($order < 1 || $order > Application::MAX_PER_CASE) {
                throw ValidationException::withMessages(['preference_order' => 'Thứ tự nguyện vọng từ 1 đến '.Application::MAX_PER_CASE.'.']);
            }

            if (in_array($order, $taken, true)) {
                throw ValidationException::withMessages(['preference_order' => "Thứ tự {$order} đã được dùng trong đợt này."]);
            }

            $application ??= new Application(['organization_id' => $case->organization_id, 'case_id' => $case->id, 'status' => ApplicationStatus::Draft]);
            $application->fill([
                'major_id' => $major->id,
                'cycle_method_id' => $method->id,
                'combination_id' => $combinationId,
                'round_id' => $round->id,
                'preference_order' => $order,
            ]);

            if (array_key_exists('encourage_bonus', $data)) {
                $application->encourage_bonus = filled($data['encourage_bonus']) ? $data['encourage_bonus'] : null;
            }

            $application->save();
            $application->unsetRelations();
            $application->forceFill($this->calculator->calculate($application)->toAttributes())->save();

            if ($case->primary_major_id === null) {
                AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['primary_major_id' => $major->id]);
            }

            return $application;
        });
    }

    private function checkRule4(AdmissionCase $case, Major $major, CycleMethod $method, ?int $combinationId): void
    {
        if (! $major->cycleMethods()->whereKey($method->id)->exists()) {
            throw ValidationException::withMessages(['cycle_method_id' => 'Ngành này không xét tuyển theo phương thức đã chọn.']);
        }

        $needsCombination = in_array($method->score_formula['source'] ?? null, ['transcript', 'thpt_exam'], true) && ! $method->is_direct;

        if ($combinationId === null) {
            if ($needsCombination) {
                throw ValidationException::withMessages(['combination_id' => 'Phương thức này tính điểm theo tổ hợp, hãy chọn tổ hợp.']);
            }

            return;
        }

        if (! $major->combinations()->wherePivot('cycle_id', $case->cycle_id)->whereKey($combinationId)->exists()) {
            throw ValidationException::withMessages(['combination_id' => 'Tổ hợp không thuộc ngành này trong mùa tuyển sinh.']);
        }
    }

    /** @param  list<int>  $taken */
    private function nextOrder(array $taken): int
    {
        for ($order = 1; $order <= Application::MAX_PER_CASE; $order++) {
            if (! in_array($order, $taken, true)) {
                return $order;
            }
        }

        return Application::MAX_PER_CASE + 1; // rejected by the range check
    }
}
