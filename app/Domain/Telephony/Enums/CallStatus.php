<?php

namespace App\Domain\Telephony\Enums;

enum CallStatus: string
{
    case Dialing = 'dialing';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Ended = 'ended';
    case Missed = 'missed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Dialing => 'Đang gọi',
            self::Ringing => 'Đang đổ chuông',
            self::Answered => 'Đang nói chuyện',
            self::Ended => 'Đã kết thúc',
            self::Missed => 'Nhỡ máy',
            self::Failed => 'Không gọi được',
        };
    }
}
