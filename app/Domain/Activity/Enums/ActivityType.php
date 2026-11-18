<?php

namespace App\Domain\Activity\Enums;

/**
 * Kinds of timeline entry. call / note / sms / zalo / email / meeting are typed by people;
 * stage_changed / owner_changed / system are written by the application.
 */
enum ActivityType: string
{
    case Call = 'call';
    case Note = 'note';
    case Sms = 'sms';
    case Zalo = 'zalo';
    case Email = 'email';
    case Meeting = 'meeting';
    case StageChanged = 'stage_changed';
    case OwnerChanged = 'owner_changed';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Cuộc gọi',
            self::Note => 'Ghi chú',
            self::Sms => 'SMS',
            self::Zalo => 'Zalo',
            self::Email => 'Email',
            self::Meeting => 'Gặp mặt',
            self::StageChanged => 'Đổi giai đoạn',
            self::OwnerChanged => 'Đổi người phụ trách',
            self::System => 'Hệ thống',
        };
    }

    /** Entries people may add by hand. */
    public function manual(): bool
    {
        return in_array($this, [self::Call, self::Note, self::Sms, self::Zalo, self::Email, self::Meeting], true);
    }
}
