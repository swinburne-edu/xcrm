<?php

namespace App\Domain\Major\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

/**
 * major.* permissions. The per-cycle setup of a major additionally needs the cycle to be open (R8),
 * checked by MajorController through CyclePolicy.
 */
class MajorPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'major';
    }
}
