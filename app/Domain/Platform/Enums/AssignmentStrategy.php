<?php

namespace App\Domain\Platform\Enums;

/** How an assignment rule picks the owner of a new case (docs/database.dbml assignment_rules.strategy). */
enum AssignmentStrategy: string
{
    case Territory = 'territory';
    case RoundRobin = 'round_robin';
    case LoadBalance = 'load_balance';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Territory => 'Theo vùng phụ trách',
            self::RoundRobin => 'Xoay vòng',
            self::LoadBalance => 'Theo tải (ít hồ sơ đang mở nhất)',
            self::Fixed => 'Cố định một người',
        };
    }

    /** Strategies that choose among the listed users / the members of the team. */
    public function needsPeople(): bool
    {
        return $this !== self::Territory;
    }
}
