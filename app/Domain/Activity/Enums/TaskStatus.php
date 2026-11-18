<?php

namespace App\Domain\Activity\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Cần làm',
            self::Done => 'Hoàn thành',
            self::Cancelled => 'Đã huỷ',
        };
    }
}
