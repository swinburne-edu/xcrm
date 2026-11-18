<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Rules\ValidSubjectCombination;

/**
 * Rule R2: exactly 3 subjects, with Toán or Ngữ văn weighing ≥ 1/3 of the total.
 */
class SubjectCombinationRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', $this->uniqueCode('subject_combinations')],
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'subjects' => ['required', 'array', new ValidSubjectCombination],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct'],
            'subjects.*.weight' => ['required', 'numeric', 'gt:0', 'max:10', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã tổ hợp chỉ gồm chữ in hoa không dấu và chữ số (VD: A00, D01).',
            'subjects.*.subject_id.required' => 'Vui lòng chọn đủ 3 môn.',
            'subjects.*.subject_id.distinct' => 'Ba môn trong tổ hợp phải khác nhau.',
            'subjects.*.weight.*' => 'Trọng số phải là số lớn hơn 0 (tối đa 2 chữ số thập phân).',
        ];
    }
}
