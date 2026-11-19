<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutGrid, Plus, Table2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CaseCard from '@/components/cases/CaseCard.vue';
import CaseCreateDialog from '@/components/cases/CaseCreateDialog.vue';
import CaseDrawer from '@/components/cases/CaseDrawer.vue';
import LostReasonDialog from '@/components/cases/LostReasonDialog.vue';
import { formatDate, selectClass } from '@/components/cases/types';
import type { BoardColumn, BulkResult, CaseCardData, CaseOptions } from '@/components/cases/types';
import DataTable from '@/components/crm/DataTable.vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import SavedViewMenu from '@/components/crm/SavedViewMenu.vue';
import type { SavedViewsProps } from '@/components/crm/SavedViewMenu.vue';
import KanbanBoard from '@/components/crm/KanbanBoard.vue';
import type { KanbanColumn } from '@/components/crm/KanbanBoard.vue';
import StageBadge from '@/components/crm/StageBadge.vue';
import TemperatureBadge from '@/components/crm/TemperatureBadge.vue';
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
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    mode: 'kanban' | 'table';
    cycle: { id: number; name: string } | null;
    cases: ListResult<CaseCardData>;
    board: { pipeline: { id: number; name: string } | null; columns: BoardColumn[] } | null;
    saved_views: SavedViewsProps;
    options: CaseOptions;
    can: { create: boolean; assign: boolean; update: boolean };
}>();

const query = useListQuery(() => props.cases, { only: ['cases', 'board', 'mode'] });

/* ---------- filters ---------- */
const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, mã, SĐT, email…' },
    { key: 'stage_id', label: 'Giai đoạn', type: 'select', options: props.options.stages },
    { key: 'status', label: 'Trạng thái', type: 'select', options: props.options.statuses },
    { key: 'temperature', label: 'Mức độ quan tâm', type: 'select', options: props.options.temperatures },
    { key: 'source_id', label: 'Nguồn', type: 'select', options: props.options.sources },
    { key: 'campaign_id', label: 'Chiến dịch', type: 'entity', entity: 'campaigns' },
    { key: 'province_id', label: 'Tỉnh/Thành phố', type: 'entity', entity: 'provinces' },
    { key: 'high_school_id', label: 'Trường THPT', type: 'entity', entity: 'high_schools' },
    { key: 'primary_major_id', label: 'Ngành', type: 'entity', entity: 'majors' },
    { key: 'tag_id', label: 'Tag', type: 'select', options: props.options.tags },
    { key: 'overdue', label: 'Quá hạn hành động', type: 'boolean', trueLabel: 'Quá hạn', falseLabel: 'Không' },
    ...props.options.customFilters,
]);

const ownerValue = computed(() => String(query.filters.value.owner ?? 'all'));
const ownerSegments = [
    { value: 'all', label: 'Tất cả' },
    { value: 'mine', label: 'Của tôi' },
    { value: 'pool', label: 'Kho chung' },
];
const ownerPerson = computed<number | null>({
    get: () => (/^\d+$/.test(ownerValue.value) ? Number(ownerValue.value) : null),
    set: (v) => query.setFilter('owner', v === null ? 'all' : String(v)),
});

function params(): URLSearchParams {
    const p = new URLSearchParams();

    for (const [key, value] of Object.entries(query.filters.value)) {
        if (value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0)) {
            continue;
        }

        p.set(`filter[${key}]`, Array.isArray(value) ? value.join(',') : String(value));
    }

    if (query.sort.value) {
        p.set('sort', query.sort.value);
    }

    return p;
}

const search = computed(() => {
    const qs = params().toString();

    return qs ? `?${qs}` : '';
});

/* ---------- Kanban ---------- */
const extra = ref<Record<number, CaseCardData[]>>({});
watch(() => props.board, () => (extra.value = {}));

const columns = computed<KanbanColumn<CaseCardData>[]>(() =>
    (props.board?.columns ?? []).map((c) => ({
        id: c.id,
        title: c.name,
        color: c.color,
        total: c.total,
        items: [...c.cards, ...(extra.value[c.id] ?? [])],
    })),
);

