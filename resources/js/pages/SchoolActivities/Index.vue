<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, List, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import ActivityFormDialog from '@/components/schoolActivities/ActivityFormDialog.vue';
import type { ActivityOptions, ActivityRow } from '@/components/schoolActivities/types';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FilterDefinition, ListResult } from '@/types';

/**
 * Hoạt động tại trường (task 2.1): bảng và lịch tháng của mùa đang chọn. Thêm hoạt động để có link và mã QR điểm danh.
 */
const props = defineProps<{
    cycle: { id: number; name: string; closed: boolean } | null;
    activities: ListResult<ActivityRow>;
    view: 'table' | 'calendar';
    calendar: { month: string; items: ActivityRow[] };
    options: ActivityOptions;
    can: { create: boolean };
}>();

const formOpen = ref(false);
const query = useListQuery(() => props.activities, { only: ['activities'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên hoạt động, địa điểm…' },
    { key: 'type', label: 'Loại', type: 'select', options: props.options.types },
    { key: 'status', label: 'Trạng thái', type: 'select', options: props.options.statuses },
    { key: 'high_school_id', label: 'Trường THPT', type: 'entity', entity: 'high_schools' },
    { key: 'start_at', label: 'Thời gian', type: 'date' },
]);

const columns: CrmColumn<ActivityRow>[] = [
    { accessorKey: 'title', header: 'Hoạt động', enableSorting: true, enableHiding: false },
    { accessorKey: 'type_label', header: 'Loại' },
    { accessorKey: 'school', header: 'Trường' },
    { accessorKey: 'start_at', header: 'Thời gian', enableSorting: true },
    { accessorKey: 'location', header: 'Địa điểm' },
    { accessorKey: 'checkins', header: 'Điểm danh' },
    { accessorKey: 'status', header: 'Trạng thái', enableSorting: true },
];

function setView(view: 'table' | 'calendar'): void {
    router.get('/school-activities', { view, month: props.calendar.month }, { preserveState: true, preserveScroll: true });
}

function goMonth(delta: number): void {
    const [y, m] = props.calendar.month.split('-').map(Number);
    const date = new Date(y, m - 1 + delta, 1);
    router.get('/school-activities', { view: 'calendar', month: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}` }, { preserveState: true, preserveScroll: true });
}

const monthLabel = computed(() => {
    const [y, m] = props.calendar.month.split('-').map(Number);

    return `Tháng ${m}/${y}`;
});

/** Weeks of the month (Monday first), each day with the activities that start on it (display time). */
const weeks = computed(() => {
    const [y, m] = props.calendar.month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const offset = (first.getDay() + 6) % 7;
    const days = new Date(y, m, 0).getDate();
    const byDay = new Map<number, ActivityRow[]>();

    for (const a of props.calendar.items) {
        if (!a.start_at) {
            continue;
        }

        const day = Number(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', day: 'numeric' }).format(new Date(a.start_at)));
        byDay.set(day, [...(byDay.get(day) ?? []), a]);
    }

    const cells: { day: number | null; items: ActivityRow[] }[] = [
        ...Array.from({ length: offset }, () => ({ day: null, items: [] })),
        ...Array.from({ length: days }, (_, i) => ({ day: i + 1, items: byDay.get(i + 1) ?? [] })),
    ];

    while (cells.length % 7 !== 0) {
        cells.push({ day: null, items: [] });
    }

    return Array.from({ length: cells.length / 7 }, (_, w) => cells.slice(w * 7, w * 7 + 7));
});

const time = (iso: string | null): string => (iso ? new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit' }).format(new Date(iso)) : '');
</script>

<template>
    <Head title="Hoạt động tại trường" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Hoạt động tại trường</h1>
                <p class="text-sm text-muted-foreground">Tư vấn tại trường, ngày hội, tham quan… của {{ cycle?.name ?? 'mùa đang chọn' }}. Mỗi hoạt động có mã QR để học sinh điểm danh.</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex rounded-md border p-0.5" role="group" aria-label="Kiểu xem">
                    <Button :variant="view === 'table' ? 'secondary' : 'ghost'" size="sm" class="h-7" data-test="view-table" @click="setView('table')"><List class="size-4" />Bảng</Button>
                    <Button :variant="view === 'calendar' ? 'secondary' : 'ghost'" size="sm" class="h-7" data-test="view-calendar" @click="setView('calendar')"><CalendarDays class="size-4" />Lịch</Button>
                </div>
                <Button v-if="can.create" data-test="add-activity" @click="formOpen = true"><Plus class="size-4" />Thêm hoạt động</Button>
            </div>
        </div>

        <DataTable v-if="view === 'table'" :list="activities" :query="query" :columns="columns" storage-key="school-activities">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-title="{ row }">
                <Link :href="`/school-activities/${row.id}`" class="font-medium hover:underline">{{ row.title }}</Link>
                <div v-if="row.staff.length" class="text-xs text-muted-foreground">{{ row.staff.join(', ') }}</div>
            </template>
            <template #cell-type_label="{ row }"><ToneBadge :tone="row.type_tone">{{ row.type_label }}</ToneBadge></template>
            <template #cell-school="{ row }">{{ row.school ?? 'Tại campus' }}</template>
            <template #cell-start_at="{ row }">{{ formatDateTime(row.start_at, '—') }}</template>
            <template #cell-location="{ row }">{{ row.location ?? '—' }}</template>
            <template #cell-checkins="{ row }">{{ row.checkins }}<span v-if="row.expected_attendees" class="text-xs text-muted-foreground"> / {{ row.expected_attendees }} dự kiến</span></template>
            <template #cell-status="{ row }"><ToneBadge :tone="row.status_tone">{{ row.status_label }}</ToneBadge></template>
        </DataTable>

        <section v-else class="grid gap-3" data-test="calendar">
            <div class="flex items-center justify-between">
                <Button variant="outline" size="icon" class="size-8" aria-label="Tháng trước" data-test="prev-month" @click="goMonth(-1)"><ChevronLeft class="size-4" /></Button>
                <h2 class="font-medium" data-test="month-label">{{ monthLabel }}</h2>
                <Button variant="outline" size="icon" class="size-8" aria-label="Tháng sau" data-test="next-month" @click="goMonth(1)"><ChevronRight class="size-4" /></Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <div class="grid min-w-[44rem] grid-cols-7 border-b bg-muted/50 text-center text-xs text-muted-foreground">
                    <div v-for="d in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="d" class="py-1.5">{{ d }}</div>
                </div>
                <div v-for="(week, w) in weeks" :key="w" class="grid min-w-[44rem] grid-cols-7 border-b last:border-b-0">
                    <div v-for="(cell, i) in week" :key="i" :class="cn('min-h-24 border-r p-1 last:border-r-0', cell.day === null ? 'bg-muted/30' : '')" data-test="calendar-day">
                        <div v-if="cell.day !== null" class="text-xs text-muted-foreground">{{ cell.day }}</div>
                        <Link
                            v-for="a in cell.items"
                            :key="a.id"
                            :href="`/school-activities/${a.id}`"
                            class="mt-0.5 block truncate rounded px-1 py-0.5 text-xs hover:underline"
                            :class="a.status === 'cancelled' ? 'bg-red-50 text-red-700 line-through dark:bg-red-950/40' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'"
                            :title="`${a.title} · ${a.school ?? 'Tại campus'}`"
                            data-test="calendar-activity"
                        >{{ time(a.start_at) }} {{ a.title }}</Link>
                    </div>
                </div>
            </div>
            <p v-if="calendar.items.length === 0" class="text-sm text-muted-foreground" data-test="calendar-empty">Không có hoạt động nào trong tháng này.</p>
        </section>
    </div>

    <ActivityFormDialog v-model:open="formOpen" :record="null" :options="options" />
</template>
