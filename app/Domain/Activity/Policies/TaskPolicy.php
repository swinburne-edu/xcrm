<?php

namespace App\Domain\Activity\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;

/**
 * task.* permissions plus Task's data scope (a user works on the tasks assigned to them).
 */
class TaskPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'task';
    }
}
