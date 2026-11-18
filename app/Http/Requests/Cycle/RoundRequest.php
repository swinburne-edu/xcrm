<?php

namespace App\Http\Requests\Cycle;

use App\Domain\Cycle\Enums\RoundType;
use App\Domain\Shared\Support\DisplayTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization (cycle.update and the cycle not being closed, R8) is done by the controller via CyclePolicy.
 */
class RoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('cycleModel'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $dateTime = ['nullable', 'date_format:Y-m-d\TH:i'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(RoundType::class)],
            'apply_start' => $dateTime,
            'apply_end' => $dateTime,
            'result_date' => ['nullable', 'date_format:Y-m-d'],
            'confirm_deadline' => $dateTime,
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = DisplayTime::toUtc($this->input('apply_start'));
            $end = DisplayTime::toUtc($this->input('apply_end'));
            $deadline = DisplayTime::toUtc($this->input('confirm_deadline'));

            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('apply_end', 'Hạn nhận hồ sơ phải sau thời điểm bắt đầu.');
            }

            if ($end && $deadline && $deadline->lt($end)) {
                $validator->errors()->add('confirm_deadline', 'Hạn xác nhận phải sau hạn nhận hồ sơ.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên đợt',
            'type' => 'loại đợt',
            'apply_start' => 'thời điểm bắt đầu nhận hồ sơ',
            'apply_end' => 'hạn nhận hồ sơ',
            'result_date' => 'ngày công bố kết quả',
            'confirm_deadline' => 'hạn xác nhận',
            'sort_order' => 'thứ tự',
        ];
    }
}
