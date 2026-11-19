<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Models\SavedView;
use App\Models\User;

/**
 * Creates or edits a saved view (validated and authorized by SavedViewRequest). The config is rebuilt from the
 * known parts only: filters (strings, booleans, lists, date ranges), sort, page size and column visibility.
 * A personal view belongs to its author; a shared view has no owner (user_id null) and everyone in the organization sees it.
 */
class SaveSavedView
{
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?SavedView $view, array $data, User $by): SavedView
    {
        $view ??= new SavedView;

        if (! $view->exists) {
            $view->object = SavedViewObject::from($data['object']);
            $view->sort_order = ((int) SavedView::query()->where('object', $view->object->value)->max('sort_order')) + 1;
        }

        $shared = (bool) ($data['is_shared'] ?? false);
        $view->name = trim($data['name']);
        $view->is_shared = $shared;
        $view->user_id = $shared ? null : $by->id;
        $view->view_type = $data['view_type'] ?? $view->view_type;

        if (array_key_exists('config', $data)) {
            $view->config = $this->config((array) $data['config']);
        }

        $view->save();

        return $view;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{filters: array<string, mixed>, sort: string|null, per_page: int|null, columns: array<string, bool>|null}
     */
    private function config(array $input): array
    {
        $perPage = (int) ($input['per_page'] ?? 0);
        $sort = $input['sort'] ?? null;
        $columns = is_array($input['columns'] ?? null) ? $input['columns'] : null;

        return [
            'filters' => $this->filters((array) ($input['filters'] ?? [])),
            'sort' => is_string($sort) && preg_match('/^-?[a-z_]+(,-?[a-z_]+){0,2}$/', $sort) ? $sort : null,
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : null,
            'columns' => $columns === null ? null : $this->columns($columns),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        $clean = [];

        foreach (array_slice($filters, 0, 40, true) as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_]{1,80}$/', $key)) {
                continue;
            }

            $value = match (true) {
                is_bool($value) => $value,
                is_scalar($value) => mb_substr((string) $value, 0, 200),
                is_array($value) && array_is_list($value) => array_values(array_map(fn ($v) => mb_substr((string) $v, 0, 200), array_filter(array_slice($value, 0, 100), 'is_scalar'))),
                is_array($value) => array_filter(array_intersect_key(array_map(fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null, $value), ['from' => 1, 'to' => 1])),
                default => null,
            };

            if ($value !== null && $value !== '' && $value !== []) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $columns
     * @return array<string, bool>
     */
    private function columns(array $columns): array
    {
        $clean = [];

        foreach (array_slice($columns, 0, 60, true) as $key => $visible) {
            if (is_string($key) && preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $key)) {
                $clean[$key] = (bool) $visible;
            }
        }

        return $clean;
    }
}
