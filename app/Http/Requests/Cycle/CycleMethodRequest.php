<?php

namespace App\Http\Requests\Cycle;

use App\Domain\Cycle\Actions\SaveCycleMethod;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\ScoreFormulaValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admission method of a cycle. Authorization (cycle.update, cycle not closed — R8) is done by the controller.
 * score_formula / conditions may arrive as arrays (structured form) or JSON strings (advanced mode).
 */
class CycleMethodRequest extends FormRequest
{
    public const DOCUMENT_TYPES = ['transcript', 'citizen_id', 'priority_proof', 'certificate', 'photo', 'other'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('cycleModel'));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['score_formula', 'conditions'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = $value === '' ? null : json_decode($value, true);

                if ($value !== '' && $merge[$field] === null) {
                    $merge[$field] = "invalid-json:{$value}";
                }
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $cycle = $this->route('cycleModel');

        return [
            'method_id' => ['required', 'integer', Rule::exists('admission_methods', 'id'), Rule::unique('cycle_methods', 'method_id')
                ->where('cycle_id', $cycle?->id)->ignore($this->route('methodId'))],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_direct' => ['boolean'],
            'score_formula' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string', Rule::in(self::DOCUMENT_TYPES)],
        ];
    }

    /**
     * Formula structure (docs/04-database.md §6) and rule R1 (max 5 non-direct methods per cycle).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['score_formula', 'conditions'] as $field) {
                if (is_string($this->input($field))) {
                    $validator->errors()->add($field, 'Không phải JSON hợp lệ.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $isDirect = $this->boolean('is_direct');
            $formula = $this->input('score_formula');

            if (! $isDirect && blank($formula)) {
                $validator->errors()->add('score_formula', 'Phương thức xét tuyển (không phải xét thẳng) cần có công thức điểm.');
            }

            foreach (app(ScoreFormulaValidator::class)->errors($formula) as $message) {
                $validator->errors()->add('score_formula', $message);
            }

            /** @var AdmissionCycle|null $cycle */
            $cycle = $this->route('cycleModel');
            $editing = $this->route('methodId') ? CycleMethod::query()->find($this->route('methodId')) : null;

            if ($cycle && ! $isDirect && SaveCycleMethod::wouldExceedLimit($cycle, $editing)) {
                $validator->errors()->add('is_direct', 'Mỗi mùa chỉ có tối đa '.AdmissionCycle::MAX_METHODS.' phương thức (không tính xét tuyển thẳng).');
            }
        }];
    }

    public function messages(): array
    {
        return ['method_id.unique' => 'Phương thức này đã có trong mùa.'];
    }

    public function attributes(): array
    {
        return [
            'method_id' => 'phương thức',
            'display_name' => 'tên hiển thị',
            'score_formula' => 'công thức điểm',
            'conditions' => 'điều kiện',
            'required_documents' => 'giấy tờ',
        ];
    }
}
