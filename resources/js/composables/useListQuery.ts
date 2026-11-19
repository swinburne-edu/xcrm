import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type {
    DateRangeValue,
    FilterValue,
    ListQueryState,
    ListResult,
} from '@/types';

const DEFAULT_PER_PAGE = 25;

export type ListQuery = ReturnType<typeof useListQuery>;

function normalizeFilters(
    filters: ListQueryState['filters'] | undefined,
): Record<string, FilterValue> {
    return filters && !Array.isArray(filters) ? { ...filters } : {};
}

function isEmpty(value: FilterValue | null | undefined): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    if (typeof value === 'object') {
        const range = value as DateRangeValue;

        return !range.from && !range.to;
    }

    return false;
}

function serializeFilter(value: FilterValue): string | DateRangeValue {
    if (Array.isArray(value)) {
        return value.join(',');
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'object') {
        const range = value as DateRangeValue;

        return {
            ...(range.from ? { from: range.from } : {}),
            ...(range.to ? { to: range.to } : {}),
        };
    }

    return value;
}

/**
 * Query-string params of a list state: ?filter[key]=…&sort=…&page=…&per_page=… (empty parts left out).
 * Also used to apply a saved view (task 2.6).
 */
export function buildListParams(
    filters: Record<string, FilterValue>,
    sort: string | null,
    page = 1,
    perPage: number = DEFAULT_PER_PAGE,
): Record<string, unknown> {
    const filter: Record<string, string | DateRangeValue> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (!isEmpty(value)) {
            filter[key] = serializeFilter(value);
        }
    }

    return {
        ...(Object.keys(filter).length > 0 ? { filter } : {}),
        ...(sort ? { sort } : {}),
        ...(page > 1 ? { page } : {}),
        ...(perPage !== DEFAULT_PER_PAGE ? { per_page: perPage } : {}),
    };
}

/**
 * Server-side list state (filters, sort, page, per_page) for one ListResult prop.
 * Changes re-visit the current URL with the shared query-string contract:
 *   ?filter[key]=...&sort=-created_at&page=2&per_page=50
 *
 * @param list getter returning the page prop, e.g. () => props.applicants
 * @param options.only partial reload keys (defaults to every prop)
 */
export function useListQuery<T>(
    list: () => ListResult<T>,
    options: { only?: string[] } = {},
) {
    const filters = ref<Record<string, FilterValue>>(
        normalizeFilters(list().query.filters),
    );
    const sort = ref<string | null>(list().query.sort);
    const perPage = ref<number>(list().query.per_page ?? DEFAULT_PER_PAGE);
    const page = ref<number>(list().meta.current_page);
    const loading = ref(false);

    // Keep local state in sync with what the server actually applied.
    watch(
        () => [list().query, list().meta.current_page] as const,
        ([query, currentPage]) => {
            filters.value = normalizeFilters(query.filters);
            sort.value = query.sort;
            perPage.value = query.per_page ?? DEFAULT_PER_PAGE;
            page.value = currentPage;
        },
    );

    const activeFilterCount = computed(
        () =>
            Object.values(filters.value).filter((value) => !isEmpty(value))
                .length,
    );

    function params(): Record<string, unknown> {
        return buildListParams(filters.value, sort.value, page.value, perPage.value);
    }

    function visit(): void {
        router.get(window.location.pathname, params() as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...(options.only ? { only: options.only } : {}),
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        });
    }

    function setFilter(key: string, value: FilterValue | null): void {
        const next = { ...filters.value };

        if (value === null || isEmpty(value)) {
            delete next[key];
        } else {
            next[key] = value;
        }

        filters.value = next;
        page.value = 1;
        visit();
    }

    function resetFilters(): void {
        filters.value = {};
        page.value = 1;
        visit();
    }

    function setSort(value: string | null): void {
        sort.value = value;
        page.value = 1;
        visit();
    }

    function setPage(value: number): void {
        page.value = value;
        visit();
    }

    function setPerPage(value: number): void {
        perPage.value = value;
        page.value = 1;
        visit();
    }

    return {
        filters,
        sort,
        page,
        perPage,
        loading,
        activeFilterCount,
        setFilter,
        resetFilters,
        setSort,
        setPage,
        setPerPage,
    };
}
