<?php

namespace App\Domain\Workflow\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** workflow.view lists workflows and their runs; workflow.manage creates, edits, switches and deletes them. */
class WorkflowPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'workflow';
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
}
