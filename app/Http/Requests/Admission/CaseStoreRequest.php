<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * POST /cases: opens the case of an applicant in the cycle selected in the header.
 * Choosing the owner needs case.assign; without it the owner follows the territory (or the shared pool).
 */
class CaseStoreRequest extends CaseRequests
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', AdmissionCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return $this->commonRules() + [
            'applicant_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Applicant::query()->whereNull('merged_into_id')->whereKey($value)->exists()) {
                    $fail('Không tìm thấy thí sinh (hoặc ngoài phạm vi dữ liệu của bạn).');
                }
            }],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
        ];
    }

    /** True when the form named an owner (or explicitly the pool) and the user may do that. */
    public function ownerChosen(): bool
    {
        return $this->has('owner_id') && $this->user()->can('case.assign');
    }
}
