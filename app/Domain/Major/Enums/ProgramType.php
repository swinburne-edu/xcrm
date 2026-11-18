<?php

namespace App\Domain\Major\Enums;

enum ProgramType: string
{
    case Standard = 'standard';
    case HighQuality = 'high_quality';
    case Advanced = 'advanced';
    case Joint = 'joint';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Chuẩn',
            self::HighQuality => 'Chất lượng cao',
            self::Advanced => 'Tiên tiến',
            self::Joint => 'Liên kết quốc tế',
        };
    }
}
