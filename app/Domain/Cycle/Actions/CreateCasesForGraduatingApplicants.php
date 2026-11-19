<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Validation\ValidationException;

/**
 * Flow F5 (docs/05-luong-nghiep-vu.md): in an active cycle, every applicant whose graduation_year equals the cycle's
 * year and who has no case yet gets one, owned by the territory / assignment rules or left in the shared pool
 * (CreateAdmissionCase). Applicants who already have a case (even a deleted one), merged or deleted applicants and
 * cycles that are not active are skipped, so running it again only picks up newcomers.
 */
class CreateCasesForGraduatingApplicants
{
    public function __construct(private readonly CreateAdmissionCase $createCase, private readonly CurrentOrganization $current) {}

    /** @return array{created: int, failed: int} */
    public function handle(AdmissionCycle $cycle): array
    {
        $result = ['created' => 0, 'failed' => 0];

        if ($cycle->status !== CycleStatus::Active) {
            return $result;
        }

        $this->current->run($cycle->organization_id, function () use ($cycle, &$result): void {
            Applicant::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $cycle->organization_id)
                ->whereNull('deleted_at')
                ->whereNull('merged_into_id')
                ->where('graduation_year', $cycle->year)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('admission_cases')
                    ->whereColumn('admission_cases.applicant_id', 'applicants.id')
                    ->where('admission_cases.cycle_id', $cycle->id))
                ->orderBy('id')
                ->chunkById(200, function ($applicants) use ($cycle, &$result): void {
                    foreach ($applicants as $applicant) {
                        try {
                            $this->createCase->handle($applicant, $cycle, ['payload' => ['origin' => 'graduating_applicants']]);
                            $result['created']++;
                        } catch (ValidationException) {
                            // no default pipeline, closed cycle…: leave it for the next run
                            $result['failed']++;
                        }
                    }
                });
        });

        return $result;
    }
}
