<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\TagColor;
use Illuminate\Validation\Rule;

class TagRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', $this->uniqueInOrganization('tags', 'name')],
            'color' => ['nullable', Rule::enum(TagColor::class)],
        ];
    }
}
