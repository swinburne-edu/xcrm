<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import ApplicantImportWizard from '@/components/applicants/ImportWizard.vue';
import ApplicantFormDialog from '@/components/applicants/ApplicantFormDialog.vue';
import type { ApplicantOptions, ApplicantRow } from '@/components/applicants/types';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    applicants: ListResult<ApplicantRow>;
    options: ApplicantOptions;
    can: { create: boolean; import: boolean };
}>();

const query = useListQuery(() => props.applicants, { only: ['applicants'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, mã, SĐT, email…' },
    { key: 'province_id', label: 'Tỉnh/Thành phố', type: 'entity', entity: 'provinces' },
    { key: 'high_school_id', label: 'Trường THPT', type: 'entity', entity: 'high_schools' },
    {
        key: 'current_grade',
        label: 'Khối',
        type: 'select',
        options: [
            { value: '10', label: 'Lớp 10' },
            { value: '11', label: 'Lớp 11' },
            { value: '12', label: 'Lớp 12' },
            { value: '13', label: 'Đã tốt nghiệp' },
        ],
    },
    { key: 'first_source_id', label: 'Nguồn', type: 'select', options: props.options.sources },
    { key: 'first_campaign_id', label: 'Chiến dịch', type: 'entity', entity: 'campaigns' },
    ...props.options.customFilters,
]);

const columns: CrmColumn<ApplicantRow>[] = [
    { accessorKey: 'code', header: 'Mã', enableSorting: true },
    { accessorKey: 'full_name', header: 'Thí sinh', enableSorting: true, enableHiding: false },
    { accessorKey: 'phone', header: 'Điện thoại' },
    { accessorKey: 'high_school_name', header: 'Trường THPT' },
    { accessorKey: 'province_name', header: 'Tỉnh/Thành phố' },
    { accessorKey: 'graduation_year', header: 'Tốt nghiệp', enableSorting: true },
    { accessorKey: 'created_at', header: 'Ngày tạo', enableSorting: true },
];

const formOpen = ref(false);
const importOpen = ref(false);

function open(row: ApplicantRow): void {
    router.visit(`/applicants/${row.uuid}`);
}

function date(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
}
</script>

<template>
    <Head title="Thí sinh" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Thí sinh</h1>
                <p class="text-sm text-muted-foreground">
                    Thí sinh tiềm năng và hồ sơ cá nhân. Số điện thoại và CCCD bị che nếu bạn không có quyền xem đầy đủ.
                </p>
            </div>
            <div class="flex gap-2">
            <Button v-if="can.import" variant="outline" data-test="import" @click="importOpen = true">
                <Upload class="size-4" />
                Nhập Excel
            </Button>
            <Button v-if="can.create" data-test="create" @click="formOpen = true">
                <Plus class="size-4" />
                Thêm thí sinh
            </Button>
            </div>
        </div>

        <DataTable :list="applicants" :query="query" :columns="columns" row-clickable storage-key="applicants" @row-click="open">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-full_name="{ row }">
                <Link :href="`/applicants/${row.uuid}`" class="font-medium hover:underline">{{ row.full_name }}</Link>
                <div v-if="row.email" class="text-xs text-muted-foreground">{{ row.email }}</div>
            </template>
            <template #cell-phone="{ row }">{{ row.phone || '—' }}</template>
            <template #cell-high_school_name="{ row }">
                {{ row.high_school_name || '—' }}<span v-if="row.class_name" class="text-muted-foreground"> · {{ row.class_name }}</span>
            </template>
            <template #cell-province_name="{ row }">{{ row.province_name || '—' }}</template>
            <template #cell-graduation_year="{ row }">{{ row.graduation_year ?? '—' }}</template>
            <template #cell-created_at="{ row }">{{ date(row.created_at) }}</template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có thí sinh phù hợp bộ lọc.</span>
                <span v-else>Chưa có thí sinh nào trong phạm vi của bạn.</span>
            </template>
        </DataTable>
    </div>

    <ApplicantFormDialog v-model:open="formOpen" :record="null" :options="options" />
    <ApplicantImportWizard v-model:open="importOpen" />
</template>
