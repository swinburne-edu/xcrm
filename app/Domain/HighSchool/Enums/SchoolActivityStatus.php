<?php

namespace App\Domain\HighSchool\Enums;

enum SchoolActivityStatus: string
{
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Dự kiến',
            self::Confirmed => 'Đã xác nhận',
            self::Done => 'Đã diễn ra',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Confirmed => 'blue',
            self::Done => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Students may check in by QR while the activity is planned or confirmed. */
    public function acceptsCheckin(): bool
    {
        return $this === self::Planned || $this === self::Confirmed;
    }
}
