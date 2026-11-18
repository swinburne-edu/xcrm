<?php

namespace App\Domain\Catalog\Enums;

/**
 * lead_sources.channel
 */
enum LeadChannel: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Trực tuyến',
            self::Offline => 'Trực tiếp',
            self::Referral => 'Giới thiệu',
        };
    }
}
