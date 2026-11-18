<?php

namespace App\Domain\Cycle\Policies;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * cycle.* permissions plus rule R8: a closed cycle (and everything attached to it: rounds,
 * methods, later quotas…) is read-only. Reopening is the one allowed change (see CycleController::reopen).
 */
class CyclePolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'cycle';
    }

    /** @param  AdmissionCycle  $model */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $model->isClosed();
    }

    /** Reopening a closed cycle needs the normal update permission. */
    public function reopen(User $user, AdmissionCycle $cycle): bool
    {
        return $cycle->isClosed() && parent::update($user, $cycle);
    }
}
