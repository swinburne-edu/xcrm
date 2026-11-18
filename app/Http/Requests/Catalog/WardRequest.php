<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\WardType;
use Illuminate\Validation\Rule;

class WardRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'code' => ['required', 'string', 'max:10', $this->uniqueCode('wards')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(WardType::class)],
            'is_active' => ['boolean'],
        ];
    }
}
