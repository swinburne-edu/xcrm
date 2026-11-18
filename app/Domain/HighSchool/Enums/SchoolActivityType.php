<?php

namespace App\Domain\HighSchool\Enums;

/** Kinds of activity with or at a high school (docs/03 M3). */
enum SchoolActivityType: string
{
    case SchoolCounseling = 'school_counseling';
    case CareerFair = 'career_fair';
    case CampusTour = 'campus_tour';
    case Scholarship = 'scholarship';
    case PrincipalMeeting = 'principal_meeting';

    public function label(): string
    {
        return match ($this) {
            self::SchoolCounseling => 'Tư vấn tại trường',
            self::CareerFair => 'Ngày hội hướng nghiệp',
            self::CampusTour => 'Tham quan campus',
            self::Scholarship => 'Trao học bổng',
            self::PrincipalMeeting => 'Gặp Ban giám hiệu',
        };
    }

    /** lead_sources.code given to students who check in (seeded per organization). */
    public function sourceCode(): string
    {
        return match ($this) {
            self::CareerFair => 'open_day',
            self::CampusTour => 'campus_tour',
            default => 'school_counseling',
        };
    }

    /** Badge colour of the calendar / table. */
    public function tone(): string
    {
        return match ($this) {
            self::SchoolCounseling => 'blue',
            self::CareerFair => 'violet',
            self::CampusTour => 'green',
            self::Scholarship => 'amber',
            self::PrincipalMeeting => 'gray',
        };
    }
}
