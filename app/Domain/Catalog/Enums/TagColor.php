<?php

namespace App\Domain\Catalog\Enums;

/**
 * tags.color: tone names understood by <ToneBadge> (resources/js/components/crm/ToneBadge.vue).
 */
enum TagColor: string
{
    case Gray = 'gray';
    case Blue = 'blue';
    case Green = 'green';
    case Amber = 'amber';
    case Red = 'red';
    case Violet = 'violet';
    case Cyan = 'cyan';
    case Pink = 'pink';

    public function label(): string
    {
        return match ($this) {
            self::Gray => 'Xám',
            self::Blue => 'Xanh dương',
            self::Green => 'Xanh lá',
            self::Amber => 'Vàng cam',
            self::Red => 'Đỏ',
            self::Violet => 'Tím',
            self::Cyan => 'Xanh ngọc',
            self::Pink => 'Hồng',
        };
    }
}
