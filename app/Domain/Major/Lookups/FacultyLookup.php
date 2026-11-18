<?php

namespace App\Domain\Major\Lookups;

use App\Domain\Major\Models\Faculty;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Faculties of the organization (EntityPicker entity="faculties").
 */
class FacultyLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Faculty::query();
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Faculty  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => $model->name, 'description' => $model->code];
    }
}
