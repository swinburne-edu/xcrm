<?php

namespace App\Domain\Platform\Lookups;

use App\Domain\Shared\Support\Listing\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Async search source for <EntityPicker entity="...">, registered in config/lookups.php.
 * Responses are lists of { id, label, description }.
 */
abstract class EloquentLookup
{
    public const MAX_RESULTS = 20;

    /**
     * Base query, already limited to what the current user may pick.
     *
     * @param  array<string, mixed>  $params  extra query-string params (e.g. province_id for wards)
     * @return Builder<Model>
     */
    abstract protected function query(array $params): Builder;

    /** @return list<string> */
    abstract protected function searchColumns(): array;

    /**
     * @return array{id: int|string, label: string, description: string|null}
     */
    abstract protected function toItem(Model $model): array;

    /**
     * @return list<string>
     */
    protected function orderColumns(): array
    {
        return [$this->searchColumns()[0]];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array{id: int|string, label: string, description: string|null}>
     */
    public function search(string $term, array $params = []): array
    {
        $query = $this->query($params);
        $term = trim($term);

        if ($term !== '') {
            Like::anyColumn($query, $this->searchColumns(), mb_substr($term, 0, 100));
        }

        foreach ($this->orderColumns() as $column) {
            $query->orderBy($query->qualifyColumn($column));
        }

        return $query->limit(self::MAX_RESULTS)->get()->map(fn (Model $m) => $this->toItem($m))->all();
    }

    /**
     * Items for already-selected ids (to show their labels).
     *
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $params
     * @return list<array{id: int|string, label: string, description: string|null}>
     */
    public function find(array $ids, array $params = []): array
    {
        $query = $this->query($params);

        return $query
            ->whereIn($query->getModel()->getQualifiedKeyName(), array_slice($ids, 0, 100))
            ->get()
            ->map(fn (Model $m) => $this->toItem($m))
            ->all();
    }
}
