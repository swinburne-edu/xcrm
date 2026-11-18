<?php

namespace App\Domain\Major\Actions;

use App\Domain\Major\Models\Major;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a major that was never used. Once it has a quota, combinations or methods in any cycle
 * (and later applications) it is deactivated instead, so history keeps pointing at it
 * (CLAUDE.md §6: no hard delete of data that applications refer to). The delete is permanent so the
 * admission code is free again.
 */
class DeleteMajor
{
    public function handle(Major $major): void
    {
        if ($major->hasCycleConfiguration()) {
            throw ValidationException::withMessages(['major' => 'Ngành đã được cấu hình cho mùa tuyển sinh nên không xoá được. Hãy chuyển sang "Ngừng sử dụng".']);
        }

        try {
            DB::transaction(fn () => $major->forceDelete());
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                throw ValidationException::withMessages(['major' => 'Ngành đã có dữ liệu liên quan (hồ sơ, nguyện vọng…) nên không xoá được. Hãy chuyển sang "Ngừng sử dụng".']);
            }

            throw $e;
        }
    }
}
