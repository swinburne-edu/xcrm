<?php

namespace App\Domain\Major\Actions;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use Illuminate\Support\Facades\DB;

/**
 * Sets up one major for one cycle: quota, accepted subject combinations and admission methods
 * (the "Theo mùa" tab). Replaces the previous setup of that cycle only; other cycles are untouched.
 * Validation (R11, methods belong to the cycle) lives in MajorCycleSetupRequest.
 */
class SaveMajorCycleSetup
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Major $major, AdmissionCycle $cycle, array $data): MajorQuota
    {
        return DB::transaction(function () use ($major, $cycle, $data): MajorQuota {
            $quota = MajorQuota::query()->firstOrNew(['major_id' => $major->id, 'cycle_id' => $cycle->id]);
            $quota->fill([
                'organization_id' => $major->organization_id,
                'quota_total' => $data['quota_total'],
                'quota_direct' => $data['quota_direct'] ?? 0,
                'target_enrolled' => $data['target_enrolled'] ?? null,
                'tuition_fee' => $data['tuition_fee'] ?? null,
                'min_threshold' => $data['min_threshold'] ?? null,
                'previous_cutoffs' => $data['previous_cutoffs'] ?? null,
                'scholarship_info' => $data['scholarship_info'] ?? null,
            ])->save();

            DB::table('major_combinations')->where('major_id', $major->id)->where('cycle_id', $cycle->id)->delete();
            DB::table('major_combinations')->insert(
                collect($data['combination_ids'] ?? [])->unique()->map(fn ($id) => [
                    'major_id' => $major->id, 'combination_id' => (int) $id, 'cycle_id' => $cycle->id,
                ])->values()->all(),
            );

            // major_cycle_methods has no cycle column: the cycle comes from the cycle method.
            $cycleMethodIds = CycleMethod::query()->where('cycle_id', $cycle->id)->pluck('id');
            DB::table('major_cycle_methods')->where('major_id', $major->id)->whereIn('cycle_method_id', $cycleMethodIds)->delete();
            DB::table('major_cycle_methods')->insert(
                collect($data['cycle_method_ids'] ?? [])->unique()->map(fn ($id) => [
                    'major_id' => $major->id, 'cycle_method_id' => (int) $id,
                ])->values()->all(),
            );

            return $quota;
        });
    }
}
