<?php

namespace App\Domain\Admission\Observers;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Rule R7: a case's status always equals the type of its stage. Keeps the two in step whenever the
 * stage changes (or a case is created), whichever code path did it.
 *
 * Also writes the timeline: "Tạo hồ sơ", stage_changed and owner_changed (docs/05-luong-nghiep-vu.md F4),
 * attributed to the logged-in user (none for jobs and console).
 */
class AdmissionCaseObserver
{
    public function saving(AdmissionCase $case): void
    {
        if ($case->stage_id === null) {
            return;
        }

        if ($case->isDirty('stage_id') || ! $case->exists) {
            $stage = PipelineStage::query()->find($case->stage_id);

            if ($stage !== null) {
                $case->status = $stage->type->caseStatus();
            }
        }
    }

    public function created(AdmissionCase $case): void
    {
        app(RecordActivity::class)->handle($case, ActivityType::System, ['title' => 'Tạo hồ sơ tuyển sinh', 'meta' => ['event' => 'created']], $this->actor());
    }

    public function updated(AdmissionCase $case): void
    {
        if ($case->wasChanged('stage_id')) {
            $names = PipelineStage::query()->whereIn('id', array_filter([$case->getOriginal('stage_id'), $case->stage_id]))->pluck('name', 'id');

            app(RecordActivity::class)->handle($case, ActivityType::StageChanged, [
                'title' => ($names[$case->getOriginal('stage_id')] ?? '—').' → '.($names[$case->stage_id] ?? '—'),
                'meta' => ['from_stage_id' => $case->getOriginal('stage_id'), 'to_stage_id' => $case->stage_id],
            ], $this->actor());
        }

        if ($case->wasChanged('owner_id')) {
            $users = User::query()->whereIn('id', array_filter([$case->getOriginal('owner_id'), $case->owner_id]))->pluck('name', 'id');

            app(RecordActivity::class)->handle($case, ActivityType::OwnerChanged, [
                'title' => ($case->getOriginal('owner_id') ? $users[$case->getOriginal('owner_id')] ?? '—' : 'Kho chung').' → '.($case->owner_id ? $users[$case->owner_id] ?? '—' : 'Kho chung'),
                'meta' => ['from_owner_id' => $case->getOriginal('owner_id'), 'to_owner_id' => $case->owner_id],
            ], $this->actor());
        }
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
