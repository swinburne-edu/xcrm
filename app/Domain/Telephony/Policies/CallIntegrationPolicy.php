<?php

namespace App\Domain\Telephony\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** call_integration.view lists the webhook endpoints; call_integration.manage creates, edits and deletes them. */
class CallIntegrationPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'call_integration';
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
