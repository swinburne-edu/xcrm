<?php

namespace App\Domain\Admission\Observers;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;

/**
 * Rule R7: a case's status always equals the type of its stage. Keeps the two in step whenever the
 * stage changes (or a case is created), whichever code path did it.
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
}
