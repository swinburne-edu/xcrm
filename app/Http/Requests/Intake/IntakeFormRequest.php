<?php

namespace App\Http\Requests\Intake;

use App\Domain\Intake\Models\Form;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / change a web form (the lead form of the university site). Needs form.manage.
 */
class IntakeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('form.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('organization_id', $org)],
            'source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('organization_id', $org)],
            'success_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.key' => ['required', Rule::in(array_keys(Form::AVAILABLE_FIELDS))],
            'fields.*.required' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên biểu mẫu', 'campaign_id' => 'chiến dịch', 'source_id' => 'nguồn', 'success_message' => 'thông báo thành công'];
    }
}
