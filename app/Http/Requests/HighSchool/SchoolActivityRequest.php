<?php

namespace App\Http\Requests\HighSchool;

use App\Domain\HighSchool\Enums\SchoolActivityType;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /school-activities and PUT /school-activities/{id}. Permission and the data scope are checked by the controller
 * (SchoolActivityPolicy). Times are typed in display time (YYYY-MM-DDTHH:mm). "Done" is not accepted here: the report
 * after the activity (CompleteSchoolActivity) sets it.
 */
class SchoolActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $own = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $organizationId)->whereNull('deleted_at');

        return [
            'type' => ['required', Rule::enum(SchoolActivityType::class)],
            'title' => ['required', 'string', 'max:255'],
            'high_school_id' => ['nullable', 'integer', $own('high_schools')],
            'campaign_id' => ['nullable', 'integer', $own('campaigns')],
            'start_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['planned', 'confirmed', 'cancelled'])],
            'expected_attendees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'staff_ids' => ['nullable', 'array', 'max:50'],
            'staff_ids.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'lead_id' => ['nullable', 'integer', 'in_array:staff_ids.*'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'loại hoạt động', 'title' => 'tên hoạt động', 'high_school_id' => 'trường THPT', 'campaign_id' => 'chiến dịch',
            'start_at' => 'giờ bắt đầu', 'end_at' => 'giờ kết thúc', 'location' => 'địa điểm', 'status' => 'trạng thái',
            'expected_attendees' => 'số tham dự dự kiến', 'staff_ids' => 'nhân sự', 'staff_ids.*' => 'nhân sự', 'lead_id' => 'người phụ trách',
        ];
    }
}
