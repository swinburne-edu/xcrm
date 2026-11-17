<script setup lang="ts" generic="T extends { id: number | string }">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Columns3,
    LoaderCircle,
    X,
} from '@lucide/vue';
import { FlexRender, useTable } from '@tanstack/vue-table';
import type {
    ColumnVisibilityState,
    RowSelectionState,
    Updater,
} from '@tanstack/vue-table';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ListQuery } from '@/composables/useListQuery';
import {
    dataTableFeatures,
    sortStateToString,
    sortStringToState,
} from '@/lib/dataTable';
import type { CrmColumn } from '@/lib/dataTable';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ListResult } from '@/types';

/**
 * Server-driven data table (TanStack Table v9).
 *
 *   const query = useListQuery(() => props.applicants);
 *   <DataTable :list="applicants" :query="query" :columns="columns" selectable row-clickable
 *              storage-key="applicants" @row-click="open">
 *     <template #cell-full_name="{ row }">…</template>
 *     <template #bulk-actions="{ selectedIds, clear }">…</template>
 *   </DataTable>
 */
const props = withDefaults(
    defineProps<{
        list: ListResult<T>;
        columns: CrmColumn<T>[];
        query: ListQuery;
        selectable?: boolean;
        rowClickable?: boolean;
        /** Remembers hidden columns per table in this browser. */
        storageKey?: string;
        emptyText?: string;
        maxHeight?: string;
    }>(),
    {
        selectable: false,
        rowClickable: false,
        storageKey: undefined,
        emptyText: 'Không có dữ liệu phù hợp.',
        maxHeight: 'calc(100vh - 17rem)',
    },
);

const emit = defineEmits<{
    'row-click': [row: T];
    'selection-change': [ids: string[]];
}>();

const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const SELECT_COLUMN_ID = '__select';

function resolve<S>(updater: Updater<S>, current: S): S {
    return typeof updater === 'function'
        ? (updater as (old: S) => S)(current)
        : updater;
}

// Column visibility, remembered per table.
const visibilityKey = computed(() =>
    props.storageKey ? `xcrm:table:${props.storageKey}:columns` : null,
);

function loadVisibility(): ColumnVisibilityState {
    if (!visibilityKey.value) {
        return {};
    }

    try {
        return JSON.parse(localStorage.getItem(visibilityKey.value) ?? '{}');
    } catch {
        return {};
    }
}

function saveVisibility(state: ColumnVisibilityState): void {
    if (!visibilityKey.value) {
        return;
    }

    try {
        localStorage.setItem(visibilityKey.value, JSON.stringify(state));
    } catch {
        // Storage unavailable (private mode…): keep the in-memory state only.
    }
}

const data = computed(() => props.list.data);
const sorting = computed(() => sortStringToState(props.query.sort.value));
const rowSelection = ref<RowSelectionState>({});
const columnVisibility = ref<ColumnVisibilityState>(loadVisibility());

const allColumns = computed<CrmColumn<T>[]>(() => [
    ...(props.selectable
        ? [
              {
                  id: SELECT_COLUMN_ID,
                  header: '',
                  enableSorting: false,
                  enableHiding: false,
                  meta: { class: 'w-10' },
              } as CrmColumn<T>,
          ]
        : []),
    // Sorting is opt-in: only columns the server allows set enableSorting: true.
    ...props.columns.map((column) => ({ enableSorting: false, ...column })),
]);

const table = useTable({
    features: dataTableFeatures,
    columns: allColumns,
    data,
    getRowId: (row: T) => String(row.id),
    manualSorting: true,
    enableMultiSort: false,
    sortDescFirst: false,
    state: computed(() => ({
        sorting: sorting.value,
        rowSelection: rowSelection.value,
        columnVisibility: columnVisibility.value,
    })),
    onSortingChange: (updater) => {
        props.query.setSort(sortStateToString(resolve(updater, sorting.value)));
    },
    onRowSelectionChange: (updater) => {
        rowSelection.value = resolve(updater, rowSelection.value);
    },
    onColumnVisibilityChange: (updater) => {
        columnVisibility.value = resolve(updater, columnVisibility.value);
        saveVisibility(columnVisibility.value);
    },
});

const selectedIds = computed(() =>
    Object.entries(rowSelection.value)
        .filter(([, selected]) => selected)
        .map(([id]) => id),
);

watch(selectedIds, (ids) => emit('selection-change', ids));

// A new filter/sort means a different result set: don't keep acting on rows the user can no longer see.
watch(
    () => JSON.stringify([props.list.query.filters, props.list.query.sort]),
    () => clearSelection(),
);

function clearSelection(): void {
    rowSelection.value = {};
}

const hideableColumns = computed(() =>
    table.getAllLeafColumns().filter((column) => column.getCanHide()),
);

function columnLabel(column: (typeof hideableColumns.value)[number]): string {
    const header = column.columnDef.header;

    return typeof header === 'string' && header !== ''
        ? header
        : (column.columnDef.meta?.label ?? column.id);
}

const pageCheckboxState = computed<boolean | 'indeterminate'>(() => {
    if (table.getIsAllPageRowsSelected()) {
        return true;
    }

    return table.getIsSomePageRowsSelected() ? 'indeterminate' : false;
});

const meta = computed(() => props.list.meta);
const visibleColumnCount = computed(() => table.getVisibleLeafColumns().length);

