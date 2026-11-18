<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * territory.view lists territories; territory.manage creates, edits and deletes them.
 */
class TerritoryPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'territory';
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
