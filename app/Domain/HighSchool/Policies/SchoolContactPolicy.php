<?php

namespace App\Domain\HighSchool\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

/**
 * school_contact.* permissions. A contact is reachable only through its school, which the controller
 * loads through HighSchool's data scope (outside the scope = 404).
 */
class SchoolContactPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'school_contact';
    }
}
