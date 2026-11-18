<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import TerritoryFormDialog from '@/components/settings/TerritoryFormDialog.vue';
import type {
    TerritoryRow,
    TerritoryTarget,
} from '@/components/settings/TerritoryFormDialog.vue';
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
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    territories: ListResult<TerritoryRow>;
    targets: TerritoryTarget[];
    can: { manage: boolean };
}>();

const query = useListQuery(() => props.territories, { only: ['territories'] });

const filters: FilterDefinition[] = [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên vùng…' },
    {
        key: 'user_id',
        label: 'Người phụ trách',
        type: 'entity',
        entity: 'users',
    },
    { key: 'team_id', label: 'Nhóm', type: 'entity', entity: 'teams' },
    {
        key: 'province_id',
        label: 'Tỉnh/Thành phố',
        type: 'entity',
        entity: 'provinces',
    },
];

const columns: CrmColumn<TerritoryRow>[] = [
    {
        accessorKey: 'name',
        header: 'Vùng',
        enableSorting: true,
        enableHiding: false,
    },
    { accessorKey: 'user_name', header: 'Người phụ trách' },
    { accessorKey: 'team_name', header: 'Nhóm' },
    { accessorKey: 'scope_label', header: 'Tỉnh/Thành phố' },
    { accessorKey: 'high_schools_count', header: 'Trường THPT' },
    {
        id: 'actions',
        header: '',
        enableHiding: false,
        meta: { class: 'w-12 text-right' },
    },
];

const formOpen = ref(false);
const editing = ref<TerritoryRow | null>(null);
const deleting = ref<TerritoryRow | null>(null);
const deleteOpen = ref(false);

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(row: TerritoryRow): void {
    if (props.can.manage) {
        editing.value = row;
        formOpen.value = true;
    }
}

function askDelete(row: TerritoryRow): void {
    deleting.value = row;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(`/settings/territories/${deleting.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (deleteOpen.value = false),
    });
}
</script>

<template>
    <Head title="Vùng phụ trách" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Vùng phụ trách</h1>
                <p class="text-sm text-muted-foreground">
                    Gán tỉnh/thành phố (và trường THPT) cho người hoặc nhóm phụ
                    trách. Hồ sơ mới được tự giao theo vùng; người dùng có phạm
                    vi "Theo vùng phụ trách" chỉ thấy dữ liệu trong vùng của
                    mình.
                </p>
            </div>
            <Button v-if="can.manage" data-test="create" @click="openCreate">
                <Plus class="size-4" />
                Thêm vùng
            </Button>
        </div>

        <DataTable
            :list="territories"
            :query="query"
            :columns="columns"
            :row-clickable="can.manage"
            storage-key="settings-territories"
            @row-click="openEdit"
        >
            <template #toolbar
                ><FilterBar :query="query" :filters="filters"
            /></template>
            <template #cell-name="{ row }"
                ><span class="font-medium">{{ row.name }}</span></template
            >
            <template #cell-user_name="{ row }">{{
                row.user_name || '—'
            }}</template>
            <template #cell-team_name="{ row }">
                <ToneBadge v-if="row.team_name" tone="blue">{{
                    row.team_name
                }}</ToneBadge>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-scope_label="{ row }">
                <span v-if="row.provinces_count">{{ row.scope_label }}</span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-high_schools_count="{ row }">{{
                row.high_schools_count || '—'
            }}</template>
            <template #cell-actions="{ row }">
                <DropdownMenu v-if="can.manage">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thao tác"
                            ><MoreHorizontal class="size-4"
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="openEdit(row)"
                            ><Pencil class="size-4" />Sửa</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="askDelete(row)"
                            ><Trash2 class="size-4" />Xoá</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0"
                    >Không có vùng phù hợp bộ lọc.</span
                >
                <span v-else
                    >Chưa có vùng phụ trách nào. Hồ sơ của tỉnh chưa gán vùng sẽ
                    vào kho chung.</span
                >
            </template>
        </DataTable>
    </div>

    <TerritoryFormDialog
        v-model:open="formOpen"
        :record="editing"
        :targets="targets"
    />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá vùng {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>
                    Các tỉnh/trường trong vùng được giải phóng để gán cho vùng
                    khác. Hồ sơ đã giao không bị đổi người phụ trách.
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
