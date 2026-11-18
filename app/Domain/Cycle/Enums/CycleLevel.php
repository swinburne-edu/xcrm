<?php

namespace App\Domain\Cycle\Enums;

enum CycleLevel: string
{
    case University = 'university';
    case College = 'college';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::University => 'Đại học',
            self::College => 'Cao đẳng',
            self::Transfer => 'Liên thông',
        };
    }
}
