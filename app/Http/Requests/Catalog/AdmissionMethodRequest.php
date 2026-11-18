<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\ScoreSource;
use Illuminate\Validation\Rule;

class AdmissionMethodRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueCode('admission_methods')],
            'name' => ['required', 'string', 'max:255'],
            'score_source' => ['nullable', Rule::enum(ScoreSource::class)],
        ];
    }
}
