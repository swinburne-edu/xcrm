<?php

namespace App\Domain\Campaign\Enums;

enum CampaignChannel: string
{
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case Google = 'google';
    case Zalo = 'zalo';
    case Email = 'email';
    case Offline = 'offline';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Tiktok => 'TikTok',
            self::Google => 'Google',
            self::Zalo => 'Zalo',
            self::Email => 'Email',
            self::Offline => 'Trực tiếp',
            self::Other => 'Khác',
        };
    }
}
