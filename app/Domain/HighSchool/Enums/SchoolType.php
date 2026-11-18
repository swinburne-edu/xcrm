<?php

namespace App\Domain\HighSchool\Enums;

enum SchoolType: string
{
    case Public = 'public';
    case Private = 'private';
    case Specialized = 'specialized';
    case Gifted = 'gifted';
    case ContinuingEdu = 'continuing_edu';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Công lập',
            self::Private => 'Tư thục',
            self::Specialized => 'Chuyên',
            self::Gifted => 'Năng khiếu',
            self::ContinuingEdu => 'GDTX',
            self::International => 'Quốc tế',
        };
    }
}
