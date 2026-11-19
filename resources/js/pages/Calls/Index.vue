<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PhoneIncoming, PhoneOutgoing } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

type Row = {
    id: number;
    direction: string;
    number: string | null;
    applicant_uuid: string | null;
    applicant_name: string | null;
    user_name: string | null;
    status: string;
    status_label: string;
    duration_sec: number | null;
    recording_url: string | null;
    error: string | null;
    created_at: string;
};

const props = defineProps<{
    calls: ListResult<Row>;
    options: { statuses: { value: string; label: string }[]; directions: { value: string; label: string }[] };
}>();

const query = useListQuery(() => props.calls, { only: ['calls'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'direction', label: 'Chiều gọi', type: 'select', options: props.options.directions },
    { key: 'status', label: 'Trạng thái', type: 'select', options: props.options.statuses },
    { key: 'user_id', label: 'Nhân viên', type: 'entity', entity: 'users' },
]);

const columns: CrmColumn<Row>[] = [
    { accessorKey: 'created_at', header: 'Thời gian', enableSorting: true },
    { accessorKey: 'direction', header: 'Chiều' },
    { accessorKey: 'applicant_name', header: 'Thí sinh' },
    { accessorKey: 'number', header: 'Số điện thoại' },
    { accessorKey: 'user_name', header: 'Nhân viên' },
    { accessorKey: 'status', header: 'Trạng thái' },
    { accessorKey: 'duration_sec', header: 'Thời lượng', enableSorting: true },
    { accessorKey: 'recording_url', header: 'Ghi âm' },
];

const tones: Record<string, BadgeTone> = { dialing: 'amber', ringing: 'amber', answered: 'green', ended: 'blue', missed: 'red', failed: 'red' };

function duration(sec: number | null): string {
    if (sec === null) {
        return '—';
    }

    return `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
}
</script>

<template>
    <Head title="Cuộc gọi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Cuộc gọi</h1>
            <p class="text-sm text-muted-foreground">Cuộc gọi đến và gọi đi qua tổng đài. Cuộc gọi kết thúc được ghi vào timeline của thí sinh.</p>
        </div>

        <DataTable :list="calls" :query="query" :columns="columns" storage-key="calls">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-direction="{ row }">
                <span class="inline-flex items-center gap-1"><component :is="row.direction === 'in' ? PhoneIncoming : PhoneOutgoing" class="size-4" />{{ row.direction === 'in' ? 'Đến' : 'Đi' }}</span>
            </template>
            <template #cell-applicant_name="{ row }">
                <Link v-if="row.applicant_uuid" :href="`/applicants/${row.applicant_uuid}`" class="hover:underline">{{ row.applicant_name }}</Link>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-user_name="{ row }">{{ row.user_name || '—' }}</template>
            <template #cell-status="{ row }">
                <ToneBadge :tone="tones[row.status] ?? 'gray'">{{ row.status_label }}</ToneBadge>
                <div v-if="row.error" class="mt-1 max-w-64 text-xs whitespace-normal text-destructive">{{ row.error }}</div>
            </template>
            <template #cell-duration_sec="{ row }">{{ duration(row.duration_sec) }}</template>
            <template #cell-recording_url="{ row }">
                <a v-if="row.recording_url" :href="row.recording_url" target="_blank" rel="noopener noreferrer" class="underline">Nghe</a>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #empty>Chưa có cuộc gọi nào.</template>
        </DataTable>
    </div>
</template>
