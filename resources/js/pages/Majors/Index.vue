<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import MajorFormDialog from '@/components/majors/MajorFormDialog.vue';
import type { MajorRow, Option } from '@/components/majors/MajorFormDialog.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

type Row = MajorRow & { cycle_quota: number | null };

const props = defineProps<{
    majors: ListResult<Row>;
    cycle: { id: number; name: string } | null;
    levelOptions: Option[];
    programTypeOptions: Option[];
    can: { create: boolean };
}>();

const query = useListQuery(() => props.majors, { only: ['majors'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, mã ngành…' },
    { key: 'faculty_id', label: 'Khoa', type: 'entity', entity: 'faculties' },
    { key: 'campus_id', label: 'Cơ sở', type: 'entity', entity: 'campuses' },
    { key: 'level', label: 'Bậc', type: 'select', options: props.levelOptions },
    {
        key: 'program_type',
        label: 'Chương trình',
        type: 'select',
        options: props.programTypeOptions,
    },
    {
        key: 'is_active',
        label: 'Trạng thái',
        type: 'boolean',
        trueLabel: 'Đang tuyển',
        falseLabel: 'Ngừng',
    },
]);

const columns = computed<CrmColumn<Row>[]>(() => [
    { accessorKey: 'admission_code', header: 'Mã xét tuyển', enableSorting: true },
    { accessorKey: 'name', header: 'Ngành', enableSorting: true, enableHiding: false },
    { accessorKey: 'faculty_name', header: 'Khoa' },
    { accessorKey: 'campus_name', header: 'Cơ sở' },
    { accessorKey: 'program_type_label', header: 'Chương trình' },
    { accessorKey: 'level_label', header: 'Bậc' },
    {
        accessorKey: 'cycle_quota',
        header: props.cycle ? `Chỉ tiêu ${props.cycle.name}` : 'Chỉ tiêu',
    },
    { accessorKey: 'is_active', header: 'Trạng thái' },
]);

const formOpen = ref(false);

function open(row: Row): void {
    router.visit(`/majors/${row.id}`);
}
</script>

<template>
    <Head title="Ngành" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Ngành</h1>
                <p class="text-sm text-muted-foreground">
                    Danh mục ngành. Chỉ tiêu, tổ hợp xét tuyển và phương thức
                    được cấu hình theo mùa ở trang chi tiết.
                </p>
            </div>
            <Button v-if="can.create" data-test="create" @click="formOpen = true">
                <Plus class="size-4" />
                Thêm ngành
            </Button>
        </div>

        <DataTable
            :list="majors"
            :query="query"
            :columns="columns"
            row-clickable
            storage-key="majors"
            @row-click="open"
        >
            <template #toolbar
                ><FilterBar :query="query" :filters="filters"
            /></template>
            <template #cell-name="{ row }">
                <Link :href="`/majors/${row.id}`" class="font-medium hover:underline">{{
                    row.name
                }}</Link>
                <div v-if="row.moet_code" class="text-xs text-muted-foreground">
                    Mã Bộ {{ row.moet_code }}
                </div>
            </template>
            <template #cell-faculty_name="{ row }">{{ row.faculty_name || '—' }}</template>
            <template #cell-campus_name="{ row }">{{ row.campus_name || '—' }}</template>
            <template #cell-cycle_quota="{ row }">{{ row.cycle_quota ?? '—' }}</template>
            <template #cell-is_active="{ row }">
                <ToneBadge :tone="row.is_active ? 'green' : 'gray'">{{
                    row.is_active ? 'Đang tuyển' : 'Ngừng'
                }}</ToneBadge>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0"
                    >Không có ngành phù hợp bộ lọc.</span
                >
                <span v-else
                    >Chưa có ngành nào. Tạo khoa và cơ sở ở Cài đặt → Danh mục trước
                    khi thêm ngành.</span
                >
            </template>
        </DataTable>
    </div>

    <MajorFormDialog
        v-model:open="formOpen"
        :record="null"
        :level-options="levelOptions"
        :program-type-options="programTypeOptions"
    />
</template>
