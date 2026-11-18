<?php

namespace App\Domain\Platform\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * assignment_rule.view lists the rules and the SLA settings; assignment_rule.manage creates, edits and deletes them.
 */
class AssignmentRulePolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'assignment_rule';
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage');
    }

    /** The organization-wide SLA / recall switches. */
    public function manage(User $user): bool
    {
        return $this->allows($user, 'manage');
    }
}
