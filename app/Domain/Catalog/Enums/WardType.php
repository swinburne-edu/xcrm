<?php

namespace App\Domain\Catalog\Enums;

enum WardType: string
{
    case Commune = 'xa';
    case Ward = 'phuong';
    case SpecialZone = 'dac_khu';

    public function label(): string
    {
        return match ($this) {
            self::Commune => 'Xã',
            self::Ward => 'Phường',
            self::SpecialZone => 'Đặc khu',
        };
    }
}
