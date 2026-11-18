<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Gives a case to a user (and team), or returns it to the shared pool when no owner is given.
 * The owner must belong to the case's organization; nothing changes in a closed cycle (R8).
 */
class AssignCase
{
    public function handle(AdmissionCase $case, ?User $owner, ?int $teamId = null, ?User $by = null): AdmissionCase
    {
        if ($case->cycle()->first()?->isClosed()) {
            throw ValidationException::withMessages(['owner_id' => 'Mùa tuyển sinh đã đóng, không thể giao hồ sơ.']);
        }

        if ($owner !== null && $owner->organization_id !== $case->organization_id) {
            throw ValidationException::withMessages(['owner_id' => 'Người phụ trách không thuộc tổ chức này.']);
        }

        if ($owner !== null && ! $owner->is_active) {
            throw ValidationException::withMessages(['owner_id' => 'Người phụ trách đã ngừng hoạt động.']);
        }

        if ($teamId !== null && ! Team::query()->whereKey($teamId)->exists()) {
            throw ValidationException::withMessages(['team_id' => 'Nhóm không hợp lệ.']);
        }

        $case->owner_id = $owner?->id;
        $case->team_id = $teamId ?? ($owner?->teams()->value('teams.id') ?? $case->team_id);
        $case->assigned_at = $owner === null ? null : now();
        $case->last_activity_at = now();
        $case->save();

        activity()->causedBy($by)->performedOn($case)->withProperties(['owner_id' => $owner?->id, 'team_id' => $case->team_id])->log('Đổi người phụ trách');

        return $case;
    }
}
