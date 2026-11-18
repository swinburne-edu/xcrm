<?php

namespace App\Http\Requests\Catalog;

class CallResultRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInOrganization('call_results', 'name')],
            'is_contacted' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
