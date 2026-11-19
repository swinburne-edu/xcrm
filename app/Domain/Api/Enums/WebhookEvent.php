<?php

namespace App\Domain\Api\Enums;

enum WebhookEvent: string
{
    case CaseEnrolled = 'case.enrolled';
    case ApplicationAdmitted = 'application.admitted';

    public function label(): string
    {
        return match ($this) {
            self::CaseEnrolled => 'Thí sinh xác nhận nhập học',
            self::ApplicationAdmitted => 'Nguyện vọng trúng tuyển',
        };
    }
}
