<?php

namespace App\Domain\Activity\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Normal => 'Bình thường',
            self::High => 'Cao',
        };
    }
}
