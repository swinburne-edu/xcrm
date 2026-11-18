<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\ProvinceType;
use Illuminate\Validation\Rule;

class ProvinceRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueCode('provinces')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProvinceType::class)],
            'is_active' => ['boolean'],
        ];
    }
}
