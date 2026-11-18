<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppointmentFormDialog from '@/components/activity/AppointmentFormDialog.vue';
import AttendanceDialog from '@/components/activity/AttendanceDialog.vue';
import type { AppointmentRow, Option } from '@/components/activity/types';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatDateTime } from '@/lib/format';
import type { FilterDefinition, ListResult } from '@/types';

type View = 'upcoming' | 'past' | 'all';

const props = defineProps<{
    appointments: ListResult<AppointmentRow>;
    view: View;
    options: { types: Option[]; attendances: Option[] };
    can: { create: boolean; update: boolean };
}>();

const query = useListQuery(() => props.appointments, { only: ['appointments', 'view'] });

const views: { key: View; label: string }[] = [
    { key: 'upcoming', label: 'Sắp diễn ra' },
    { key: 'past', label: 'Đã qua' },
    { key: 'all', label: 'Tất cả' },
];

const filters = computed<FilterDefinition[]>(() => [
    { key: 'type', label: 'Loại', type: 'select', options: props.options.types },
    { key: 'attendance', label: 'Trạng thái', type: 'select', options: props.options.attendances },
    { key: 'host_id', label: 'Người phụ trách', type: 'entity', entity: 'users' },
    { key: 'start', label: 'Ngày hẹn', type: 'date' },
]);

const columns: CrmColumn<AppointmentRow>[] = [
    { accessorKey: 'start_at', header: 'Thời gian', enableSorting: true, enableHiding: false },
    { accessorKey: 'applicant_name', header: 'Thí sinh' },
    { accessorKey: 'type_label', header: 'Loại', enableSorting: true },
    { accessorKey: 'location', header: 'Địa điểm' },
    { accessorKey: 'host_name', header: 'Phụ trách' },
    { accessorKey: 'attendance', header: 'Trạng thái', enableSorting: true },
    { id: 'actions', header: '', enableSorting: false, enableHiding: false },
];

const tones: Record<string, BadgeTone> = { scheduled: 'blue', attended: 'green', no_show: 'red', cancelled: 'gray' };

const formOpen = ref(false);
const editing = ref<AppointmentRow | null>(null);
const attendanceOpen = ref(false);
const recording = ref<AppointmentRow | null>(null);

function edit(row: AppointmentRow | null): void {
    editing.value = row;
    formOpen.value = true;
}

function record(row: AppointmentRow): void {
    recording.value = row;
    attendanceOpen.value = true;
}

function remove(row: AppointmentRow): void {
    if (window.confirm(`Xoá lịch hẹn ${row.type_label} của ${row.applicant_name ?? 'thí sinh'}?`)) {
        router.delete(`/appointments/${row.id}`, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <Head title="Lịch hẹn" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Lịch hẹn</h1>
                <p class="text-sm text-muted-foreground">Lịch tư vấn, tham quan, phỏng vấn, thi năng khiếu của các hồ sơ bạn phụ trách.</p>
            </div>
            <Button v-if="can.create" data-test="create" @click="edit(null)">
                <Plus class="size-4" />
                Đặt lịch hẹn
            </Button>
        </div>

        <nav class="flex flex-wrap gap-1 border-b" data-test="appointment-views">
            <Link
                v-for="v in views"
                :key="v.key"
                :href="`/appointments?view=${v.key}`"
                preserve-scroll
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="view === v.key ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground'"
                :data-test="`view-${v.key}`"
            >
                {{ v.label }}
            </Link>
        </nav>

        <DataTable :list="appointments" :query="query" :columns="columns" storage-key="appointments">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-start_at="{ row }">
                <span class="font-medium">{{ formatDateTime(row.start_at) }}</span>
            </template>
            <template #cell-applicant_name="{ row }">
                <Link v-if="row.applicant_href" :href="row.applicant_href" class="hover:underline">{{ row.applicant_name }}</Link>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-location="{ row }">{{ row.location || '—' }}</template>
            <template #cell-host_name="{ row }">{{ row.host_name || '—' }}</template>
            <template #cell-attendance="{ row }">
                <ToneBadge :tone="tones[row.attendance] ?? 'gray'">{{ row.attendance_label }}</ToneBadge>
                <div v-if="row.result" class="mt-0.5 max-w-xs truncate text-xs text-muted-foreground">{{ row.result }}</div>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.update && row.attendance === 'scheduled'" class="flex justify-end gap-0.5">
                    <Button variant="ghost" size="icon" class="size-7" aria-label="Ghi nhận kết quả" data-test="record" @click="record(row)"><ClipboardCheck class="size-4" /></Button>
                    <Button variant="ghost" size="icon" class="size-7" aria-label="Đổi lịch" @click="edit(row)"><Pencil class="size-3.5" /></Button>
                    <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" @click="remove(row)"><Trash2 class="size-3.5" /></Button>
                </div>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có lịch hẹn phù hợp bộ lọc.</span>
                <span v-else>Chưa có lịch hẹn nào.</span>
            </template>
        </DataTable>
    </div>

    <AppointmentFormDialog v-model:open="formOpen" :record="editing" :types="options.types" />
    <AttendanceDialog v-model:open="attendanceOpen" :record="recording" />
</template>
