<?php

namespace App\Http\Requests\HighSchool;

use App\Domain\HighSchool\Models\HighSchool;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /high-schools/import: an .xlsx/.xls/.csv file. dry_run = check only, write nothing.
 */
class HighSchoolImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('import', HighSchool::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'dry_run' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'tệp'];
    }
}
