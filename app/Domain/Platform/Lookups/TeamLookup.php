<?php

namespace App\Domain\Platform\Lookups;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Teams of the current organization (OrganizationScope applies).
 */
class TeamLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Team::query()->with('leader:id,name');
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    /**
     * @param  Team  $model
     */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => $model->name,
            'description' => $model->leader ? 'Trưởng nhóm: '.$model->leader->name : null,
        ];
    }
}
