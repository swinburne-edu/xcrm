<?php

namespace App\Domain\Organization\Enums;

/**
 * Row-level data scope of a user (users.data_scope), see docs/02-phan-quyen.md.
 */
enum DataScopeLevel: string
{
    case Own = 'own';
    case Team = 'team';
    case Territory = 'territory';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Của tôi',
            self::Team => 'Theo nhóm',
            self::Territory => 'Theo vùng phụ trách',
            self::All => 'Toàn bộ',
        };
    }
}
