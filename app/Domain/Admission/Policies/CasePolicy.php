<?php

namespace App\Domain\Admission\Policies;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * case.* permissions plus the data scope of AdmissionCase, and rule R8: a case of a closed cycle is read-only.
 * Giving a case to someone needs case.assign; anyone who may update a case in the pool can claim it.
 */
class CasePolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'case';
    }

    /** @param  AdmissionCase  $model */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $this->cycleClosed($model);
    }

    /** @param  AdmissionCase  $model */
    public function assign(User $user, Model $model): bool
    {
        return parent::assign($user, $model) && ! $this->cycleClosed($model);
    }

    /** "Nhận" a case from the shared pool. */
    public function claim(User $user, AdmissionCase $case): bool
    {
        return $case->inPool() && $this->update($user, $case);
    }

    private function cycleClosed(AdmissionCase $case): bool
    {
        return (bool) $case->cycle?->isClosed();
    }
}
