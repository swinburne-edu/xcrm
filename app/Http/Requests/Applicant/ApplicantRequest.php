<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Enums\Gender;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Catalog\Models\Ward;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Services\CustomFields;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update an applicant. Rejects a candidate that already exists (same phone, e-mail or citizen id):
 * the message names the existing record only when the user may see it.
 */
class ApplicantRequest extends FormRequest
{
    private ?Applicant $applicant = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('uuid') === null) {
            return (bool) $user?->can('create', Applicant::class);
        }

        return $this->applicant() !== null && (bool) $user?->can('update', $this->applicant());
    }

    public function applicant(): ?Applicant
    {
        return $this->applicant ??= $this->route('uuid') === null ? null : Applicant::query()->where('uuid', $this->route('uuid'))->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $inOrganization = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $organizationId);

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'dob' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1950-01-01'],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(PhoneNormalizer::class)->isValid($value)) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'citizen_id' => ['nullable', 'regex:/^(\d{9}|\d{12})$/'],
            'zalo_id' => ['nullable', 'string', 'max:100'],
            'facebook_id' => ['nullable', 'string', 'max:100'],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'high_school_id' => ['nullable', 'integer', $inOrganization('high_schools')],
            'class_name' => ['nullable', 'string', 'max:20'],
            'current_grade' => ['nullable', 'integer', 'between:10,13'],
            'graduation_year' => ['nullable', 'integer', 'between:2000,2100'],
            'priority_area_id' => ['nullable', 'integer', Rule::exists('priority_areas', 'id')],
            'priority_group_id' => ['nullable', 'integer', Rule::exists('priority_groups', 'id')],
            'first_source_id' => ['nullable', 'integer', $inOrganization('lead_sources')],
            'first_campaign_id' => ['nullable', 'integer', $inOrganization('campaigns')],
            'referred_by_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'consent_marketing' => ['boolean'],
            ...app(CustomFields::class)->rules(CustomFieldObject::Applicants),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('ward_id') && $this->filled('province_id')
                && ! Ward::query()->whereKey($this->input('ward_id'))->where('province_id', $this->input('province_id'))->exists()) {
                $validator->errors()->add('ward_id', 'Xã/phường không thuộc tỉnh/thành phố đã chọn.');
            }

            $labels = ['phone' => 'Số điện thoại', 'email' => 'Email', 'citizen_id' => 'Số CCCD'];

            foreach (app(ApplicantDeduplicator::class)->matches($this->only(['phone', 'email', 'citizen_id']), $this->applicant()?->id) as $match) {
                $existing = $match['applicant'];
                $who = $this->user()?->can('view', $existing)
                    ? "thí sinh {$existing->code} – {$existing->full_name}"
                    : 'một thí sinh khác (ngoài phạm vi dữ liệu của bạn)';

                $validator->errors()->add($match['field'], "{$labels[$match['field']]} đã thuộc về {$who}.");
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'họ tên', 'gender' => 'giới tính', 'dob' => 'ngày sinh', 'phone' => 'số điện thoại', 'citizen_id' => 'số CCCD',
            'province_id' => 'tỉnh/thành phố', 'ward_id' => 'xã/phường', 'high_school_id' => 'trường THPT', 'current_grade' => 'khối lớp',
            'graduation_year' => 'năm tốt nghiệp', 'priority_area_id' => 'khu vực ưu tiên', 'priority_group_id' => 'đối tượng ưu tiên',
            'first_source_id' => 'nguồn', 'first_campaign_id' => 'chiến dịch', 'referred_by_user_id' => 'người giới thiệu',
            ...app(CustomFields::class)->attributes(CustomFieldObject::Applicants),
        ];
    }

    public function messages(): array
    {
        return ['citizen_id.regex' => 'Số CCCD gồm 12 chữ số (hoặc CMND 9 chữ số).'];
    }
}
