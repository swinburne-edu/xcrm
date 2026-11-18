<?php

namespace App\Domain\Organization\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a user (their records keep pointing at them for the timeline / audit).
 */
class DeleteUser
{
    public function __construct(private readonly SaveUser $users) {}

    public function handle(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $this->users->guardLastAdmin($user, stays: false);

            $user->teams()->detach();
            $user->delete();

            activity()->causedBy($actor)->performedOn($user)->log('Xoá người dùng');
        });
    }
}
