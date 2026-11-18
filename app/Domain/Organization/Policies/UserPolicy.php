<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'user';
    }

    /** Users cannot be deleted by themselves (would lock the organization out). */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->is($model);
    }
}
