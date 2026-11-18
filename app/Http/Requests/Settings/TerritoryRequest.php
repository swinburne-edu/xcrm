<?php

namespace App\Http\Requests\Settings;

use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Organization\Services\TerritoryTargets;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('territory.manage');
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
        $types = TerritoryTargets::types();

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('territories', 'name')
                ->where('organization_id', $organizationId)->whereNull('deleted_at')->ignore($this->route('id'))],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')
                ->where('organization_id', $organizationId)->where('is_active', true)->whereNull('deleted_at')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')
                ->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'targets' => ['nullable', 'array:'.implode(',', $types)],
        ];

        foreach ($types as $type) {
            $rules["targets.{$type}"] = ['nullable', 'array'];
            $rules["targets.{$type}.*"] = ['integer', 'distinct', $type === 'province'
                ? Rule::exists('provinces', 'id')->where('is_active', true)
                : Rule::exists('high_schools', 'id')->where('organization_id', $organizationId)];
        }

        return $rules;
    }

    /**
     * A territory needs someone responsible, and a province / school belongs to one territory only.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (blank($this->input('user_id')) && blank($this->input('team_id'))) {
                $validator->errors()->add('user_id', 'Chọn người phụ trách hoặc nhóm phụ trách.');
            }

            foreach ($this->conflicts() as $type => $message) {
                $validator->errors()->add("targets.{$type}", $message);
            }
        }];
    }

    /**
     * @return array<string, string> type => message
     */
    private function conflicts(): array
    {
        $conflicts = [];
        $current = $this->route('id');

        foreach ((array) $this->input('targets', []) as $type => $ids) {
            $ids = array_map('intval', (array) $ids);

            if ($ids === []) {
                continue;
            }

            $taken = TerritoryAssignment::query()
                ->where('assignable_type', $type)
                ->whereIn('assignable_id', $ids)
                ->whereHas('territory', fn ($q) => $q->when($current, fn ($q) => $q->whereKeyNot($current)))
                ->with('territory:id,name')
                ->get();

            if ($taken->isEmpty()) {
                continue;
            }

            $names = TerritoryTargets::labels($type, $taken->pluck('assignable_id')->all());
            $conflicts[$type] = $taken->map(fn ($a) => ($names[$a->assignable_id] ?? "#{$a->assignable_id}").' đã thuộc vùng '.$a->territory->name)->implode('; ').'.';
        }

        return $conflicts;
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên vùng',
            'user_id' => 'người phụ trách',
            'team_id' => 'nhóm',
            'targets' => 'phạm vi',
        ];
    }
}
