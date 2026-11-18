<?php

namespace App\Http\Requests\Catalog;

class FacultyRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueInOrganization('faculties', 'code')],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
