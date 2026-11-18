<?php

namespace App\Http\Requests\Settings;

use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('id') ? 'team.update' : 'team.create');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $activeUser = Rule::exists('users', 'id')->where('organization_id', $organizationId)->where('is_active', true)->whereNull('deleted_at');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')
                ->where('organization_id', $organizationId)->whereNull('deleted_at')->ignore($this->route('id'))],
            'leader_id' => ['nullable', 'integer', $activeUser],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', $activeUser],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên nhóm',
            'leader_id' => 'trưởng nhóm',
            'member_ids' => 'thành viên',
            'member_ids.*' => 'thành viên',
        ];
    }
}
