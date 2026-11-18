<?php

namespace App\Domain\Admission\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang xử lý',
            self::Won => 'Nhập học',
            self::Lost => 'Thất bại',
        };
    }
}
