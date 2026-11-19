<?php

namespace App\Http\Requests\HighSchool;

use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Services\CustomFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HighSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', HighSchool::class);
        }

        $school = HighSchool::query()->find($this->route('id'));

        return $school !== null && (bool) $user?->can('update', $school);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $code = Str::upper(trim($this->input('code')));
            $this->merge(['code' => $code === '' ? null : $code]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('high_schools', 'code')
                ->where('organization_id', $organizationId)->ignore($this->route('id'))],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(SchoolType::class)],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'priority_area_id' => ['nullable', 'integer', Rule::exists('priority_areas', 'id')],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'grade12_students' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'grade12_classes' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'strong_combinations' => ['nullable', 'array', 'max:20'],
            'strong_combinations.*' => ['string', Rule::exists('subject_combinations', 'code')],
            'tier' => ['nullable', Rule::enum(SchoolTier::class)],
            'partnership_status' => ['nullable', Rule::enum(PartnershipStatus::class)],
            'mou_signed_at' => ['nullable', 'date_format:Y-m-d'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:10000'],
            ...app(CustomFields::class)->rules(CustomFieldObject::HighSchools),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('ward_id') || ! $this->filled('province_id')) {
                return;
            }

            $belongs = Ward::query()->whereKey($this->input('ward_id'))->where('province_id', $this->input('province_id'))->exists();

            if (! $belongs) {
                $validator->errors()->add('ward_id', 'Xã/phường không thuộc tỉnh/thành phố đã chọn.');
            }
        }];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Mã trường này đã được dùng.'];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã trường', 'name' => 'tên trường', 'type' => 'loại hình', 'province_id' => 'tỉnh/thành phố',
            'ward_id' => 'xã/phường', 'priority_area_id' => 'khu vực ưu tiên', 'grade12_students' => 'số học sinh khối 12',
            'grade12_classes' => 'số lớp khối 12', 'strong_combinations' => 'tổ hợp mạnh', 'tier' => 'hạng',
            'partnership_status' => 'trạng thái hợp tác', 'mou_signed_at' => 'ngày ký MOU', 'owner_id' => 'người phụ trách',
            ...app(CustomFields::class)->attributes(CustomFieldObject::HighSchools),
        ];
    }
}
