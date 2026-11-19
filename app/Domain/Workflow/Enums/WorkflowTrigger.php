<?php

namespace App\Domain\Workflow\Enums;

enum WorkflowTrigger: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StageChanged = 'stage_changed';
    case Schedule = 'schedule';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Khi tạo mới',
            self::Updated => 'Khi đổi một trường',
            self::StageChanged => 'Khi đổi giai đoạn',
            self::Schedule => 'Hằng ngày (theo lịch)',
        };
    }
}
