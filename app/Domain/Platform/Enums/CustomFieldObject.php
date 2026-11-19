<?php

namespace App\Domain\Platform\Enums;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\Major\Models\Major;
use Illuminate\Database\Eloquent\Model;

/** The objects that can carry custom fields (every model using HasCustomData). */
enum CustomFieldObject: string
{
    case Applicants = 'applicants';
    case AdmissionCases = 'admission_cases';
    case HighSchools = 'high_schools';
    case Majors = 'majors';
    case Campaigns = 'campaigns';
    case SchoolContacts = 'school_contacts';

    public function label(): string
    {
        return match ($this) {
            self::Applicants => 'Thí sinh',
            self::AdmissionCases => 'Hồ sơ tuyển sinh',
            self::HighSchools => 'Trường THPT',
            self::Majors => 'Ngành',
            self::Campaigns => 'Chiến dịch',
            self::SchoolContacts => 'Người liên hệ ở trường',
        };
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Applicants => Applicant::class,
            self::AdmissionCases => AdmissionCase::class,
            self::HighSchools => HighSchool::class,
            self::Majors => Major::class,
            self::Campaigns => Campaign::class,
            self::SchoolContacts => SchoolContact::class,
        };
    }
}
