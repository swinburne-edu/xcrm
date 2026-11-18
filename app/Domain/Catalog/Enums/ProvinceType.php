<?php

namespace App\Domain\Catalog\Enums;

enum ProvinceType: string
{
    case Province = 'tinh';
    case City = 'thanh_pho';

    public function label(): string
    {
        return match ($this) {
            self::Province => 'Tỉnh',
            self::City => 'Thành phố',
        };
    }
}
