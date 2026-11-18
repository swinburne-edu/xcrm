<?php

namespace App\Domain\Applicant\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

/**
 * applicant.* permissions plus the data scope of Applicant. Applicants are never hard-deleted
 * through the UI (R9): delete is a soft delete.
 */
class ApplicantPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'applicant';
    }

    /** R9: an applicant is never removed for good, so the history of their nguyện vọng stays. */
    public function forceDelete(): bool
    {
        return false;
    }
}
