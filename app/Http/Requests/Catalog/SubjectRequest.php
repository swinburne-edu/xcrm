<?php

namespace App\Http\Requests\Catalog;

class SubjectRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', $this->uniqueCode('subjects')],
            'name' => ['required', 'string', 'max:100'],
            'is_core' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã môn chỉ gồm chữ in hoa không dấu, chữ số và dấu gạch dưới (VD: TOAN, GDKTPL).',
        ];
    }
}
