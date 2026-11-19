<?php

namespace App\Domain\Communication\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** message_template.view lists templates; message_template.manage creates, edits and deletes them. */
class MessageTemplatePolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'message_template';
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
