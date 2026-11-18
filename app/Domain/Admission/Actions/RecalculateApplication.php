<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Services\ScoreCalculator;
use Illuminate\Validation\ValidationException;

/**
 * Re-scores a nguyện vọng after the applicant's scores, certificates or priority changed, and lets the registrar
 * enter the encouragement bonus. Not in a closed cycle (R8) and not once the application is withdrawn.
 */
class RecalculateApplication
{
    public function __construct(private readonly ScoreCalculator $calculator) {}

    public function handle(Application $application, ?float $encourageBonus = null, bool $setEncourage = false): Application
    {
        $application->loadMissing('case.cycle');

        if ($application->case->cycle->isClosed()) {
            throw ValidationException::withMessages(['encourage_bonus' => 'Mùa tuyển sinh đã đóng, không thể tính lại điểm.']);
        }

        if ($application->status === ApplicationStatus::Withdrawn) {
            throw ValidationException::withMessages(['encourage_bonus' => 'Nguyện vọng đã rút.']);
        }

        if ($setEncourage) {
            $application->encourage_bonus = $encourageBonus;
        }

        $application->unsetRelations();
        $application->forceFill($this->calculator->calculate($application)->toAttributes())->save();

        return $application;
    }
}
