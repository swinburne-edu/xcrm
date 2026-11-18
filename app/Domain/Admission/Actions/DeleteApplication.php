<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\Application;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a draft nguyện vọng. Anything already submitted is withdrawn (TransitionApplication), never deleted (R9).
 */
class DeleteApplication
{
    public function handle(Application $application): void
    {
        $application->loadMissing('case.cycle');

        if ($application->case->cycle->isClosed()) {
            throw ValidationException::withMessages(['application' => 'Mùa tuyển sinh đã đóng, không thể xoá nguyện vọng.']);
        }

        if ($application->status !== ApplicationStatus::Draft) {
            throw ValidationException::withMessages(['application' => 'Chỉ xoá được nguyện vọng nháp; nguyện vọng đã nộp hãy chọn "Rút".']);
        }

        $application->delete();
    }
}
