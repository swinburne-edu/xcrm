<?php

namespace App\Domain\Shared\Scopes;

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Row-level data scope (docs/02-phan-quyen.md): what the logged-in user may see of a model
 * that uses HasDataScope.
 *
 *   all        everything (of the organization)
 *   own        records I own (+ the shared pool of my teams when the model has one)
 *   team       own + records of the teams I belong to
 *   territory  own + records in my territory (model hook; resolved by task 1.4)
 *
 * No logged-in user (console, jobs, seeders) = no restriction, like OrganizationScope.
 */
class DataScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            self::constrain($builder, $model, $user);
        }
    }

    /**
     * Apply the scope of $user to $builder (used by the global scope and by visibleTo()).
     */
    public static function constrain(Builder $builder, Model $model, User $user): void
    {
        $level = $user->data_scope;

        if ($level === DataScopeLevel::All) {
            return;
        }

        $owner = $model->qualifyColumn($model->dataScopeOwnerColumn());
        $teamColumn = $model->dataScopeTeamColumn();
        $team = $teamColumn === null ? null : $model->qualifyColumn($teamColumn);
        $myTeams = DB::table('team_user')->select('team_id')->where('user_id', $user->id);

        $builder->where(function (Builder $q) use ($level, $model, $user, $owner, $team, $myTeams): void {
            $q->where($owner, $user->id);

            // Shared pool (no owner) of the teams I belong to.
            if ($model->dataScopeIncludesPool()) {
                $q->orWhere(function (Builder $pool) use ($owner, $team, $myTeams): void {
                    $pool->whereNull($owner);

                    if ($team !== null) {
                        $pool->whereIn($team, $myTeams);
                    }
                });
            }

            if ($level === DataScopeLevel::Team && $team !== null) {
                $q->orWhereIn($team, $myTeams);
            }

            if ($level === DataScopeLevel::Territory) {
                $q->orWhere(fn (Builder $territory) => $model->applyDataScopeTerritory($territory, $user));
            }
        });
    }
}
