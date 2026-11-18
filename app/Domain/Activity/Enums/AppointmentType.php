<?php

namespace App\Domain\Activity\Enums;

enum AppointmentType: string
{
    case Counseling = 'counseling';
    case CampusTour = 'campus_tour';
    case Interview = 'interview';
    case TalentExam = 'talent_exam';

    public function label(): string
    {
        return match ($this) {
            self::Counseling => 'Tư vấn',
            self::CampusTour => 'Tham quan campus',
            self::Interview => 'Phỏng vấn',
            self::TalentExam => 'Thi năng khiếu',
        };
    }
}
