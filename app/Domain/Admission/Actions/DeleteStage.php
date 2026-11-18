<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseStageHistory;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a stage no case has ever been in. A stage in use (or the last open / won / lost one) stays.
 */
class DeleteStage
{
    public function handle(Pipeline $pipeline, PipelineStage $stage): void
    {
        if (SaveStage::isLastOfType($pipeline, $stage)) {
            throw ValidationException::withMessages(['stage' => "Pipeline cần ít nhất một giai đoạn \"{$stage->type->label()}\"."]);
        }

        $used = AdmissionCase::query()->withoutGlobalScopes()->withTrashed()->where('stage_id', $stage->id)->exists()
            || CaseStageHistory::query()->where(fn ($q) => $q->where('from_stage_id', $stage->id)->orWhere('to_stage_id', $stage->id))->exists();

        if ($used) {
            throw ValidationException::withMessages(['stage' => 'Giai đoạn đã có hồ sơ đi qua nên không xoá được.']);
        }

        $stage->delete();
    }
}
