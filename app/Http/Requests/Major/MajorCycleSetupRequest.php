<?php

namespace App\Http\Requests\Major;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\Major;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /majors/{id}/cycles/{cycleId}: quota, combinations and methods of a major in one cycle.
 * Needs major.update and an open cycle (R8: a closed cycle is read-only). R11: direct ≤ total.
 */
class MajorCycleSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $major = Major::query()->find($this->route('id'));
        $cycle = AdmissionCycle::query()->find($this->route('cycleId'));

        return $major !== null && $cycle !== null
            && (bool) $user?->can('update', $major) && (bool) $user?->can('update', $cycle);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cycleId = (int) $this->route('cycleId');

        return [
            'quota_total' => ['required', 'integer', 'min:0', 'max:1000000'],
            'quota_direct' => ['nullable', 'integer', 'min:0', 'lte:quota_total'],
            'target_enrolled' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tuition_fee' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'min_threshold' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'previous_cutoffs' => ['nullable', 'array', 'max:20'],
            'previous_cutoffs.*.year' => ['required', 'integer', 'between:2000,2200'],
            'previous_cutoffs.*.method' => ['nullable', 'string', 'max:50'],
            'previous_cutoffs.*.score' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'scholarship_info' => ['nullable', 'string', 'max:5000'],
            'combination_ids' => ['nullable', 'array'],
            'combination_ids.*' => ['integer', Rule::exists('subject_combinations', 'id')],
            'cycle_method_ids' => ['nullable', 'array'],
            'cycle_method_ids.*' => ['integer', Rule::exists('cycle_methods', 'id')->where('cycle_id', $cycleId)],
        ];
    }

    public function messages(): array
    {
        return [
            'quota_direct.lte' => 'Chỉ tiêu xét tuyển thẳng không được lớn hơn tổng chỉ tiêu.',
            'cycle_method_ids.*.exists' => 'Phương thức không thuộc mùa tuyển sinh này.',
        ];
    }

    public function attributes(): array
    {
        return [
            'quota_total' => 'tổng chỉ tiêu', 'quota_direct' => 'chỉ tiêu xét tuyển thẳng', 'target_enrolled' => 'mục tiêu nhập học',
            'tuition_fee' => 'học phí', 'min_threshold' => 'điểm sàn',
        ];
    }
}
