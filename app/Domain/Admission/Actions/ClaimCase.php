<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * "Nhận" a case from the shared pool: assigns it to the user, but only while it still has no owner.
 * The update is conditional, so two people claiming at once cannot both win.
 */
class ClaimCase
{
    public function handle(AdmissionCase $case, User $by): AdmissionCase
    {
        if ($case->cycle()->first()?->isClosed()) {
            throw ValidationException::withMessages(['case' => 'Mùa tuyển sinh đã đóng, không thể nhận hồ sơ.']);
        }

        $claimed = AdmissionCase::query()->withoutDataScope()->whereKey($case->id)->whereNull('owner_id')->update([
            'owner_id' => $by->id,
            'team_id' => $case->team_id ?? $by->teams()->value('teams.id'),
            'assigned_at' => now(),
            'last_activity_at' => now(),
        ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages(['case' => 'Hồ sơ đã có người phụ trách.']);
        }

        activity()->causedBy($by)->performedOn($case)->withProperties(['owner_id' => $by->id])->log('Nhận hồ sơ từ kho chung');

        return $case->refresh();
    }
}
