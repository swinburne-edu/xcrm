<?php

namespace App\Domain\Applicant\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Grandparent = 'grandparent';
    case Sibling = 'sibling';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Bố',
            self::Mother => 'Mẹ',
            self::Grandparent => 'Ông/bà',
            self::Sibling => 'Anh/chị/em',
            self::Guardian => 'Người giám hộ',
        };
    }
}
