<?php

namespace App\Domain\Intake\Policies;

use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * lead_integration.view lists the lead ads integrations; lead_integration.manage creates, edits and deletes them
 * (it also reveals the webhook verify token).
 */
class LeadIntegrationPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'lead_integration';
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
