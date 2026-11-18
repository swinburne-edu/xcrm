<?php

namespace App\Domain\Activity\Policies;

use App\Domain\Activity\Models\Appointment;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * appointment.* permissions; an appointment is visible to whoever may see its case and changeable by
 * whoever may update that case (so the case's data scope and the closed-cycle rule apply).
 */
class AppointmentPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'appointment';
    }

    /** @param  Appointment  $model */
    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view') && $this->caseAllows($user, $model, 'view');
    }

    /** @param  Appointment  $model */
    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update') && $this->caseAllows($user, $model, 'update');
    }

    /** @param  Appointment  $model */
    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete') && $this->caseAllows($user, $model, 'update');
    }

    private function caseAllows(User $user, Appointment $appointment, string $ability): bool
    {
        $case = $appointment->case()->first();

        return $case !== null && $user->can($ability, $case);
    }
}
