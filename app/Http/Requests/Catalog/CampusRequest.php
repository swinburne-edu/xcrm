<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Validation\Rule;

class CampusRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInOrganization('campuses', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
