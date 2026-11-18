<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a cycle that was never used. Only cycles still in preparation can go (an active or
 * closed season has history), and it is removed for good: the (organization, year, level)
 * unique key must be free again for the corrected cycle. Cycles other tables refer to are protected
 * by foreign keys and refused.
 */
class DeleteCycle
{
    public function handle(AdmissionCycle $cycle): void
    {
        if ($cycle->status !== CycleStatus::Preparing || $cycle->is_current) {
            throw ValidationException::withMessages(['status' => 'Chỉ xoá được mùa đang ở trạng thái chuẩn bị và chưa là mùa hiện tại.']);
        }

        try {
            DB::transaction(fn () => $cycle->forceDelete());
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                throw ValidationException::withMessages(['status' => 'Mùa này đã có dữ liệu liên quan (ngành, chiến dịch, hồ sơ…) nên không thể xoá.']);
            }

            throw $e;
        }
    }
}
