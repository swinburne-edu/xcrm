<?php

namespace App\Domain\Cycle\Jobs;

use App\Domain\Cycle\Actions\CreateCasesForGraduatingApplicants;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * F5: opens cases for applicants graduating in the cycle's year. Dispatched when a cycle becomes active
 * (with its id) and every day by crm:create-graduating-cases (without an id: all active cycles, to pick up
 * applicants added since).
 */
class CreateCasesForGraduatingApplicantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?int $cycleId = null) {}

    public function handle(CreateCasesForGraduatingApplicants $action): void
    {
        $cycles = AdmissionCycle::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('status', CycleStatus::Active->value);

        if ($this->cycleId !== null) {
            $cycles->whereKey($this->cycleId);
        }

        foreach ($cycles->get() as $cycle) {
            $action->handle($cycle);
        }
    }
}
