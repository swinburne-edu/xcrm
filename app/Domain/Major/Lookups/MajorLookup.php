<?php

namespace App\Domain\Major\Lookups;

use App\Domain\Major\Models\Major;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Active majors of the organization (EntityPicker entity="majors": interested major, preference…).
 */
class MajorLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Major::query()->where('is_active', true);
    }

    protected function searchColumns(): array
    {
        return ['name', 'admission_code'];
    }

    /** @param  Major  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => $model->name, 'description' => $model->admission_code];
    }
}
