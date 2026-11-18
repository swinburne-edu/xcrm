<?php

namespace App\Http\Requests\Cycle;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /settings/cycles/{id}/clone: name and year of the new cycle. The level is the source's,
 * so (organization, year, level) must be free.
 */
class CloneCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = AdmissionCycle::query()->find($this->route('id'));

        return $source !== null && (bool) $this->user()?->can('create', AdmissionCycle::class) && (bool) $this->user()->can('view', $source);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $source = AdmissionCycle::query()->find($this->route('id'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:2000,2200', Rule::unique('admission_cycles', 'year')
                ->where('organization_id', app(CurrentOrganization::class)->id())
                ->where('level', $source?->level->value)],
        ];
    }

    public function messages(): array
    {
        return ['year.unique' => 'Đã có mùa tuyển sinh năm này cho cùng bậc đào tạo.'];
    }

    public function attributes(): array
    {
        return ['name' => 'tên mùa', 'year' => 'năm'];
    }
}