function onRowClick(row: T, event: MouseEvent): void {
    if (!props.rowClickable) {
        return;
    }

    // Ignore clicks on interactive children (links, buttons, checkboxes).
    if ((event.target as HTMLElement).closest('a, button, [role="checkbox"]')) {
        return;
    }

    emit('row-click', row);
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-2" data-test="data-table">
        <!-- Toolbar: bulk actions or caller toolbar + column menu -->
        <div class="flex min-h-9 flex-wrap items-center gap-2">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <slot name="toolbar" />
            </div>

            <DropdownMenu v-if="hideableColumns.length > 0">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="sm" class="h-8">
                        <Columns3 class="size-4" />
                        <span class="hidden sm:inline">Cột</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuLabel>Hiển thị cột</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                        v-for="column in hideableColumns"
                        :key="column.id"
                        :model-value="column.getIsVisible()"
                        @update:model-value="
                            (value) => column.toggleVisibility(!!value)
                        "
                        @select.prevent
                    >
                        {{ columnLabel(column) }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Bulk actions for the selected rows -->
        <div
            v-if="selectable && selectedIds.length > 0"
            class="flex flex-wrap items-center gap-2 rounded-md bg-muted px-3 py-1.5 text-sm"
            data-test="bulk-actions"
        >
            <span class="font-medium"
                >Đã chọn {{ formatNumber(selectedIds.length) }}</span
            >
            <Button
                variant="ghost"
                size="sm"
                class="h-7 px-2"
                @click="clearSelection"
            >
                <X class="size-3.5" />
                Bỏ chọn
            </Button>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <slot
                    name="bulk-actions"
                    :selected-ids="selectedIds"
                    :clear="clearSelection"
                />
            </div>
        </div>

        <!-- Table -->
        <div
            class="relative overflow-auto rounded-md border"
            :style="{ maxHeight }"
        >
            <table class="w-full caption-bottom text-sm">
                <thead
                    class="sticky top-0 z-10 bg-background shadow-[inset_0_-1px_0] shadow-border"
                >
                    <tr
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <th
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :class="
                                cn(
                                    'h-9 px-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground',
                                    header.column.columnDef.meta?.class,
                                )
                            "
                        >
                            <Checkbox
                                v-if="header.column.id === SELECT_COLUMN_ID"
                                :model-value="pageCheckboxState"
                                aria-label="Chọn tất cả trên trang"
                                @update:model-value="
                                    (value) =>
                                        table.toggleAllPageRowsSelected(!!value)
                                "
                            />
                            <button
                                v-else-if="header.column.getCanSort()"
                                type="button"
                                class="-ml-1.5 inline-flex items-center gap-1 rounded px-1.5 py-1 hover:bg-accent hover:text-accent-foreground"
                                @click="
                                    header.column.getToggleSortingHandler()?.(
                                        $event,
                                    )
                                "
                            >
                                <FlexRender :header="header" />
                                <ArrowUp
                                    v-if="header.column.getIsSorted() === 'asc'"
                                    class="size-3.5 text-foreground"
                                />
                                <ArrowDown
                                    v-else-if="
                                        header.column.getIsSorted() === 'desc'
                                    "
                                    class="size-3.5 text-foreground"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="size-3.5 opacity-40"
                                />
                            </button>
                            <FlexRender
                                v-else-if="!header.isPlaceholder"
                                :header="header"
                            />
                        </th>
                    </tr>
                </thead>
                <tbody
                    :class="{
                        'opacity-60 transition-opacity': query.loading.value,
                    }"
                >
                    <tr
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        :data-state="
                            row.getIsSelected() ? 'selected' : undefined
                        "
                        :class="
                            cn(
                                'border-b transition-colors last:border-0 hover:bg-muted/50 data-[state=selected]:bg-muted',
                                rowClickable && 'cursor-pointer',
                            )
                        "
                        @click="onRowClick(row.original, $event)"
                    >
                        <td
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                            :class="
                                cn(
                                    'px-3 py-2 align-middle',
                                    cell.column.columnDef.meta?.class,
                                )
                            "
                        >
                            <Checkbox
                                v-if="cell.column.id === SELECT_COLUMN_ID"
                                :model-value="row.getIsSelected()"
                                aria-label="Chọn dòng"
                                @update:model-value="
                                    (value) => row.toggleSelected(!!value)
                                "
                            />
                            <slot
                                v-else
                                :name="`cell-${cell.column.id}`"
                                :row="row.original"
                                :value="cell.getValue()"
                            >
                                <FlexRender :cell="cell" />
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="table.getRowModel().rows.length === 0">
                        <td
                            :colspan="visibleColumnCount"
                            class="h-28 text-center text-muted-foreground"
                        >
                            <slot name="empty">{{ emptyText }}</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
            data-test="pagination"
        >
            <div class="flex items-center gap-2">
                <LoaderCircle
                    v-if="query.loading.value"
                    class="size-4 animate-spin"
                />
                <span v-if="meta.total > 0">
                    {{ formatNumber(meta.from) }}–{{ formatNumber(meta.to) }} /
                    {{ formatNumber(meta.total) }} bản ghi
                </span>
                <span v-else>0 bản ghi</span>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline">Số dòng</span>
                    <Select
                        :model-value="String(query.perPage.value)"
                        @update:model-value="
                            (value) => query.setPerPage(Number(value))
                        "
                    >
                        <SelectTrigger size="sm" class="h-8 w-[4.5rem]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in PER_PAGE_OPTIONS"
                                :key="option"
                                :value="String(option)"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <span class="whitespace-nowrap"
                    >Trang {{ meta.current_page }} /
                    {{ Math.max(meta.last_page, 1) }}</span
                >
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Trang trước"
                        :disabled="
                            meta.current_page <= 1 || query.loading.value
                        "
                        @click="query.setPage(meta.current_page - 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Trang sau"
                        :disabled="
                            meta.current_page >= meta.last_page ||
                            query.loading.value
                        "
                        @click="query.setPage(meta.current_page + 1)"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
