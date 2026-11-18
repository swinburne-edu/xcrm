<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Enums\CertificateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A certificate of an applicant. Authorization is done by ApplicantCertificateController.
 */
class CertificateRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CertificateType::class)],
            'score' => ['nullable', 'string', 'max:20'],
            'issued_at' => ['nullable', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => 'loại chứng chỉ', 'score' => 'điểm', 'issued_at' => 'ngày cấp', 'expires_at' => 'ngày hết hạn'];
    }
}
