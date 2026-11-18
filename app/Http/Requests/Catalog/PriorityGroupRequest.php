<?php

namespace App\Http\Requests\Catalog;

class PriorityGroupRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueCode('priority_groups')],
            'name' => ['required', 'string', 'max:255'],
            'bonus_score' => ['required', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
        ];
    }
}
