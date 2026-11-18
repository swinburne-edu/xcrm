<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Finds who is responsible for a province or a high school (flow F3: new leads are assigned by territory).
 *
 *   high school → its own assignment first, else the assignment of its province
 *   province    → its assignment
 */
class TerritoryResolver
{
    public function __construct(private readonly CurrentOrganization $current) {}

    /**
     * @param  Model  $target  a Province or a HighSchool (any model with the morph alias "high_school" and a province_id)
     * @param  int|null  $organizationId  defaults to the target's organization, then the current one
     */
    public function ownerFor(Model $target, ?int $organizationId = null): ?TerritoryOwner
    {
        $type = $target->getMorphClass();

        if (! in_array($type, ['province', 'high_school'], true)) {
            throw new InvalidArgumentException("Cannot resolve a territory for [{$type}].");
        }

        $organizationId ??= $target->getAttribute('organization_id') ?? $this->current->id();

        if ($organizationId === null) {
            return null;
        }

        $territory = $this->territoryOf($type, (int) $target->getKey(), $organizationId);

        if ($territory === null && $type === 'high_school' && $target->getAttribute('province_id') !== null) {
            $territory = $this->territoryOf('province', (int) $target->getAttribute('province_id'), $organizationId);
        }

        return $territory === null ? null : new TerritoryOwner($territory, $territory->user_id, $territory->team_id);
    }

    private function territoryOf(string $type, int $id, int $organizationId): ?Territory
    {
        $assignment = TerritoryAssignment::query()
            ->where('assignable_type', $type)
            ->where('assignable_id', $id)
            ->whereHas('territory', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('deleted_at'))
            ->first();

        return $assignment === null
            ? null
            : Territory::acrossOrganizations()->find($assignment->territory_id);
    }
}
