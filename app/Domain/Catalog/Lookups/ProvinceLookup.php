<?php

namespace App\Domain\Catalog\Lookups;

use App\Domain\Catalog\Models\Province;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Active provinces for <EntityPicker entity="provinces"> / <ProvinceWardSelect>.
 */
class ProvinceLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Province::query()->where('is_active', true);
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Province  $model */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => $model->name,
            'description' => $model->type?->label(),
        ];
    }
}
