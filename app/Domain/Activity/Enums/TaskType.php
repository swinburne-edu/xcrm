<?php

namespace App\Domain\Activity\Enums;

enum TaskType: string
{
    case CallBack = 'call_back';
    case SendInfo = 'send_info';
    case RemindDocuments = 'remind_documents';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CallBack => 'Gọi lại',
            self::SendInfo => 'Gửi thông tin',
            self::RemindDocuments => 'Nhắc nộp giấy tờ',
            self::Other => 'Khác',
        };
    }
}
