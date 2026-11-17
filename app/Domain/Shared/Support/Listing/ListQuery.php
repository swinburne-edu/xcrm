<?php

namespace App\Domain\Shared\Support\Listing;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared list contract for every resource screen (docs/06-giao-dien.md):
 *
 *   GET /{resource}?filter[status]=a,b&filter[q]=abc&filter[created_at][from]=2026-01-01
 *                  &sort=-created_at,name&page=2&per_page=25
 *
 * Only declared filters/sorts are applied; anything else in the query string is ignored.
 *
 *   return Inertia::render('Applicant/Index', [
 *       'applicants' => ListQuery::for(Applicant::query(), $request)
 *           ->filters(Filter::search('q', ['full_name', 'phone']), Filter::exact('gender'))
 *           ->sorts(['full_name', 'created_at'])
 *           ->defaultSort('-created_at')
 *           ->toProps(fn (Applicant $a) => [...]),
 *   ]);
 */
final class ListQuery
{
    public const DEFAULT_PER_PAGE = 25;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const MAX_SORT_FIELDS = 3;

    /** @var array<string, Filter> */
    private array $filters = [];

    /** @var array<string, string|Closure(Builder, string): void> */
    private array $sorts = [];

    private ?string $defaultSort = null;

    /** @var array<string, mixed> */
    private array $appliedFilters = [];

    /** @var list<string> */
    private array $appliedSorts = [];

    private bool $applied = false;

    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<Model>|class-string<Model>  $query
     */
    public static function for(Builder|string $query, ?Request $request = null): self
    {
        $builder = is_string($query) ? $query::query() : $query;

        return new self($builder, $request ?? request());
    }

    public function filters(Filter ...$filters): self
    {
        foreach ($filters as $filter) {
            $this->filters[$filter->name] = $filter;
        }

        return $this;
    }

    /**
     * Allowed sort keys. A plain item sorts by the same column; a keyed item maps
     * the key to another column or to a Closure(Builder $query, string $direction).
     *
     * @param  array<int|string, string|Closure(Builder, string): void>  $sorts
     */
    public function sorts(array $sorts): self
    {
        foreach ($sorts as $key => $sort) {
            is_int($key) ? $this->sorts[$sort] = $sort : $this->sorts[$key] = $sort;
        }

        return $this;
    }

    /** e.g. "-created_at" (newest first) */
    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    /**
     * The query with filters and sorts applied (for exports, counts, bulk actions…).
     *
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        if (! $this->applied) {
            $this->applyFilters();
            $this->applySorts();
            $this->applied = true;
        }

        return $this->query;
    }

    public function paginate(): LengthAwarePaginator
    {
        $page = max(1, (int) $this->request->query('page', '1'));

        return $this->query()
            ->paginate($this->perPage(), ['*'], 'page', $page)
            ->withPath($this->request->url())
            ->appends($this->request->query());
    }

    /**
     * Props for the shared DataTable: { data, meta, query }.
     *
     * @param  (callable(mixed): array<string, mixed>)|null  $map
     * @return array{data: list<mixed>, meta: array<string, int|null>, query: array<string, mixed>}
     */
    public function toProps(?callable $map = null): array
    {
        $paginator = $this->paginate();
        $items = $paginator->getCollection();

        return [
            'data' => ($map ? $items->map($map) : $items)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'query' => $this->state(),
        ];
    }

    /**
     * Normalized request state echoed back to the UI (filters, sort, per_page).
     *
     * @return array{filters: array<string, mixed>, sort: string|null, per_page: int}
     */
    public function state(): array
    {
        $this->query();

        return [
            'filters' => $this->appliedFilters,
            'sort' => $this->appliedSorts === [] ? null : implode(',', $this->appliedSorts),
            'per_page' => $this->perPage(),
        ];
    }

    public function perPage(): int
    {
        $perPage = (int) $this->request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    private function applyFilters(): void
    {
        $input = $this->request->query('filter', []);

        if (! is_array($input)) {
            return;
        }

        foreach ($this->filters as $name => $filter) {
            if (! array_key_exists($name, $input)) {
                continue;
            }

            $value = $filter->normalize($input[$name]);

            if ($value === null) {
                continue;
            }

            $filter->apply($this->query, $value);
            $this->appliedFilters[$name] = $value;
        }
    }

    private function applySorts(): void
    {
        $requested = $this->request->query('sort');
        $requested = is_string($requested) && $requested !== '' ? $requested : $this->defaultSort;

        foreach (array_slice(explode(',', (string) $requested), 0, self::MAX_SORT_FIELDS) as $token) {
            $token = trim($token);
            $direction = str_starts_with($token, '-') ? 'desc' : 'asc';
            $key = ltrim($token, '-');

            if ($key === '' || ! isset($this->sorts[$key]) || in_array($key, array_map(fn ($s) => ltrim($s, '-'), $this->appliedSorts), true)) {
                continue;
            }

            $sort = $this->sorts[$key];
            $sort instanceof Closure
                ? $sort($this->query, $direction)
                : $this->query->orderBy($this->query->qualifyColumn($sort), $direction);

            $this->appliedSorts[] = ($direction === 'desc' ? '-' : '').$key;
        }

        // Stable order across pages when sort values tie.
        $this->query->orderBy($this->query->getModel()->getQualifiedKeyName(), 'desc');
    }
}
