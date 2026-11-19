<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    channel: string;
    channel_label: string;
    recipient: string | null;
    applicant_uuid: string | null;
    applicant_name: string | null;
    template_name: string | null;
    user_name: string | null;
    body: string | null;
    status: string;
    status_label: string;
    error: string | null;
    created_at: string;
};

const props = defineProps<{
    logs: ListResult<Row>;
    options: { statuses: { value: string; label: string }[]; channels: { value: string; label: string }[] };
}>();

const query = useListQuery(() => props.logs, { only: ['logs'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'status', label: 'Trạng thái', type: 'select', options: props.options.statuses },
    { key: 'channel', label: 'Kênh', type: 'select', options: props.options.channels },
]);

const columns: CrmColumn<Row>[] = [
    { accessorKey: 'created_at', header: 'Thời gian', enableSorting: true },
    { accessorKey: 'applicant_name', header: 'Thí sinh' },
    { accessorKey: 'channel_label', header: 'Kênh' },
    { accessorKey: 'recipient', header: 'Người nhận' },
    { accessorKey: 'template_name', header: 'Mẫu' },
    { accessorKey: 'status', header: 'Trạng thái', enableSorting: true },
    { accessorKey: 'body', header: 'Nội dung' },
];

const tones: Record<string, BadgeTone> = { queued: 'amber', sent: 'blue', delivered: 'green', failed: 'red' };
</script>

<template>
    <Head title="Tin nhắn đã gửi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Tin nhắn đã gửi</h1>
            <p class="text-sm text-muted-foreground">Gửi hàng loạt từ danh sách thí sinh (nút Gửi tin). Chỉ gửi cho thí sinh đã đồng ý nhận tin.</p>
        </div>

        <DataTable :list="logs" :query="query" :columns="columns" storage-key="message-logs">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-applicant_name="{ row }">
                <Link v-if="row.applicant_uuid" :href="`/applicants/${row.applicant_uuid}`" class="hover:underline">{{ row.applicant_name }}</Link>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-template_name="{ row }">{{ row.template_name || '—' }}</template>
            <template #cell-status="{ row }">
                <ToneBadge :tone="tones[row.status] ?? 'gray'">{{ row.status_label }}</ToneBadge>
                <div v-if="row.error" class="mt-1 max-w-64 text-xs whitespace-normal text-destructive">{{ row.error }}</div>
            </template>
            <template #cell-body="{ row }"><span class="line-clamp-2 max-w-96 whitespace-normal">{{ row.body }}</span></template>
            <template #empty>Chưa có tin nhắn nào.</template>
        </DataTable>
    </div>
</template>
