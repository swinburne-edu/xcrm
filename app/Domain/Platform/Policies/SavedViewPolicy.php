<?php

namespace App\Domain\Platform\Policies;

use App\Domain\Platform\Models\SavedView;
use App\Models\User;

/**
 * Anyone who can open a list screen keeps personal views there. Sharing a view with the organization, and
 * editing or deleting a shared view, needs saved_view.manage. A personal view is only the owner's.
 */
class SavedViewPolicy
{
    public function create(User $user, bool $shared = false): bool
    {
        return ! $shared || $user->can('saved_view.manage');
    }

    public function update(User $user, SavedView $view): bool
    {
        return $view->is_shared ? $user->can('saved_view.manage') : $view->user_id === $user->id;
    }

    public function delete(User $user, SavedView $view): bool
    {
        return $this->update($user, $view);
    }
}
