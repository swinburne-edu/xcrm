<?php

namespace App\Http\Requests\Cycle;

use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', AdmissionCycle::class);
        }

        $cycle = AdmissionCycle::query()->find($this->route('id'));

        return $cycle !== null && (bool) $user?->can('update', $cycle);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'regulation_ref'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:2000,2200', Rule::unique('admission_cycles', 'year')
                ->where('organization_id', $organizationId)
                ->where('level', $this->input('level'))
                ->ignore($this->route('id'))],
            'level' => ['required', Rule::enum(CycleLevel::class)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::enum(CycleStatus::class)],
            'target_enrolled' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'regulation_ref' => ['nullable', 'string', 'max:255'],
            'is_current' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.unique' => 'Đã có mùa tuyển sinh của năm này ở cùng bậc đào tạo.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên mùa',
            'year' => 'năm',
            'level' => 'bậc đào tạo',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'status' => 'trạng thái',
            'target_enrolled' => 'chỉ tiêu nhập học',
            'regulation_ref' => 'văn bản quy chế',
        ];
    }
}
