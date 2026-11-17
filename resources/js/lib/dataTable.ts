import type { ColumnDef, RowData, SortingState } from '@tanstack/vue-table';
import {
    columnVisibilityFeature,
    rowSelectionFeature,
    rowSortingFeature,
    tableFeatures,
} from '@tanstack/vue-table';

/**
 * TanStack Table v9 features used by <DataTable>. Sorting and pagination are
 * server-side (manualSorting / manualPagination), so no client row models.
 */
export const dataTableFeatures = tableFeatures({
    rowSortingFeature,
    rowSelectionFeature,
    columnVisibilityFeature,
});

export type DataTableFeatures = typeof dataTableFeatures;

/**
 * Column definition for <DataTable>.
 * - `id` / `accessorKey` is also the server sort key when `enableSorting: true`.
 * - `meta.label` names the column in the "Cột" menu when `header` is not a string.
 * - Custom cells: use the `#cell-{columnId}="{ row, value }"` slot of <DataTable>.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type CrmColumn<T extends RowData> = ColumnDef<DataTableFeatures, T, any>;

declare module '@tanstack/table-core' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TFeatures, TData, TValue> {
        label?: string;
        /** Extra classes for header and cells (e.g. 'w-32 text-right'). */
        class?: string;
    }
}

/** "-created_at,name" ⇄ [{ id: 'created_at', desc: true }, { id: 'name', desc: false }] */
export function sortStringToState(sort: string | null): SortingState {
    if (!sort) {
        return [];
    }

    return sort
        .split(',')
        .filter(Boolean)
        .map((token) => ({
            id: token.replace(/^-/, ''),
            desc: token.startsWith('-'),
        }));
}

export function sortStateToString(state: SortingState): string | null {
    if (state.length === 0) {
        return null;
    }

    return state.map((s) => `${s.desc ? '-' : ''}${s.id}`).join(',');
}