async function loadMore(columnId: string | number): Promise<void> {
    const column = columns.value.find((c) => c.id === columnId);

    if (!column) {
        return;
    }

    const p = params();
    p.set('stage_id', String(columnId));
    p.set('offset', String(column.items.length));

    const response = await fetch(`/cases/column?${p}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (response.ok) {
        const data = (await response.json()) as { cards: CaseCardData[] };
        extra.value = { ...extra.value, [Number(columnId)]: [...(extra.value[Number(columnId)] ?? []), ...data.cards] };
    }
}

/* ---------- moving a case ---------- */
const errorMessage = ref<string | null>(null);
const lostOpen = ref(false);
const pendingMove = ref<{ uuid: string; stageId: number; name: string } | null>(null);

function postMove(uuid: string, data: Record<string, unknown>): void {
    errorMessage.value = null;
    router.post(`/cases/${uuid}/move`, data as never, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] ?? 'Không chuyển được giai đoạn.';
            router.reload({ only: ['board', 'cases'] }); // the card jumps back
        },
    });
}

function onMove({ item, to }: { item: CaseCardData; from: string | number; to: string | number }): void {
    const stage = props.options.stages.find((s) => Number(s.value) === Number(to));

    if (stage?.type === 'lost') {
        pendingMove.value = { uuid: item.uuid, stageId: Number(to), name: item.applicant_name ?? '' };
        lostOpen.value = true;

        return;
    }

    postMove(item.uuid, { stage_id: to });
}

function confirmLost(payload: { lost_reason_id: string; lost_note: string | null }): void {
    if (pendingMove.value) {
        postMove(pendingMove.value.uuid, { stage_id: pendingMove.value.stageId, ...payload });
        pendingMove.value = null;
    }
}

function cancelLost(): void {
    pendingMove.value = null;
    router.reload({ only: ['board', 'cases'] });
}

/* ---------- drawer, create ---------- */
const drawerOpen = ref(false);
const drawerUuid = ref<string | null>(null);
const createOpen = ref(false);

function openCase(item: { uuid: string }): void {
    drawerUuid.value = item.uuid;
    drawerOpen.value = true;
}

/* ---------- table + bulk ---------- */
const tableColumns: CrmColumn<CaseCardData>[] = [
    { accessorKey: 'applicant_name', header: 'Thí sinh', enableHiding: false },
    { accessorKey: 'stage_name', header: 'Giai đoạn' },
    { accessorKey: 'temperature', header: 'Quan tâm' },
    { accessorKey: 'owner_name', header: 'Phụ trách' },
    { accessorKey: 'high_school_name', header: 'Trường THPT' },
    { accessorKey: 'next_action_at', header: 'Hành động tiếp theo', enableSorting: true },
    { accessorKey: 'last_activity_at', header: 'Hoạt động gần nhất', enableSorting: true },
    { accessorKey: 'tags', header: 'Tag' },
];

const bulkOwner = ref<number | string | null>(null);
const bulkStage = ref('');
const bulkTag = ref('');
const bulkIds = ref<string[]>([]);
const bulkLostOpen = ref(false);
const bulkResult = ref<BulkResult | null>(null);
const bulkProcessing = ref(false);
let clearSelection: (() => void) | null = null;

function bulk(ids: string[], clear: () => void, action: string, data: Record<string, unknown> = {}): void {
    clearSelection = clear;
    router.post('/cases/bulk', { action, ids, ...data } as never, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (bulkProcessing.value = true),
        onSuccess: (page) => {
            const result = (page as unknown as { flash?: { bulk?: BulkResult } }).flash?.bulk ?? null;
            bulkResult.value = result && result.failed.length > 0 ? result : null;
            clear();
        },
        onFinish: () => (bulkProcessing.value = false),
    });
}

function bulkMove(ids: string[], clear: () => void): void {
    const stage = props.options.stages.find((s) => s.value === bulkStage.value);

    if (!stage) {
        return;
    }

    if (stage.type === 'lost') {
        bulkIds.value = ids;
        clearSelection = clear;
        bulkLostOpen.value = true;

        return;
    }

    bulk(ids, clear, 'move', { stage_id: stage.value });
}

function confirmBulkLost(payload: { lost_reason_id: string; lost_note: string | null }): void {
    bulk(bulkIds.value, clearSelection ?? (() => undefined), 'move', { stage_id: bulkStage.value, ...payload });
}
</script>

<template>
    <Head title="Hồ sơ tuyển sinh" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Hồ sơ tuyển sinh</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="cycle">Hồ sơ của {{ cycle.name }}{{ board?.pipeline ? ` · ${board.pipeline.name}` : '' }}. Kéo thẻ sang cột khác để đổi giai đoạn.</template>
                    <template v-else>Chưa có mùa tuyển sinh. Tạo mùa ở Cài đặt trước.</template>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-md border p-0.5" role="group" aria-label="Chế độ xem">
                    <Link :href="`/cases${search}`" class="inline-flex items-center gap-1 rounded px-2.5 py-1 text-sm" :class="mode === 'kanban' ? 'bg-muted font-medium' : 'text-muted-foreground'" data-test="view-kanban"
                        ><LayoutGrid class="size-4" />Kanban</Link
                    >
                    <Link :href="`/cases/table${search}`" class="inline-flex items-center gap-1 rounded px-2.5 py-1 text-sm" :class="mode === 'table' ? 'bg-muted font-medium' : 'text-muted-foreground'" data-test="view-table"
                        ><Table2 class="size-4" />Bảng</Link
                    >
                </div>
                <Button v-if="can.create" data-test="create" @click="createOpen = true"><Plus class="size-4" />Thêm hồ sơ</Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-md border p-0.5" role="group" aria-label="Người phụ trách" data-test="owner-segments">
                <button
                    v-for="s in ownerSegments"
                    :key="s.value"
                    type="button"
                    class="rounded px-2.5 py-1 text-sm"
                    :class="ownerValue === s.value ? 'bg-muted font-medium' : 'text-muted-foreground'"
                    :data-test="`owner-${s.value}`"
                    @click="query.setFilter('owner', s.value)"
                >
                    {{ s.label }}
                </button>
            </div>
            <div class="w-52"><EntityPicker v-model="ownerPerson" entity="users" placeholder="Người phụ trách…" /></div>
            <FilterBar :query="query" :filters="filters" />
            <SavedViewMenu :views="saved_views" :query="query" storage-key="cases" :view-type="mode" />
        </div>

        <p v-if="errorMessage" class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200" role="alert" data-test="move-error">
            {{ errorMessage }}
        </p>

        <template v-if="mode === 'kanban'">
            <p v-if="!board?.pipeline" class="text-sm text-muted-foreground">Chưa có pipeline mặc định. Tạo ở Cài đặt → Pipeline.</p>
            <KanbanBoard v-else :columns="columns" :readonly="!can.update" @move="onMove" @load-more="loadMore" @open="openCase">
                <template #card="{ item }"><CaseCard :item="item" /></template>
            </KanbanBoard>
        </template>

        <DataTable v-else :list="cases" :query="query" :columns="tableColumns" selectable row-clickable storage-key="cases" @row-click="openCase">
            <template #bulk-actions="{ selectedIds, clear }">
                <div class="flex flex-wrap items-center gap-2" data-test="bulk-bar">
                    <template v-if="can.assign">
                        <div class="w-48"><EntityPicker v-model="bulkOwner" entity="users" placeholder="Giao cho…" /></div>
                        <Button size="sm" variant="outline" :disabled="bulkOwner === null || bulkProcessing" data-test="bulk-assign" @click="bulk(selectedIds, clear, 'assign', { owner_id: bulkOwner })">Giao</Button>
                        <Button size="sm" variant="outline" :disabled="bulkProcessing" data-test="bulk-pool" @click="bulk(selectedIds, clear, 'assign', { owner_id: null })">Về kho chung</Button>
                    </template>
                    <template v-if="can.update">
                        <select v-model="bulkStage" :class="[selectClass, 'h-8 w-44']" data-test="bulk-stage">
                            <option value="">Giai đoạn…</option>
                            <option v-for="s in options.stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <Button size="sm" variant="outline" :disabled="!bulkStage || bulkProcessing" data-test="bulk-move" @click="bulkMove(selectedIds, clear)">Chuyển</Button>
                        <select v-model="bulkTag" :class="[selectClass, 'h-8 w-36']" data-test="bulk-tag">
                            <option value="">Tag…</option>
                            <option v-for="t in options.tags" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <Button size="sm" variant="outline" :disabled="!bulkTag || bulkProcessing" data-test="bulk-tag-add" @click="bulk(selectedIds, clear, 'tag', { tag_id: bulkTag })">Gắn tag</Button>
                        <Button size="sm" variant="outline" :disabled="!bulkTag || bulkProcessing" data-test="bulk-tag-remove" @click="bulk(selectedIds, clear, 'untag', { tag_id: bulkTag })">Bỏ tag</Button>
                    </template>
                </div>
            </template>
            <template #cell-applicant_name="{ row }">
                <div class="font-medium">{{ row.applicant_name }}</div>
                <div class="text-xs text-muted-foreground">{{ row.applicant_code }}</div>
            </template>
            <template #cell-stage_name="{ row }"><StageBadge :name="row.stage_name ?? ''" :color="row.stage_color" /></template>
            <template #cell-temperature="{ row }"><TemperatureBadge :value="row.temperature" /><span v-if="!row.temperature">—</span></template>
            <template #cell-owner_name="{ row }">
                <span v-if="row.in_pool" class="text-muted-foreground">Kho chung</span>
                <template v-else>{{ row.owner_name }}</template>
            </template>
            <template #cell-high_school_name="{ row }">{{ row.high_school_name || '—' }}</template>
            <template #cell-next_action_at="{ row }">
                <span :class="row.overdue ? 'font-medium text-red-600 dark:text-red-400' : ''">{{ formatDate(row.next_action_at) }}</span>
            </template>
            <template #cell-last_activity_at="{ row }">{{ formatDate(row.last_activity_at) }}</template>
            <template #cell-tags="{ row }">
                <div class="flex flex-wrap gap-1"><ToneBadge v-for="t in row.tags" :key="t.id" tone="gray">{{ t.name }}</ToneBadge><span v-if="!row.tags.length">—</span></div>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có hồ sơ phù hợp bộ lọc.</span>
                <span v-else>Chưa có hồ sơ nào trong mùa này.</span>
            </template>
        </DataTable>
    </div>

    <CaseDrawer v-model:open="drawerOpen" :uuid="drawerUuid" :options="options" />
    <CaseCreateDialog v-model:open="createOpen" :cycle-name="cycle?.name ?? null" :options="options" :can-assign="can.assign" />
    <LostReasonDialog v-model:open="lostOpen" :reasons="options.lostReasons" :subject="pendingMove?.name" @confirm="confirmLost" @cancel="cancelLost" />
    <LostReasonDialog v-model:open="bulkLostOpen" :reasons="options.lostReasons" @confirm="confirmBulkLost" />

    <Dialog :open="bulkResult !== null" @update:open="(v) => !v && (bulkResult = null)">
        <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg" data-test="bulk-result">
            <DialogHeader>
                <DialogTitle>Kết quả thao tác hàng loạt</DialogTitle>
                <DialogDescription>Đã xử lý {{ bulkResult?.done }} hồ sơ; {{ bulkResult?.failed.length }} hồ sơ không thực hiện được.</DialogDescription>
            </DialogHeader>
            <ul class="grid gap-1 text-sm">
                <li v-for="(f, i) in bulkResult?.failed" :key="i"><span class="font-medium">{{ f.name }}</span>: <span class="text-destructive">{{ f.message }}</span></li>
            </ul>
            <DialogFooter><Button @click="bulkResult = null">Đóng</Button></DialogFooter>
        </DialogContent>
    </Dialog>
</template>
