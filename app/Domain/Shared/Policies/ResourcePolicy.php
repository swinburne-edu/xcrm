<?php

namespace App\Domain\Shared\Policies;

use App\Domain\Shared\Concerns\HasDataScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy (CLAUDE.md §5 rule 3): an action is allowed when the user has the permission
 * `{object}.{action}` AND, for models with a data scope, the record is inside that scope.
 *
 *   class ApplicantPolicy extends ResourcePolicy
 *   {
 *       protected function object(): string { return 'applicant'; }
 *   }
 *   Gate::policy(Applicant::class, ApplicantPolicy::class);   // AppServiceProvider::configurePolicies
 *
 * Extra rules (closed cycle is read-only — R8, no hard delete once applications exist — R9)
 * are added by overriding the method and calling parent::update() first.
 */
abstract class ResourcePolicy
{
    /** Permission object, e.g. "applicant" for applicant.view / applicant.update… */
    abstract protected function object(): string;

    protected function allows(User $user, string $action): bool
    {
        return $user->can($this->object().'.'.$action);
    }

    /** The record is within the user's data scope (always true for models without one). */
    protected function visible(User $user, Model $model): bool
    {
        if (! in_array(HasDataScope::class, class_uses_recursive($model), true)) {
            return true;
        }

        return $model->newQuery()->visibleTo($user)->whereKey($model->getKey())->exists();
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view') && $this->visible($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update') && $this->visible($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete') && $this->visible($user, $model);
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    public function import(User $user): bool
    {
        return $this->allows($user, 'import');
    }

    public function assign(User $user, Model $model): bool
    {
        return $this->allows($user, 'assign') && $this->visible($user, $model);
    }

    public function merge(User $user): bool
    {
        return $this->allows($user, 'merge');
    }
}
