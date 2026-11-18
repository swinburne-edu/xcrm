<?php

namespace App\Domain\Admission\Enums;

enum Temperature: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public function label(): string
    {
        return match ($this) {
            self::Hot => 'Nóng',
            self::Warm => 'Ấm',
            self::Cold => 'Lạnh',
        };
    }
}
