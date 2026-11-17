<?php

namespace App\Domain\Platform\Lookups;

use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Active users of the current organization (owner / assignee pickers).
 * Users have no OrganizationScope, so the organization filter is explicit here.
 */
class UserLookup extends EloquentLookup
{
    public function __construct(private readonly CurrentOrganization $organization) {}

    protected function query(array $params): Builder
    {
        $organizationId = $this->organization->id();

        return User::query()
            ->where('is_active', true)
            ->when(
                $organizationId === null,
                fn (Builder $q) => $q->whereRaw('1 = 0'),
                fn (Builder $q) => $q->where('organization_id', $organizationId),
            );
    }

    protected function searchColumns(): array
    {
        return ['name', 'email', 'phone'];
    }

    /**
     * @param  User  $model
     */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => $model->name,
            'description' => $model->email,
        ];
    }
}
