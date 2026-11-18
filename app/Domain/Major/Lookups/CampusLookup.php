<?php

namespace App\Domain\Major\Lookups;

use App\Domain\Major\Models\Campus;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campuses of the organization (EntityPicker entity="campuses").
 */
class CampusLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Campus::query();
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Campus  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => $model->name, 'description' => $model->code];
    }
}
