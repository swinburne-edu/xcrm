<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import CycleFormDialog from '@/components/cycles/CycleFormDialog.vue';
import type { CycleRow, Option } from '@/components/cycles/types';
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
    cycles: ListResult<CycleRow>;
    levelOptions: Option[];
    statusOptions: Option[];
    can: { create: boolean };
}>();

const query = useListQuery(() => props.cycles, { only: ['cycles'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên mùa…' },
    {
        key: 'status',
        label: 'Trạng thái',
        type: 'select',
        options: props.statusOptions,
    },
    {
        key: 'level',
        label: 'Bậc',
        type: 'select',
        options: props.levelOptions,
    },
]);

const columns: CrmColumn<CycleRow>[] = [
    { accessorKey: 'name', header: 'Mùa', enableSorting: true, enableHiding: false },
    { accessorKey: 'year', header: 'Năm', enableSorting: true },
    { accessorKey: 'level_label', header: 'Bậc' },
    { accessorKey: 'status', header: 'Trạng thái', enableSorting: true },
    { accessorKey: 'methods_count', header: 'Phương thức' },
    { accessorKey: 'rounds_count', header: 'Đợt' },
    { accessorKey: 'target_enrolled', header: 'Chỉ tiêu' },
    { id: 'actions', header: '', enableHiding: false, meta: { class: 'w-12 text-right' } },
];

const tones: Record<string, BadgeTone> = {
    preparing: 'amber',
    active: 'green',
    closed: 'gray',
};

const formOpen = ref(false);
const deleteOpen = ref(false);
const deleting = ref<CycleRow | null>(null);

function openCreate(): void {
    formOpen.value = true;
}

function open(row: CycleRow): void {
    router.visit(`/settings/cycles/${row.id}`);
}

function setCurrent(row: CycleRow): void {
    router.post(`/settings/cycles/${row.id}/current`, {}, { preserveScroll: true });
}

function askDelete(row: CycleRow): void {
    deleting.value = row;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(`/settings/cycles/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}
</script>

<template>
    <Head title="Mùa tuyển sinh" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Mùa tuyển sinh</h1>
                <p class="text-sm text-muted-foreground">
                    Mỗi mùa có phương thức xét tuyển, đợt và mốc thời gian riêng.
                    Mùa hiện tại được chọn mặc định ở thanh trên cùng; mùa đã
                    đóng chỉ đọc.
                </p>
            </div>
            <Button v-if="can.create" data-test="create" @click="openCreate">
                <Plus class="size-4" />
                Thêm mùa
            </Button>
        </div>

        <DataTable
            :list="cycles"
            :query="query"
            :columns="columns"
            row-clickable
            storage-key="settings-cycles"
            @row-click="open"
        >
            <template #toolbar
                ><FilterBar :query="query" :filters="filters"
            /></template>
            <template #cell-name="{ row }">
                <Link
                    :href="`/settings/cycles/${row.id}`"
                    class="font-medium hover:underline"
                    >{{ row.name }}</Link
                >
                <ToneBadge v-if="row.is_current" tone="blue" class="ml-2"
                    >Hiện tại</ToneBadge
                >
            </template>
            <template #cell-status="{ row }">
                <ToneBadge :tone="tones[row.status] ?? 'gray'">{{
                    row.status_label
                }}</ToneBadge>
            </template>
            <template #cell-target_enrolled="{ row }">{{
                row.target_enrolled ?? '—'
            }}</template>
            <template #cell-actions="{ row }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thao tác"
                            @click.stop
                            ><MoreHorizontal class="size-4"
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="open(row)"
                            ><Pencil class="size-4" />Mở chi tiết</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="can.create && !row.is_current && row.status !== 'closed'"
                            @select="setCurrent(row)"
                            ><Star class="size-4" />Đặt làm mùa hiện
                            tại</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="can.create && row.status === 'preparing' && !row.is_current"
                            class="text-destructive focus:text-destructive"
                            @select="askDelete(row)"
                            ><Trash2 class="size-4" />Xoá</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0"
                    >Không có mùa phù hợp bộ lọc.</span
                >
                <span v-else>Chưa có mùa tuyển sinh nào.</span>
            </template>
        </DataTable>
    </div>

    <CycleFormDialog
        v-model:open="formOpen"
        :record="null"
        :level-options="levelOptions"
        :status-options="statusOptions"
    />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>
                    Chỉ xoá được mùa đang chuẩn bị và chưa có dữ liệu phát sinh.
                    Cấu hình đợt và phương thức của mùa sẽ mất.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
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
