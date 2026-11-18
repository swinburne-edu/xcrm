<?php

namespace App\Domain\HighSchool\Enums;

enum SchoolTier: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Hạng A — ưu tiên cao',
            self::B => 'Hạng B',
            self::C => 'Hạng C',
        };
    }
}
