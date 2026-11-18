<?php

namespace App\Domain\Admission\Lookups;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Platform\Lookups\EloquentLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cases the user may see, found by the applicant's name or code (EntityPicker entity="cases").
 * ?cycle_id= narrows to one cycle.
 */
class CaseLookup extends EloquentLookup
{
    protected function query(array $params): Builder
    {
        return AdmissionCase::query()
            ->with('applicant:id,full_name,code')
            ->join('applicants', 'applicants.id', '=', 'admission_cases.applicant_id')
            ->select('admission_cases.*')
            ->when(filled($params['cycle_id'] ?? null), fn (Builder $q) => $q->where('admission_cases.cycle_id', (int) $params['cycle_id']));
    }

    protected function searchColumns(): array
    {
        return ['applicants.full_name', 'applicants.code'];
    }

    /** @return list<string> */
    protected function orderColumns(): array
    {
        return ['applicants.full_name'];
    }

    /** @param  AdmissionCase  $model */
    protected function toItem(Model $model): array
    {
        return ['id' => $model->id, 'label' => (string) $model->applicant?->full_name, 'description' => $model->applicant?->code];
    }
}
