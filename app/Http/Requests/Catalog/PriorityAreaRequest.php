<?php

namespace App\Http\Requests\Catalog;

class PriorityAreaRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueCode('priority_areas')],
            'name' => ['required', 'string', 'max:100'],
            'bonus_score' => ['required', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
        ];
    }
}
