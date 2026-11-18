<?php

namespace App\Domain\Cycle\Enums;

enum RoundType: string
{
    case EarlySchool = 'early_school';
    case MoetSystem = 'moet_system';
    case Supplementary = 'supplementary';

    public function label(): string
    {
        return match ($this) {
            self::EarlySchool => 'Xét sớm (trường)',
            self::MoetSystem => 'Hệ thống của Bộ',
            self::Supplementary => 'Bổ sung',
        };
    }
}
