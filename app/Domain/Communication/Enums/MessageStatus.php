<?php

namespace App\Domain\Communication\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Đang chờ gửi',
            self::Sent => 'Đã gửi',
            self::Delivered => 'Đã nhận',
            self::Failed => 'Lỗi',
        };
    }
}
