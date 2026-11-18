<?php

namespace App\Domain\HighSchool\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

/**
 * high_school.* permissions plus the data scope of HighSchool (own / team / territory / all).
 */
class HighSchoolPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'high_school';
    }
}
