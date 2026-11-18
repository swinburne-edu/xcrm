<?php

namespace App\Domain\Cycle\Enums;

enum CycleStatus: string
{
    case Preparing = 'preparing';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Chuẩn bị',
            self::Active => 'Đang tuyển sinh',
            self::Closed => 'Đã đóng',
        };
    }
}
