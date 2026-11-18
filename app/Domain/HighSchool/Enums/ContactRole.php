<?php

namespace App\Domain\HighSchool\Enums;

enum ContactRole: string
{
    case Principal = 'principal';
    case VicePrincipal = 'vice_principal';
    case CareerCounselor = 'career_counselor';
    case HomeroomTeacher = 'homeroom_teacher';
    case YouthUnion = 'youth_union';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Principal => 'Hiệu trưởng',
            self::VicePrincipal => 'Phó hiệu trưởng',
            self::CareerCounselor => 'Tư vấn hướng nghiệp',
            self::HomeroomTeacher => 'Giáo viên chủ nhiệm',
            self::YouthUnion => 'Đoàn trường',
            self::Other => 'Khác',
        };
    }
}
