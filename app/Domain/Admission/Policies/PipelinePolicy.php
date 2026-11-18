<?php

namespace App\Domain\Admission\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pipelines and their stages: pipeline.view opens the screen, pipeline.manage changes anything.
 */
class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pipeline.view') || $user->can('pipeline.manage');
    }

    public function view(User $user, Model $pipeline): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('pipeline.manage');
    }

    public function update(User $user, Model $pipeline): bool
    {
        return $user->can('pipeline.manage');
    }

    public function delete(User $user, Model $pipeline): bool
    {
        return $user->can('pipeline.manage');
    }
}
