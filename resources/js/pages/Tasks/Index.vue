<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Pencil, Plus, RotateCcw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Option, TaskRow } from '@/components/activity/types';
import TaskFormDialog from '@/components/activity/TaskFormDialog.vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatDateTime } from '@/lib/format';
import type { FilterDefinition, ListResult } from '@/types';

type View = 'today' | 'overdue' | 'upcoming' | 'done';

const props = defineProps<{
    tasks: ListResult<TaskRow>;
    view: View;
    counts: Record<View, number>;
    options: { types: Option[]; priorities: Option[] };
    can: { create: boolean };
}>();

const query = useListQuery(() => props.tasks, { only: ['tasks', 'counts', 'view'] });

const views: { key: View; label: string }[] = [
    { key: 'today', label: 'Hôm nay' },
    { key: 'overdue', label: 'Quá hạn' },
    { key: 'upcoming', label: 'Sắp tới' },
    { key: 'done', label: 'Đã xong' },
];

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tiêu đề việc…' },
    { key: 'type', label: 'Loại', type: 'select', options: props.options.types },
    { key: 'priority', label: 'Ưu tiên', type: 'select', options: props.options.priorities },
]);

const columns: CrmColumn<TaskRow>[] = [
    { accessorKey: 'title', header: 'Việc', enableSorting: true, enableHiding: false },
    { accessorKey: 'subject_label', header: 'Liên quan' },
    { accessorKey: 'type_label', header: 'Loại' },
    { accessorKey: 'priority', header: 'Ưu tiên', enableSorting: true },
    { accessorKey: 'due_at', header: 'Hạn', enableSorting: true },
    { accessorKey: 'assignee_name', header: 'Người thực hiện' },
    { id: 'actions', header: '', enableSorting: false, enableHiding: false },
];

const priorityTones: Record<string, BadgeTone> = { low: 'gray', normal: 'blue', high: 'amber', urgent: 'red' };

const formOpen = ref(false);
const editing = ref<TaskRow | null>(null);

function edit(task: TaskRow | null): void {
    editing.value = task;
    formOpen.value = true;
}

function setStatus(task: TaskRow, status: 'done' | 'todo'): void {
    router.post(`/tasks/${task.id}/status`, { status }, { preserveScroll: true, preserveState: true });
}

function remove(task: TaskRow): void {
    if (window.confirm(`Xoá việc "${task.title}"?`)) {
        router.delete(`/tasks/${task.id}`, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <Head title="Công việc của tôi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Công việc của tôi</h1>
                <p class="text-sm text-muted-foreground">Việc cần làm theo hạn; việc quá hạn và hôm nay hiện đầu tiên.</p>
            </div>
            <Button v-if="can.create" data-test="create" @click="edit(null)">
                <Plus class="size-4" />
                Thêm việc
            </Button>
        </div>

        <nav class="flex flex-wrap gap-1 border-b" data-test="task-views">
            <Link
                v-for="v in views"
                :key="v.key"
                :href="`/tasks?view=${v.key}`"
                preserve-scroll
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm"
                :class="view === v.key ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground'"
                :data-test="`view-${v.key}`"
            >
                {{ v.label }}
                <ToneBadge :tone="v.key === 'overdue' && counts[v.key] > 0 ? 'red' : 'gray'">{{ counts[v.key] }}</ToneBadge>
            </Link>
        </nav>

        <DataTable :list="tasks" :query="query" :columns="columns" storage-key="tasks">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-title="{ row }">
                <span class="font-medium" :class="{ 'line-through opacity-60': row.status !== 'todo' }">{{ row.title }}</span>
            </template>
            <template #cell-subject_label="{ row }">
                <Link v-if="row.subject_href" :href="row.subject_href" class="hover:underline">{{ row.subject_label }}</Link>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-priority="{ row }">
                <ToneBadge :tone="priorityTones[row.priority] ?? 'gray'">{{ row.priority_label }}</ToneBadge>
            </template>
            <template #cell-due_at="{ row }">
                <span v-if="row.due_at" :class="row.overdue ? 'font-medium text-destructive' : ''">{{ formatDateTime(row.due_at) }}</span>
                <span v-else class="text-muted-foreground">Không hạn</span>
            </template>
            <template #cell-assignee_name="{ row }">{{ row.assignee_name || '—' }}</template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-0.5">
                    <Button v-if="row.status === 'todo'" variant="ghost" size="icon" class="size-7" aria-label="Hoàn thành" data-test="complete" @click="setStatus(row, 'done')"><Check class="size-4" /></Button>
                    <Button v-else variant="ghost" size="icon" class="size-7" aria-label="Mở lại" data-test="reopen" @click="setStatus(row, 'todo')"><RotateCcw class="size-4" /></Button>
                    <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" @click="edit(row)"><Pencil class="size-3.5" /></Button>
                    <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" @click="remove(row)"><Trash2 class="size-3.5" /></Button>
                </div>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có việc phù hợp bộ lọc.</span>
                <span v-else>Không có việc nào ở mục này.</span>
            </template>
        </DataTable>
    </div>

    <TaskFormDialog v-model:open="formOpen" :record="editing" :types="options.types" :priorities="options.priorities" />
</template>
