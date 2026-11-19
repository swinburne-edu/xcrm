<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Jobs\CreateCasesForGraduatingApplicantsJob;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a cycle. Status and "current" changes go through here so is_current stays
 * consistent: a closed cycle is never current, a new cycle is not current until SetCurrentCycle.
 */
class SaveCycle
{
    public function __construct(private readonly SetCurrentCycle $setCurrent) {}

    /**
     * @param  array<string, mixed>  $data  validated by CycleRequest
     */
    public function handle(?AdmissionCycle $cycle, array $data): AdmissionCycle
    {
        return DB::transaction(function () use ($cycle, $data): AdmissionCycle {
            $cycle ??= new AdmissionCycle;

            $cycle->fill([
                'name' => $data['name'],
                'year' => $data['year'],
                'level' => $data['level'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'target_enrolled' => $data['target_enrolled'] ?? null,
                'regulation_ref' => $data['regulation_ref'] ?? null,
                'status' => $data['status'] ?? $cycle->status,
            ]);

            // Closing a cycle takes it out of the "current" slot.
            if ($cycle->status === CycleStatus::Closed) {
                $cycle->is_current = false;
            }

            $cycle->save();

            if ($cycle->status === CycleStatus::Active && ($cycle->wasRecentlyCreated || $cycle->wasChanged('status'))) {
                CreateCasesForGraduatingApplicantsJob::dispatch($cycle->id)->afterCommit();
            }

            if (! empty($data['is_current']) && ! $cycle->is_current) {
                $this->setCurrent->handle($cycle);
            }

            return $cycle->refresh();
        });
    }
}
