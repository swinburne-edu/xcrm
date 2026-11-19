<?php

namespace App\Domain\Applicant\Services;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Services\CustomFields;
use App\Domain\Shared\Support\Listing\Filter;

/** The filters of the applicant list, shared by the list itself and the bulk message send (which reuses the list's filters). */
class ApplicantFilters
{
    /** @return list<Filter> */
    public static function all(): array
    {
        return [
            Filter::search('q', ['full_name', 'code', 'email', 'phone', 'phone_normalized']),
            Filter::exact('province_id'),
            Filter::exact('high_school_id'),
            Filter::exact('graduation_year'),
            Filter::exact('current_grade'),
            Filter::exact('first_source_id'),
            Filter::exact('first_campaign_id'),
            ...app(CustomFields::class)->filters(CustomFieldObject::Applicants),
        ];
    }
}
