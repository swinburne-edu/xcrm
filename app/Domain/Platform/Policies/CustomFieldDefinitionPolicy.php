<?php

namespace App\Domain\Platform\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * custom_field.view lists the definitions; custom_field.manage creates, edits and deletes them.
 * Editing the VALUES of a field is governed by the record's own policy (update).
 */
class CustomFieldDefinitionPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'custom_field';
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
