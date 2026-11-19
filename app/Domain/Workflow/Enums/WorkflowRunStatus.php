<?php

namespace App\Domain\Workflow\Enums;

enum WorkflowRunStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Thành công',
            self::Partial => 'Một phần',
            self::Failed => 'Lỗi',
            self::Skipped => 'Không đạt điều kiện',
        };
    }
}
