<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Code2, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import FormDialog from '@/components/intake/FormDialog.vue';
import type { FormOptions, FormRow } from '@/components/intake/FormDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    forms: ListResult<FormRow>;
    options: FormOptions;
    can: { manage: boolean };
}>();

const query = useListQuery(() => props.forms, { only: ['forms'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên biểu mẫu…' },
    { key: 'is_active', label: 'Đang nhận đăng ký', type: 'boolean' },
]);

const columns: CrmColumn<FormRow>[] = [
    { accessorKey: 'name', header: 'Biểu mẫu', enableSorting: true, enableHiding: false },
    { accessorKey: 'source_name', header: 'Nguồn' },
    { accessorKey: 'campaign_name', header: 'Chiến dịch' },
    { id: 'fields', header: 'Trường' },
    { accessorKey: 'is_active', header: 'Trạng thái', enableSorting: true },
    { id: 'actions', header: '', enableSorting: false, enableHiding: false },
];

const labelOf = (key: string): string => props.options.fields.find((f) => f.key === key)?.label ?? key;

const formOpen = ref(false);
const editing = ref<FormRow | null>(null);
const embedFor = ref<FormRow | null>(null);
const copied = ref(false);

function edit(row: FormRow | null): void {
    editing.value = row;
    formOpen.value = true;
}

function remove(row: FormRow): void {
    if (window.confirm(`Xoá biểu mẫu "${row.name}"? Các form đã nhúng trên website sẽ ngừng hoạt động.`)) {
        router.delete(`/forms/${row.id}`, { preserveScroll: true, preserveState: true });
    }
}

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(embedFor.value?.embed ?? '');
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // the text is selectable in the box
    }
}
</script>

<template>
    <Head title="Form thu thập" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Form thu thập</h1>
                <p class="text-sm text-muted-foreground">Biểu mẫu đăng ký nhúng vào website nhà trường. Thí sinh gửi form sẽ vào hệ thống thành hồ sơ mới và được giao việc "Gọi lần đầu".</p>
            </div>
            <Button v-if="can.manage" data-test="create" @click="edit(null)">
                <Plus class="size-4" />
                Thêm biểu mẫu
            </Button>
        </div>

        <DataTable :list="forms" :query="query" :columns="columns" storage-key="forms">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-source_name="{ row }">{{ row.source_name || 'Website' }}</template>
            <template #cell-campaign_name="{ row }">{{ row.campaign_name || '—' }}</template>
            <template #cell-fields="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.fields.map((f) => labelOf(f.key) + (f.required ? '*' : '')).join(', ') }}</span>
            </template>
            <template #cell-is_active="{ row }">
                <ToneBadge :tone="row.is_active ? 'green' : 'gray'">{{ row.is_active ? 'Đang nhận' : 'Tạm dừng' }}</ToneBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-0.5">
                    <Button variant="ghost" size="icon" class="size-7" aria-label="Lấy mã nhúng" data-test="embed" @click="embedFor = row"><Code2 class="size-4" /></Button>
                    <template v-if="can.manage">
                        <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" @click="edit(row)"><Pencil class="size-3.5" /></Button>
                        <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" @click="remove(row)"><Trash2 class="size-3.5" /></Button>
                    </template>
                </div>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có biểu mẫu phù hợp bộ lọc.</span>
                <span v-else>Chưa có biểu mẫu nào.</span>
            </template>
        </DataTable>
    </div>

    <FormDialog v-model:open="formOpen" :record="editing" :options="options" />

    <Dialog :open="embedFor !== null" @update:open="(v) => !v && (embedFor = null)">
        <DialogContent class="sm:max-w-2xl" data-test="embed-dialog">
            <DialogHeader>
                <DialogTitle>Mã nhúng: {{ embedFor?.name }}</DialogTitle>
                <DialogDescription>
                    Dán đoạn HTML này vào trang web. Form gửi tới <code>/api/public/leads</code>, tự gắn tham số <code>utm_*</code> của trang và có ô chống spam ẩn (<code>hp</code>) — không xoá ô đó.
                </DialogDescription>
            </DialogHeader>
            <textarea readonly rows="14" class="w-full rounded-md border bg-muted/40 p-2 font-mono text-xs" :value="embedFor?.embed" data-test="embed-code" @focus="($event.target as HTMLTextAreaElement).select()" />
            <DialogFooter>
                <Button type="button" variant="outline" @click="embedFor = null">Đóng</Button>
                <Button type="button" data-test="copy-embed" @click="copy">{{ copied ? 'Đã sao chép' : 'Sao chép' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
