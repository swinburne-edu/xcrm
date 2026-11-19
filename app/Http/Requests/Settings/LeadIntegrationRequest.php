<?php

namespace App\Http\Requests\Settings;

use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Services\LeadFieldMapper;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /settings/lead-integrations and PUT /settings/lead-integrations/{id} (needs lead_integration.manage).
 * The signing secret is required when creating; when editing, blank secrets / tokens mean "keep the saved one".
 */
class LeadIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('lead_integration.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $creating = $this->isMethod('POST');

        return [
            'provider' => [$creating ? 'required' : 'prohibited', Rule::enum(LeadProvider::class)],
            'name' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('organization_id', $organizationId)],
            'is_active' => ['boolean'],
            'secret' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'verify_token' => ['nullable', 'string', 'max:100'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'field_map' => ['nullable', 'array', 'max:30'],
            'field_map.*.name' => ['nullable', 'string', 'max:100'],
            'field_map.*.key' => ['nullable', Rule::in(LeadFieldMapper::KEYS)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tên', 'secret' => 'khoá bí mật', 'verify_token' => 'verify token', 'access_token' => 'access token', 'campaign_id' => 'chiến dịch', 'source_id' => 'nguồn'];
    }
}
