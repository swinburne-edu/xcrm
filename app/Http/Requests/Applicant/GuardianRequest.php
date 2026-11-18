<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Enums\GuardianRelationship;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guardian of an applicant and the link between them. Authorization (applicant.update on an applicant
 * in the user's scope) is done by ApplicantGuardianController.
 */
class GuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(PhoneNormalizer::class)->isValid($value)) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'zalo_id' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'relationship' => ['nullable', Rule::enum(GuardianRelationship::class)],
            'is_decision_maker' => ['boolean'],
            'is_primary_contact' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['full_name' => 'họ tên', 'phone' => 'số điện thoại', 'relationship' => 'quan hệ', 'occupation' => 'nghề nghiệp'];
    }
}
