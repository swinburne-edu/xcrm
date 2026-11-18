<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\Scopes\DataScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Restricts a model to the rows the logged-in user may see (see DataScope).
 * Override the dataScope* hooks when the model's columns differ:
 *
 *   applicants      owner = referred_by_user_id (ambassadors see who they referred)
 *   admission_cases owner_id + team_id, includes the shared pool (owner_id null)
 *   high_schools    owner_id, territory by province / school (task 1.4)
 *
 * @mixin Model
 */
trait HasDataScope
{
    public static function bootHasDataScope(): void
    {
        static::addGlobalScope(new DataScope);
    }

    public function dataScopeOwnerColumn(): string
    {
        return 'owner_id';
    }

    /**
     * Extra columns that also make a row "mine" (e.g. created_by for applicants, so whoever
     * entered a candidate keeps seeing it). Empty by default.
     *
     * @return list<string>
     */
    public function dataScopeAlsoOwnedBy(): array
    {
        return [];
    }

    /** Column with the owning team, or null when the model has none. */
    public function dataScopeTeamColumn(): ?string
    {
        return 'team_id';
    }

    /** Rows without an owner form a shared pool visible to the teams they belong to. */
    public function dataScopeIncludesPool(): bool
    {
        return false;
    }

    /**
     * Rows of the user's territory (provinces / schools assigned to the user or team).
     * Territories arrive in task 1.4; until a model overrides this, territory scope
     * shows nothing beyond the user's own rows (fail closed).
     *
     * @param  Builder<Model>  $query
     */
    public function applyDataScopeTerritory(Builder $query, User $user): void
    {
        $query->whereRaw('1 = 0');
    }

    /**
     * Records visible to $user, regardless of who is logged in (policies, jobs, tests).
     *
     * @param  Builder<Model>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->withoutGlobalScope(DataScope::class);
        DataScope::constrain($query, $this, $user);

        return $query;
    }

    /**
     * Query without the data scope (system jobs, merges). Organization scope still applies.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWithoutDataScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(DataScope::class);
    }
}
