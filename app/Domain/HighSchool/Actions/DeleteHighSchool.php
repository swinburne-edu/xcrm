<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\HighSchool\Models\HighSchool;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a school for good (so its code is free again), together with its contacts and its place in
 * a territory. Schools that other records point at (applicants, activities…: foreign keys) are refused;
 * set their partnership status back to "not contacted" instead.
 */
class DeleteHighSchool
{
    public function handle(HighSchool $school): void
    {
        try {
            DB::transaction(function () use ($school): void {
                DB::table('territory_assignments')->where('assignable_type', 'high_school')->where('assignable_id', $school->id)->delete();
                $school->forceDelete();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                throw ValidationException::withMessages(['school' => 'Trường đã có dữ liệu liên quan (thí sinh, hoạt động…) nên không xoá được.']);
            }

            throw $e;
        }
    }
}
