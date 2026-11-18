<?php

namespace App\Domain\Campaign\Lookups;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaigns of the organization (EntityPicker entity="campaigns"); ?cycle_id= narrows to one cycle.
 */
class CampaignLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Campaign::query()
            ->when(filled($params['cycle_id'] ?? null), fn (Builder $q) => $q->where('cycle_id', (int) $params['cycle_id']));
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Campaign  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => $model->name, 'description' => $model->code];
    }
}
