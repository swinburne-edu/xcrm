<?php

namespace App\Domain\Admission\Enums;

/**
 * What reaching a stage means for a case: still open, won (enrolled) or lost. A case's status
 * always equals the type of its stage (rule R7).
 */
enum StageType: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang xử lý',
            self::Won => 'Thành công',
            self::Lost => 'Thất bại',
        };
    }

    public function caseStatus(): CaseStatus
    {
        return CaseStatus::from($this->value);
    }
}
