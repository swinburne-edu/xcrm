<?php

namespace App\Domain\Applicant\Lookups;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applicants the user may see (data scope applies), by name, code, e-mail or phone digits.
 * The phone itself is never returned.
 */
class ApplicantLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return Applicant::query()->whereNull('merged_into_id');
    }

    protected function searchColumns(): array
    {
        return ['full_name', 'code', 'email', 'phone_normalized'];
    }

    /** @param  Applicant  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => $model->full_name, 'description' => $model->code];
    }
}
