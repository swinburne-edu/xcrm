<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Actions\UpdateApplicantField;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Domain\Shared\Services\SensitiveData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /applicants/{uuid}/field: one field {field, value} of the quick edit in the applicant list.
 * Same rules as ApplicantRequest for that field, including the duplicate check on phone and email. The phone cannot
 * be changed here by someone who only sees it masked (they use the full form, which asks for the whole number).
 */
class ApplicantFieldRequest extends FormRequest
{
    private ?Applicant $applicant = null;

    public function authorize(): bool
    {
        return $this->applicant() !== null && (bool) $this->user()?->can('update', $this->applicant());
    }

    public function applicant(): ?Applicant
    {
        return $this->applicant ??= Applicant::query()->where('uuid', $this->route('uuid'))->first();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $field = (string) $this->input('field');

        $valueRules = match ($field) {
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(PhoneNormalizer::class)->isValid($value)) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'high_school_id' => ['nullable', 'integer', Rule::exists('high_schools', 'id')->where('organization_id', $organizationId)],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'graduation_year' => ['nullable', 'integer', 'between:2000,2100'],
            default => ['prohibited'],
        };

        return ['field' => ['required', Rule::in(UpdateApplicantField::FIELDS)], 'value' => $valueRules];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $field = (string) $this->input('field');

            if ($field === 'phone' && ! app(SensitiveData::class)->canView($this->user())) {
                $validator->errors()->add('value', 'Bạn chỉ thấy số điện thoại đã che, hãy sửa trong form đầy đủ.');

                return;
            }

            if (in_array($field, ['phone', 'email'], true) && filled($this->input('value'))) {
                foreach (app(ApplicantDeduplicator::class)->matches([$field => $this->input('value')], $this->applicant()?->id) as $match) {
                    $existing = $match['applicant'];
                    $who = $this->user()?->can('view', $existing) ? "thí sinh {$existing->code} – {$existing->full_name}" : 'một thí sinh khác (ngoài phạm vi dữ liệu của bạn)';

                    $validator->errors()->add('value', ($field === 'phone' ? 'Số điện thoại' : 'Email')." đã thuộc về {$who}.");
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['value' => 'giá trị', 'full_name' => 'họ tên'];
    }
}
