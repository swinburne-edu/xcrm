<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import TierBadge from '@/components/crm/TierBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import SchoolFormDialog from '@/components/highschools/SchoolFormDialog.vue';
import SchoolImportDialog from '@/components/highschools/SchoolImportDialog.vue';
import type { SchoolOptions, SchoolRow } from '@/components/highschools/types';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    schools: ListResult<SchoolRow>;
    options: SchoolOptions;
    can: { create: boolean; import: boolean };
}>();

const query = useListQuery(() => props.schools, { only: ['schools'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, mã trường…' },
    { key: 'province_id', label: 'Tỉnh/Thành phố', type: 'entity', entity: 'provinces' },
    { key: 'tier', label: 'Hạng', type: 'select', options: props.options.tiers },
    { key: 'partnership_status', label: 'Hợp tác', type: 'select', options: props.options.partnership },
    { key: 'type', label: 'Loại hình', type: 'select', options: props.options.types },
    { key: 'owner_id', label: 'Người phụ trách', type: 'entity', entity: 'users' },
]);

const columns: CrmColumn<SchoolRow>[] = [
    { accessorKey: 'code', header: 'Mã', enableSorting: true },
    { accessorKey: 'name', header: 'Trường', enableSorting: true, enableHiding: false },
    { accessorKey: 'province_name', header: 'Tỉnh/Thành phố' },
    { accessorKey: 'tier', header: 'Hạng', enableSorting: true },
    { accessorKey: 'partnership_label', header: 'Hợp tác' },
    { accessorKey: 'grade12_students', header: 'HS khối 12', enableSorting: true },
    { accessorKey: 'contacts_count', header: 'Liên hệ' },
    { accessorKey: 'owner_name', header: 'Phụ trách' },
];

const tones: Record<string, BadgeTone> = {
    not_contacted: 'gray',
    contacting: 'amber',
    partner: 'blue',
    mou_signed: 'green',
};

const formOpen = ref(false);
const importOpen = ref(false);

function open(row: SchoolRow): void {
    router.visit(`/high-schools/${row.id}`);
}
</script>

<template>
    <Head title="Danh sách trường THPT" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Trường THPT</h1>
                <p class="text-sm text-muted-foreground">
                    Danh sách trường, hạng tiềm năng, trạng thái hợp tác và người phụ trách. Bạn chỉ thấy các trường trong phạm vi dữ liệu của mình.
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.import" variant="outline" data-test="import" @click="importOpen = true">
                    <Upload class="size-4" />
                    Nhập Excel
                </Button>
                <Button v-if="can.create" data-test="create" @click="formOpen = true">
                    <Plus class="size-4" />
                    Thêm trường
                </Button>
            </div>
        </div>

        <DataTable :list="schools" :query="query" :columns="columns" row-clickable storage-key="high-schools" @row-click="open">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-name="{ row }">
                <Link :href="`/high-schools/${row.id}`" class="font-medium hover:underline">{{ row.name }}</Link>
                <div v-if="row.type_label" class="text-xs text-muted-foreground">{{ row.type_label }}</div>
            </template>
            <template #cell-code="{ row }">{{ row.code || '—' }}</template>
            <template #cell-province_name="{ row }">{{ row.province_name || '—' }}</template>
            <template #cell-tier="{ row }">
                <TierBadge v-if="row.tier" :tier="row.tier" />
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-partnership_label="{ row }">
                <ToneBadge :tone="tones[row.partnership_status] ?? 'gray'">{{ row.partnership_label }}</ToneBadge>
            </template>
            <template #cell-grade12_students="{ row }">{{ row.grade12_students ?? '—' }}</template>
            <template #cell-owner_name="{ row }">{{ row.owner_name || '—' }}</template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có trường phù hợp bộ lọc.</span>
                <span v-else>Chưa có trường nào trong phạm vi của bạn.</span>
            </template>
        </DataTable>
    </div>

    <SchoolFormDialog v-model:open="formOpen" :record="null" :options="options" />
    <SchoolImportDialog v-model:open="importOpen" />
</template>
