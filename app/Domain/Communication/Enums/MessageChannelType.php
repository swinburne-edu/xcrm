<?php

namespace App\Domain\Communication\Enums;

use App\Domain\Activity\Enums\ActivityType;

enum MessageChannelType: string
{
    case Zns = 'zns';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Zns => 'Zalo ZNS',
            self::Sms => 'SMS',
            self::Email => 'Email',
        };
    }

    /** Timeline entry written for the applicant when a message goes out. */
    public function activityType(): ActivityType
    {
        return match ($this) {
            self::Zns => ActivityType::Zalo,
            self::Sms => ActivityType::Sms,
            self::Email => ActivityType::Email,
        };
    }
}
