<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\LeadChannel;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadSourceRequest extends CatalogRequest
{
    /** Source codes are lower snake_case keys (facebook_ads), not upper-case. */
    protected bool $uppercaseCode = false;

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Blank code: derive it from the name ("Facebook Ads" → facebook_ads).
        if (blank($this->input('code')) && is_string($this->input('name'))) {
            $this->merge(['code' => Str::slug($this->input('name'), '_')]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', $this->uniqueInOrganization('lead_sources', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', Rule::enum(LeadChannel::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã nguồn chỉ gồm chữ thường không dấu, chữ số và dấu gạch dưới (VD: facebook_ads).',
        ];
    }
}
