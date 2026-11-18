<?php

namespace App\Domain\Applicant\Enums;

enum DocumentType: string
{
    case Transcript = 'transcript';
    case CitizenId = 'citizen_id';
    case PriorityProof = 'priority_proof';
    case Certificate = 'certificate';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transcript => 'Học bạ',
            self::CitizenId => 'CCCD',
            self::PriorityProof => 'Giấy tờ ưu tiên',
            self::Certificate => 'Chứng chỉ',
            self::Photo => 'Ảnh thẻ',
            self::Other => 'Khác',
        };
    }
}
