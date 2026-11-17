<?php

namespace App\Domain\Shared\Scopes;

use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries to the current organization.
 *
 * - No tenant context (console, jobs, seeders): no filter.
 * - Logged-in user without an organization: sees nothing (fail closed).
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentOrganization::class);

        if (! $current->hasContext()) {
            return;
        }

        $organizationId = $current->id();

        if ($organizationId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
