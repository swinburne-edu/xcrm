<?php

namespace App\Domain\Intake\Enums;

/** Where a received lead is: queued, turned into or merged with an applicant, or failed / rejected. */
enum LeadEventStatus: string
{
    case Received = 'received';
    case Created = 'created';
    case Merged = 'merged';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Đang xử lý',
            self::Created => 'Đã tạo thí sinh',
            self::Merged => 'Trùng, đã bổ sung',
            self::Failed => 'Lỗi',
            self::Rejected => 'Bị từ chối',
        };
    }
}
