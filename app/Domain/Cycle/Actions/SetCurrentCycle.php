<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Jobs\CreateCasesForGraduatingApplicantsJob;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rule R10: one current cycle per organization and level. Making a cycle current activates it
 * (flow F1 step 6); a closed cycle cannot be current.
 */
class SetCurrentCycle
{
    public function handle(AdmissionCycle $cycle): AdmissionCycle
    {
        if ($cycle->isClosed()) {
            throw ValidationException::withMessages(['is_current' => 'Mùa đã đóng không thể đặt làm mùa hiện tại. Hãy mở lại mùa trước.']);
        }

        return DB::transaction(function () use ($cycle): AdmissionCycle {
            AdmissionCycle::query()
                ->where('organization_id', $cycle->organization_id)
                ->where('level', $cycle->level)
                ->where('is_current', true)
                ->whereKeyNot($cycle->id)
                ->update(['is_current' => false]);

            $cycle->forceFill(['is_current' => true, 'status' => CycleStatus::Active])->save();
            CreateCasesForGraduatingApplicantsJob::dispatch($cycle->id)->afterCommit();

            return $cycle;
        });
    }
}
