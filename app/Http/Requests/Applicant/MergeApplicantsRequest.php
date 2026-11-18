<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Models\Applicant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /applicants/merge: keep (the survivor's uuid), merge (the duplicate's uuid) and, per field, which value to keep.
 */
class MergeApplicantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('merge', Applicant::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keep' => ['required', 'uuid', 'different:merge'],
            'merge' => ['required', 'uuid'],
            'choices' => ['nullable', 'array'],
            'choices.*' => [Rule::in(['keep', 'merge'])],
        ];
    }

    public function messages(): array
    {
        return ['keep.different' => 'Chọn hai thí sinh khác nhau.'];
    }
}
