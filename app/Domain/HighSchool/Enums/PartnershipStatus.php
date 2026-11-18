<?php

namespace App\Domain\HighSchool\Enums;

enum PartnershipStatus: string
{
    case NotContacted = 'not_contacted';
    case Contacting = 'contacting';
    case Partner = 'partner';
    case MouSigned = 'mou_signed';

    public function label(): string
    {
        return match ($this) {
            self::NotContacted => 'Chưa liên hệ',
            self::Contacting => 'Đang liên hệ',
            self::Partner => 'Đối tác',
            self::MouSigned => 'Đã ký MOU',
        };
    }
}
