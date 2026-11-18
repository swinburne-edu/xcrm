<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A stage of a pipeline: code (unique in the pipeline), name, colour, order, type (open / won / lost),
 * win probability, SLA hours and the case fields required to enter it.
 */
class StageRequest extends FormRequest
{
    public const COLORS = ['gray', 'blue', 'green', 'amber', 'red', 'violet', 'cyan', 'pink'];

    public function authorize(): bool
    {
        $pipeline = Pipeline::query()->find($this->route('id'));

        return $pipeline !== null && (bool) $this->user()?->can('update', $pipeline);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('pipeline_stages', 'code')
                ->where('pipeline_id', $this->route('id'))->ignore($this->route('stageId'))],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'type' => ['required', Rule::enum(StageType::class)],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'required_fields' => ['nullable', 'array'],
            'required_fields.*' => ['string', Rule::in(PipelineStage::REQUIRABLE)],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Mã giai đoạn chỉ gồm chữ thường không dấu, số và gạch dưới.', 'code.unique' => 'Mã giai đoạn này đã có trong pipeline.'];
    }

    public function attributes(): array
    {
        return ['code' => 'mã giai đoạn', 'name' => 'tên giai đoạn', 'type' => 'loại', 'probability' => 'xác suất', 'sla_hours' => 'SLA (giờ)', 'sort_order' => 'thứ tự'];
    }
}
