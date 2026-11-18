<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

class TeamPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'team';
    }
}
