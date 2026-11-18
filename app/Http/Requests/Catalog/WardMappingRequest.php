<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Validation\Rule;

class WardMappingRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'old_province_name' => ['required', 'string', 'max:255'],
            'old_district_name' => ['nullable', 'string', 'max:255'],
            'old_ward_name' => ['required', 'string', 'max:255'],
            'new_ward_id' => ['required', 'integer', Rule::exists('wards', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'old_province_name' => 'tỉnh (cũ)',
            'old_district_name' => 'quận/huyện (cũ)',
            'old_ward_name' => 'xã/phường (cũ)',
        ];
    }
}
