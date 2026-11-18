<?php

namespace App\Domain\Applicant\Enums;

enum CertificateType: string
{
    case Ielts = 'IELTS';
    case Toefl = 'TOEFL';
    case Toeic = 'TOEIC';
    case Sat = 'SAT';
    case Act = 'ACT';
    case Vstep = 'VSTEP';
    case Other = 'OTHER';

    public function label(): string
    {
        return $this === self::Other ? 'Khác' : $this->value;
    }
}
