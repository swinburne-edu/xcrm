<?php

namespace App\Domain\Admission\Policies;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * application.* permissions. A nguyện vọng is as visible as its case (data scope), is read-only in a closed cycle (R8)
 * and is only deleted while it is a draft (R9). Decisions (eligible / admit / reject…) need application.review.
 */
class ApplicationPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'application';
    }

    protected function visible(User $user, Model $model): bool
    {
        return AdmissionCase::query()->visibleTo($user)->whereKey($model->case_id)->exists();
    }

    /** @param  Application  $model */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $this->cycleClosed($model);
    }

    /** @param  Application  $model */
    public function delete(User $user, Model $model): bool
    {
        // Whoever may edit a nguyện vọng may drop their own draft; nothing else is ever deleted (R9).
        return ($this->allows($user, 'delete') || $this->allows($user, 'update'))
            && $this->visible($user, $model) && ! $this->cycleClosed($model) && $model->status === ApplicationStatus::Draft;
    }

    /** @param  Application  $model */
    public function review(User $user, Model $model): bool
    {
        return $this->allows($user, 'review') && $this->visible($user, $model) && ! $this->cycleClosed($model);
    }

    private function cycleClosed(Application $application): bool
    {
        return (bool) $application->case()->withoutGlobalScopes()->first()?->cycle?->isClosed();
    }
}
