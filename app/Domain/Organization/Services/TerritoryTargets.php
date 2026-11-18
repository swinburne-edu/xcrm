<?php

namespace App\Domain\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * What a territory can cover: provinces now, high schools once task 1.8 registers them.
 * A type is available when its model has a morph alias and its EntityPicker lookup exists.
 */
final class TerritoryTargets
{
    /** morph alias => label and lookup (config/lookups.php) */
    private const TYPES = [
        'province' => ['label' => 'Tỉnh/Thành phố', 'lookup' => 'provinces'],
        'high_school' => ['label' => 'Trường THPT', 'lookup' => 'high_schools'],
    ];

    /** @return list<array{type: string, label: string, lookup: string}> */
    public static function available(): array
    {
        $available = [];

        foreach (self::TYPES as $type => $info) {
            if (Relation::getMorphedModel($type) !== null && config("lookups.{$info['lookup']}") !== null) {
                $available[] = ['type' => $type, ...$info];
            }
        }

        return $available;
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_column(self::available(), 'type');
    }

    /**
     * Display names of records of a target type.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function labels(string $type, array $ids): array
    {
        $model = Relation::getMorphedModel($type);

        if ($model === null || $ids === []) {
            return [];
        }

        return $model::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
