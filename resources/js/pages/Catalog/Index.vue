<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import CatalogFormDialog from '@/components/catalog/CatalogFormDialog.vue';
import type {
    CatalogColumn,
    CatalogRow,
    CatalogSchema,
} from '@/components/catalog/types';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ListResult } from '@/types';

const props = defineProps<{
    tabs: { key: string; title: string }[];
    catalog: CatalogSchema;
    items: ListResult<CatalogRow>;
    can: { manage: boolean };
}>();

const query = useListQuery(() => props.items, { only: ['items'] });

function renderCell(column: CatalogColumn, value: unknown) {
    switch (column.type) {
        case 'code':
            return h(
                'span',
                { class: 'font-mono text-xs font-medium' },
                String(value ?? ''),
            );
        case 'number':
            return value === null || value === undefined
                ? '—'
                : formatNumber(value as number);
        case 'boolean':
            return h(ToneBadge, { tone: value ? 'green' : 'gray' }, () =>
                value
                    ? (column.trueLabel ?? 'Đang dùng')
                    : (column.falseLabel ?? 'Ngừng dùng'),
            );
        case 'badge':
            return value
                ? h(ToneBadge, { tone: 'blue' }, () => String(value))
                : '—';
        default:
            return value === null || value === undefined || value === ''
                ? '—'
                : String(value);
    }
}

const columns = computed<CrmColumn<CatalogRow>[]>(() => [
    ...props.catalog.columns.map((column): CrmColumn<CatalogRow> => ({
        id: column.key,
        accessorKey: column.key,
        header: column.label,
        enableSorting: column.sortable ?? false,
        cell: (ctx) => renderCell(column, ctx.getValue()),
        meta: {
            class: column.type === 'number' ? 'text-right' : undefined,
        },
    })),
    ...(props.can.manage
        ? [
              {
                  id: 'actions',
                  header: '',
                  enableHiding: false,
                  meta: { class: 'w-12 text-right' },
              } as CrmColumn<CatalogRow>,
          ]
        : []),
]);

// --- Create / edit ---
const formOpen = ref(false);
const editing = ref<CatalogRow | null>(null);

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(row: CatalogRow): void {
    if (!props.can.manage) {
        return;
    }

    editing.value = row;
    formOpen.value = true;
}

// --- Delete ---
const deleting = ref<CatalogRow | null>(null);
const deleteOpen = ref(false);

function askDelete(row: CatalogRow): void {
    deleting.value = row;
    deleteOpen.value = true;
}

function rowLabel(row: CatalogRow | null): string {
    if (!row) {
        return '';
    }

    return [row.code, row.name ?? row.old_ward_name]
        .filter(Boolean)
        .join(' – ');
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(
        `/settings/catalogs/${props.catalog.key}/${deleting.value.id}`,
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                deleteOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`${catalog.title} – Danh mục`" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Danh mục hệ thống</h1>
                <p class="text-sm text-muted-foreground">
                    Dữ liệu dùng chung cho toàn hệ thống: địa giới, ưu tiên,
                    môn, tổ hợp, phương thức xét tuyển.
                </p>
            </div>
            <Button
                v-if="can.manage"
                data-test="catalog-create"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Thêm
            </Button>
        </div>

        <!-- Tabs: one page per catalog -->
        <nav
            class="-mx-1 flex gap-1 overflow-x-auto border-b px-1"
            aria-label="Danh mục"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="`/settings/catalogs/${tab.key}`"
                :class="
                    cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                        tab.key === catalog.key
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )
                "
                :aria-current="tab.key === catalog.key ? 'page' : undefined"
            >
                {{ tab.title }}
            </Link>
        </nav>

        <p v-if="catalog.description" class="text-sm text-muted-foreground">
            {{ catalog.description }}
        </p>

        <DataTable
            :key="catalog.key"
            :list="items"
            :query="query"
            :columns="columns"
            :row-clickable="can.manage"
            :storage-key="`catalog-${catalog.key}`"
            @row-click="openEdit"
        >
            <template #toolbar>
                <FilterBar :query="query" :filters="catalog.filters" />
            </template>
            <template #cell-actions="{ row }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thao tác"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="openEdit(row)">
                            <Pencil class="size-4" />
                            Sửa
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="askDelete(row)"
                        >
                            <Trash2 class="size-4" />
                            Xoá
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0"
                    >Không có bản ghi phù hợp bộ lọc.</span
                >
                <span v-else>Chưa có dữ liệu trong danh mục này.</span>
            </template>
        </DataTable>
    </div>

    <CatalogFormDialog
        v-model:open="formOpen"
        :catalog="catalog"
        :record="editing"
    />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ rowLabel(deleting) }}?</DialogTitle>
                <DialogDescription>
                    Không thể hoàn tác. Nếu bản ghi đang được dùng ở nơi khác,
                    hệ thống sẽ không cho xoá — khi đó hãy chuyển sang ngừng sử
                    dụng.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false"
                    >Huỷ</Button
                >
                <Button
                    variant="destructive"
                    data-test="confirm-delete"
                    @click="confirmDelete"
                    >Xoá</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
