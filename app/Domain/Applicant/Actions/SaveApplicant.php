<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Shared\Services\SensitiveData;
use App\Models\User;

/**
 * Create or update an applicant from validated input (ApplicantRequest).
 *  - Phone and citizen id shown masked to users without applicant.view_sensitive come back blank from the form:
 *    for them a blank means "unchanged", never "clear".
 *  - Area of priority defaults to the school's when not given (docs: KV lấy từ trường).
 *  - consent_at is stamped when marketing consent is first given and cleared when withdrawn.
 */
class SaveApplicant
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Applicant $applicant, array $data, User $by): Applicant
    {
        $applicant ??= new Applicant;
        $canSeeSensitive = $this->sensitive->canView($by);

        $fill = [
            'full_name' => $data['full_name'],
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'email' => $data['email'] ?? null,
            'zalo_id' => $data['zalo_id'] ?? null,
            'facebook_id' => $data['facebook_id'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
            'address' => $data['address'] ?? null,
            'high_school_id' => $data['high_school_id'] ?? null,
            'class_name' => $data['class_name'] ?? null,
            'current_grade' => $data['current_grade'] ?? null,
            'graduation_year' => $data['graduation_year'] ?? null,
            'priority_area_id' => $data['priority_area_id'] ?? null,
            'priority_group_id' => $data['priority_group_id'] ?? null,
            'first_source_id' => $data['first_source_id'] ?? null,
            'first_campaign_id' => $data['first_campaign_id'] ?? null,
            'referred_by_user_id' => $data['referred_by_user_id'] ?? null,
        ];

        foreach (['phone', 'citizen_id'] as $field) {
            if ($canSeeSensitive || ! $applicant->exists || filled($data[$field] ?? null)) {
                $fill[$field] = $data[$field] ?? null;
            }
        }

        $applicant->fill($fill);

        if ($applicant->priority_area_id === null && $applicant->high_school_id !== null) {
            $applicant->priority_area_id = HighSchool::query()->withoutGlobalScopes()->whereKey($applicant->high_school_id)->value('priority_area_id');
        }

        $consent = (bool) ($data['consent_marketing'] ?? false);
        $applicant->consent_at = $consent ? ($applicant->consent_marketing ? $applicant->consent_at : now()) : null;
        $applicant->consent_marketing = $consent;

        $applicant->save();

        return $applicant->refresh();
    }
}
