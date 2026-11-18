<?php

namespace App\Domain\Catalog\Lookups;

use App\Domain\Catalog\Models\Ward;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Active wards; pass ?province_id= to limit to one province (ProvinceWardSelect does).
 */
class WardLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Ward::query()
            ->with('province:id,name')
            ->where('is_active', true)
            ->when(
                filled($params['province_id'] ?? null),
                fn (Builder $q) => $q->where('province_id', (int) $params['province_id']),
            );
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Ward  $model */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => trim(($model->type?->label() ?? '').' '.$model->name),
            'description' => $model->province?->name,
        ];
    }
}
