<?php

namespace App\Http\Requests\Catalog;

class LostReasonRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInOrganization('lost_reasons', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
