<?php

namespace App\Domain\Api\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** webhook.view lists webhooks and their deliveries; webhook.manage creates, edits, renews the secret, deletes and re-sends. */
class WebhookPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'webhook';
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
