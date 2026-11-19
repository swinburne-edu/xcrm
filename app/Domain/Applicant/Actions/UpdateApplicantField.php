<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\HighSchool\Models\HighSchool;

/**
 * Quick edit of one field of an applicant from the list (name, phone, email, high school, province, graduation year).
 * Only that field changes: everything else, including the sensitive columns, stays as it is. Rules that keep the
 * record coherent match SaveApplicant: the ward is cleared when the province changes, and a high school fills the
 * priority area when the applicant has none. Validation (format, duplicates, permissions) is in ApplicantFieldRequest.
 */
class UpdateApplicantField
{
    public const FIELDS = ['full_name', 'phone', 'email', 'high_school_id', 'province_id', 'graduation_year'];

    public function handle(Applicant $applicant, string $field, mixed $value): Applicant
    {
        $value = is_string($value) ? trim($value) : $value;
        $value = $value === '' ? null : $value;

        $applicant->{$field} = in_array($field, ['high_school_id', 'province_id', 'graduation_year'], true) && $value !== null ? (int) $value : $value;

        if ($field === 'province_id') {
            $applicant->ward_id = null;
        }

        if ($field === 'high_school_id' && $applicant->priority_area_id === null && $applicant->high_school_id !== null) {
            $applicant->priority_area_id = HighSchool::query()->withoutGlobalScopes()->whereKey($applicant->high_school_id)->value('priority_area_id');
        }

        $applicant->save();

        return $applicant->refresh();
    }
}
