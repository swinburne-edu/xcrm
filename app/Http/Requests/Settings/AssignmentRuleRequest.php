<?php

namespace App\Http\Requests\Settings;

use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /settings/assignment-rules and PUT /settings/assignment-rules/{id} (needs assignment_rule.manage).
 * Strategies other than "territory" need someone to choose from: listed users, or a team whose members are used.
 * "Fixed" needs at least one listed user.
 */
class AssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('assignment_rule.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'between:1,9999'],
            'strategy' => ['required', Rule::enum(AssignmentStrategy::class)],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array', 'max:200'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'conditions' => ['nullable', 'array'],
            'conditions.source_ids' => ['nullable', 'array'],
            'conditions.campaign_ids' => ['nullable', 'array'],
            'conditions.province_ids' => ['nullable', 'array'],
            'conditions.high_school_ids' => ['nullable', 'array'],
            'conditions.major_ids' => ['nullable', 'array'],
            'conditions.school_tiers' => ['nullable', 'array'],
            'conditions.*.*' => ['nullable'],
            'conditions.school_tiers.*' => ['in:A,B,C'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $strategy = AssignmentStrategy::tryFrom((string) $this->input('strategy'));

            if ($strategy === null || ! $strategy->needsPeople()) {
                return;
            }

            $hasUsers = array_filter((array) $this->input('user_ids', [])) !== [];

            if ($strategy === AssignmentStrategy::Fixed && ! $hasUsers) {
                $validator->errors()->add('user_ids', 'Chọn người phụ trách cố định.');
            } elseif (! $hasUsers && blank($this->input('team_id'))) {
                $validator->errors()->add('user_ids', 'Chọn những người được nhận hồ sơ, hoặc chọn một nhóm.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên quy tắc',
            'priority' => 'độ ưu tiên',
            'strategy' => 'cách phân bổ',
            'user_ids' => 'người nhận',
            'user_ids.*' => 'người nhận',
            'team_id' => 'nhóm',
            'conditions.school_tiers.*' => 'hạng trường',
        ];
    }
}
