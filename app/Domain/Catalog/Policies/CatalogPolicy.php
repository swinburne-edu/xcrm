<?php

namespace App\Domain\Catalog\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * System catalogs (provinces, subjects, combinations…): one policy for all catalog models.
 * catalog.view → open the screen; catalog.manage → create / edit / delete.
 */
class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view') || $user->can('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('catalog.manage');
    }
}
