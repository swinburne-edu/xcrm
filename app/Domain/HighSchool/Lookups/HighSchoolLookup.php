<?php

namespace App\Domain\HighSchool\Lookups;

use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * High schools the current user may see (data scope applies); ?province_id= narrows to a province.
 * Used by EntityPicker entity="high_schools" (applicant form, territories, filters).
 */
class HighSchoolLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return HighSchool::query()
            ->with('province:id,name')
            ->when(filled($params['province_id'] ?? null), fn (Builder $q) => $q->where('province_id', (int) $params['province_id']));
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  HighSchool  $model */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => $model->name,
            'description' => trim(($model->code ? $model->code.' · ' : '').($model->province?->name ?? '')) ?: null,
        ];
    }
}
