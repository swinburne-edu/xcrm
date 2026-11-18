<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a territory with the provinces / schools it covers.
 */
class SaveTerritory
{
    /**
     * @param  array{name: string, user_id?: int|null, team_id?: int|null, targets?: array<string, list<int|string>>|null}  $data
     */
    public function handle(?Territory $territory, array $data): Territory
    {
        return DB::transaction(function () use ($territory, $data): Territory {
            $territory ??= new Territory;

            $territory->fill([
                'name' => $data['name'],
                'user_id' => $data['user_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
            ])->save();

            $territory->assignments()->delete();

            $rows = [];

            foreach ($data['targets'] ?? [] as $type => $ids) {
                foreach (array_unique(array_map('intval', $ids)) as $id) {
                    $rows[] = ['territory_id' => $territory->id, 'assignable_type' => $type, 'assignable_id' => $id];
                }
            }

            TerritoryAssignment::query()->insert($rows);

            return $territory;
        });
    }
}
