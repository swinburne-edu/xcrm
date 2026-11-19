<?php

namespace App\Domain\Intake\Enums;

/** The ad platforms that can push leads to a webhook (task 2.7). */
enum LeadProvider: string
{
    case Facebook = 'facebook';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook Lead Ads',
            self::TikTok => 'TikTok Lead Generation',
        };
    }

    /** Code of the lead source used when the integration names none. */
    public function sourceCode(): string
    {
        return match ($this) {
            self::Facebook => 'facebook_ads',
            self::TikTok => 'tiktok_ads',
        };
    }
}
