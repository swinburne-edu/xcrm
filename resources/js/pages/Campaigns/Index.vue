<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CampaignFormDialog from '@/components/campaigns/CampaignFormDialog.vue';
import { money } from '@/components/campaigns/types';
import type { CampaignOptions, CampaignRow } from '@/components/campaigns/types';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    campaigns: ListResult<CampaignRow>;
    cycle: { id: number; name: string } | null;
    options: CampaignOptions;
    can: { create: boolean };
}>();

const query = useListQuery(() => props.campaigns, { only: ['campaigns'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, mã chiến dịch…' },
    { key: 'status', label: 'Trạng thái', type: 'select', options: props.options.statuses },
    { key: 'type', label: 'Loại', type: 'select', options: props.options.types },
    { key: 'channel', label: 'Kênh', type: 'select', options: props.options.channels },
    { key: 'owner_id', label: 'Người phụ trách', type: 'entity', entity: 'users' },
]);

const columns: CrmColumn<CampaignRow>[] = [
    { accessorKey: 'name', header: 'Chiến dịch', enableSorting: true, enableHiding: false },
    { accessorKey: 'code', header: 'Mã', enableSorting: true },
    { accessorKey: 'status', header: 'Trạng thái', enableSorting: true },
    { accessorKey: 'type_label', header: 'Loại' },
    { accessorKey: 'channel_label', header: 'Kênh' },
    { accessorKey: 'start_date', header: 'Thời gian', enableSorting: true },
    { accessorKey: 'budget', header: 'Ngân sách', enableSorting: true },
    { accessorKey: 'owner_name', header: 'Phụ trách' },
];

const tones: Record<string, BadgeTone> = {
    draft: 'gray',
    pending_approval: 'amber',
    running: 'green',
    paused: 'violet',
    ended: 'blue',
};

const formOpen = ref(false);

function open(row: CampaignRow): void {
    router.visit(`/campaigns/${row.id}`);
}
</script>

<template>
    <Head title="Chiến dịch" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Chiến dịch</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="cycle">Chiến dịch của {{ cycle.name }} (mùa chọn ở thanh trên cùng).</template>
                    <template v-else>Chưa có mùa tuyển sinh. Tạo mùa ở Cài đặt trước khi tạo chiến dịch.</template>
                </p>
            </div>
            <Button v-if="can.create" data-test="create" @click="formOpen = true">
                <Plus class="size-4" />
                Thêm chiến dịch
            </Button>
        </div>

        <DataTable :list="campaigns" :query="query" :columns="columns" row-clickable storage-key="campaigns" @row-click="open">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-name="{ row }">
                <Link :href="`/campaigns/${row.id}`" class="font-medium hover:underline">{{ row.name }}</Link>
                <div v-if="row.parent_name" class="text-xs text-muted-foreground">Thuộc {{ row.parent_name }}</div>
            </template>
            <template #cell-status="{ row }">
                <ToneBadge :tone="tones[row.status] ?? 'gray'">{{ row.status_label }}</ToneBadge>
            </template>
            <template #cell-type_label="{ row }">{{ row.type_label || '—' }}</template>
            <template #cell-channel_label="{ row }">{{ row.channel_label || '—' }}</template>
            <template #cell-start_date="{ row }">
                <span v-if="row.start_date || row.end_date">{{ row.start_date || '…' }} → {{ row.end_date || '…' }}</span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-budget="{ row }">{{ money(row.budget) }}</template>
            <template #cell-owner_name="{ row }">{{ row.owner_name || '—' }}</template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có chiến dịch phù hợp bộ lọc.</span>
                <span v-else>Mùa này chưa có chiến dịch nào.</span>
            </template>
        </DataTable>
    </div>

    <CampaignFormDialog v-model:open="formOpen" :record="null" :cycle-id="cycle?.id ?? null" :options="options" />
</template>
