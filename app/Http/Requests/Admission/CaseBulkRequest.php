<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Actions\BulkCaseAction;
use App\Domain\Admission\Models\AdmissionCase;
use Illuminate\Validation\Rule;

/**
 * POST /cases/bulk: one action over the selected cases. Per-case permissions and rules are checked by
 * BulkCaseAction; the form only needs view access to the screen.
 */
class CaseBulkRequest extends CaseRequests
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', AdmissionCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['assign', 'move', 'tag', 'untag'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkCaseAction::MAX_CASES],
            'ids.*' => ['uuid'],
            'owner_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_note' => ['nullable', 'string', 'max:5000'],
            'tag_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['ids.max' => 'Mỗi lần chỉ xử lý tối đa '.BulkCaseAction::MAX_CASES.' hồ sơ.', 'ids.required' => 'Chưa chọn hồ sơ nào.'];
    }
}
