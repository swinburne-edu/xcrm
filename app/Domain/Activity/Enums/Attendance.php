<?php

namespace App\Domain\Activity\Enums;

enum Attendance: string
{
    case Scheduled = 'scheduled';
    case Attended = 'attended';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Đã hẹn',
            self::Attended => 'Có đến',
            self::NoShow => 'Vắng',
            self::Cancelled => 'Đã huỷ',
        };
    }
}
