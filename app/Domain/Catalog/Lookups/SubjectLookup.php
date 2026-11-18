<?php

namespace App\Domain\Catalog\Lookups;

use App\Domain\Catalog\Models\Subject;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Subjects for combination forms and score entry; core subjects (Toán, Ngữ văn) first.
 */
class SubjectLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Subject::query()->orderByDesc('is_core');
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    /** @param  Subject  $model */
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'label' => (string) $model->name,
            'description' => $model->code.($model->is_core ? ' · cốt lõi' : ''),
        ];
    }
}
