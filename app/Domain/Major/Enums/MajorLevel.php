<?php

namespace App\Domain\Major\Enums;

enum MajorLevel: string
{
    case University = 'university';
    case College = 'college';
    case Transfer = 'transfer';
    case SecondDegree = 'second_degree';

    public function label(): string
    {
        return match ($this) {
            self::University => 'Đại học',
            self::College => 'Cao đẳng',
            self::Transfer => 'Liên thông',
            self::SecondDegree => 'Văn bằng 2',
        };
    }
}
