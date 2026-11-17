<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Scopes\OrganizationScope;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every business table with an organization_id column:
 * - global scope limiting queries to the current organization;
 * - fills organization_id on create when it is not set.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('organization_id'))) {
                return;
            }

            $organizationId = app(CurrentOrganization::class)->id();

            if ($organizationId !== null) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query across all organizations (system jobs, super-admin tools only).
     */
    public function scopeAcrossOrganizations(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganizationScope::class);
    }
}
