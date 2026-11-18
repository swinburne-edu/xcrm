<?php

namespace App\Domain\HighSchool\Policies;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * school_activity.* permissions plus the data scope of SchoolActivity (creator, staff, territory of the school),
 * and rule R8: an activity of a closed cycle is read-only.
 */
class SchoolActivityPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'school_activity';
    }

    /** @param  SchoolActivity  $model */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $this->cycleClosed($model);
    }

    /** @param  SchoolActivity  $model */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $this->cycleClosed($model);
    }

    private function cycleClosed(SchoolActivity $activity): bool
    {
        return AdmissionCycle::query()->whereKey($activity->cycle_id)->where('status', 'closed')->exists();
    }
}
