<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, FilterOption, ListResult } from '@/types';

/**
 * Danh sách xét tuyển (task 2.2): nguyện vọng đã nộp của mùa đang chọn, xếp theo điểm. Chọn một ngành để thấy
 * thứ hạng so với chỉ tiêu: những nguyện vọng trong chỉ tiêu xét theo điểm được đánh dấu xanh.
 */
type Row = {
    id: number;
    rank: number;
    applicant_uuid: string | null;
    applicant_code: string | null;
    applicant_name: string | null;
    owner: string | null;
    major: string | null;
    method: string | null;
    combination: string | null;
    round: string | null;
    preference_order: number;
    raw_score: number | null;
    priority_bonus: number | null;
    final_score: number | null;
    meets_minimum: boolean | null;
    status: string;
    status_label: string;
    status_tone: 'gray' | 'blue' | 'violet' | 'green' | 'red' | 'amber';
};

const props = defineProps<{
    cycle: { id: number; name: string; closed: boolean } | null;
    applications: ListResult<Row>;
    quota: { total: number; direct: number; by_score: number; min_threshold: number | null; admitted: number; confirmed: number } | null;
    options: { majors: FilterOption[]; rounds: FilterOption[]; methods: FilterOption[]; statuses: FilterOption[] };
    can: { review: boolean };
}>();

const query = useListQuery(() => props.applications, { only: ['applications', 'quota'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên hoặc mã thí sinh…' },
    { key: 'major_id', label: 'Ngành', type: 'select', options: props.options.majors },
    { key: 'round_id', label: 'Đợt', type: 'select', options: props.options.rounds },
    { key: 'cycle_method_id', label: 'Phương thức', type: 'select', options: props.options.methods },
    { key: 'status', label: 'Trạng thái', type: 'select', multiple: true, options: props.options.statuses },
]);

const columns: CrmColumn<Row>[] = [
    { accessorKey: 'rank', header: '#', enableHiding: false, meta: { class: 'w-14' } },
    { accessorKey: 'applicant_name', header: 'Thí sinh', enableHiding: false },
    { accessorKey: 'major', header: 'Ngành' },
    { accessorKey: 'method', header: 'Phương thức' },
    { accessorKey: 'combination', header: 'Tổ hợp' },
    { accessorKey: 'round', header: 'Đợt' },
    { accessorKey: 'preference_order', header: 'NV', enableSorting: true },
    { accessorKey: 'final_score', header: 'Điểm xét', enableSorting: true },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'owner', header: 'Phụ trách' },
];

/** Ranking against the quota only makes sense for one major sorted by score. */
const ranked = computed(() => props.quota !== null && (props.applications.query.sort ?? '-final_score') === '-final_score');
const fmt = (n: number | null): string => (n === null ? '—' : n.toLocaleString('vi-VN', { maximumFractionDigits: 2 }));
const fill = computed(() => (props.quota && props.quota.total > 0 ? Math.min(100, Math.round((props.quota.admitted / props.quota.total) * 100)) : 0));
</script>

<template>
    <Head title="Nguyện vọng xét tuyển" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Nguyện vọng xét tuyển</h1>
            <p class="text-sm text-muted-foreground">
                Danh sách xét tuyển của {{ cycle?.name ?? 'mùa đang chọn' }}, xếp theo điểm. Chọn một ngành để so thứ hạng với chỉ tiêu.
            </p>
        </div>

        <section v-if="quota" class="grid gap-2 rounded-md border p-4 text-sm" data-test="quota-card">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <span>Chỉ tiêu <b>{{ quota.total }}</b></span>
                <span class="text-muted-foreground">xét thẳng {{ quota.direct }} · xét theo điểm {{ quota.by_score }}</span>
                <span>Trúng tuyển <b>{{ quota.admitted }}</b></span>
                <span>Xác nhận nhập học <b>{{ quota.confirmed }}</b></span>
                <span v-if="quota.min_threshold !== null" class="text-muted-foreground">Ngưỡng đầu vào {{ fmt(quota.min_threshold) }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="fill" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full" :class="quota.admitted > quota.total ? 'bg-red-500' : 'bg-primary'" :style="{ width: `${fill}%` }" />
            </div>
            <p v-if="quota.admitted > quota.total" class="text-xs text-destructive">Đã trúng tuyển vượt chỉ tiêu {{ quota.admitted - quota.total }}.</p>
        </section>

        <DataTable :list="applications" :query="query" :columns="columns" storage-key="applications">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-rank="{ row }">
                <ToneBadge v-if="ranked" :tone="row.rank <= (quota?.by_score ?? 0) ? 'green' : 'gray'" data-test="rank-badge">{{ row.rank }}</ToneBadge>
                <span v-else class="text-muted-foreground">{{ row.rank }}</span>
            </template>
            <template #cell-applicant_name="{ row }">
                <Link v-if="row.applicant_uuid" :href="`/applicants/${row.applicant_uuid}`" class="font-medium hover:underline">{{ row.applicant_name }}</Link>
                <div class="text-xs text-muted-foreground">{{ row.applicant_code }}</div>
            </template>
            <template #cell-combination="{ row }">{{ row.combination ?? '—' }}</template>
            <template #cell-final_score="{ row }">
                <span class="font-medium">{{ fmt(row.final_score) }}</span>
                <span v-if="row.meets_minimum === false" class="ml-1 text-xs text-destructive" title="Dưới ngưỡng đầu vào">!</span>
                <div v-if="row.final_score !== null" class="text-xs text-muted-foreground">gốc {{ fmt(row.raw_score) }} + ưu tiên {{ fmt(row.priority_bonus) }}</div>
            </template>
            <template #cell-status="{ row }"><ToneBadge :tone="row.status_tone">{{ row.status_label }}</ToneBadge></template>
            <template #cell-owner="{ row }">{{ row.owner ?? '—' }}</template>
        </DataTable>
    </div>
</template>
