<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, Pencil, Phone, Plus, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import ApplicantImportWizard from '@/components/applicants/ImportWizard.vue';
import ApplicantFormDialog from '@/components/applicants/ApplicantFormDialog.vue';
import InlineEditCell from '@/components/applicants/InlineEditCell.vue';
import type { ApplicantOptions, ApplicantRow } from '@/components/applicants/types';
import DataTable from '@/components/crm/DataTable.vue';
import StageBadge from '@/components/crm/StageBadge.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import SavedViewMenu from '@/components/crm/SavedViewMenu.vue';
import type { SavedViewsProps } from '@/components/crm/SavedViewMenu.vue';
import { Button } from '@/components/ui/button';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

const props = defineProps<{
    applicants: ListResult<ApplicantRow>;
    saved_views: SavedViewsProps;
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
    { accessorKey: 'stage_name', header: 'Giai đoạn' },
    { accessorKey: 'owner_name', header: 'Người phụ trách' },
    { accessorKey: 'last_activity_at', header: 'Hoạt động gần nhất' },
    { accessorKey: 'high_school_name', header: 'Trường THPT' },
    { accessorKey: 'province_name', header: 'Tỉnh/Thành phố' },
    { accessorKey: 'graduation_year', header: 'Tốt nghiệp', enableSorting: true },
    { accessorKey: 'created_at', header: 'Ngày tạo', enableSorting: true },
    { id: 'actions', header: '', enableHiding: false },
];

const copied = ref<string | null>(null);

async function copyPhone(row: ApplicantRow): Promise<void> {
    try {
        await navigator.clipboard.writeText(row.phone ?? '');
        copied.value = row.uuid;
        setTimeout(() => (copied.value = null), 1500);
    } catch {
        // Clipboard blocked: the number stays selectable in the cell.
    }
}

const formOpen = ref(false);
const editOpen = ref(false);
const editing = ref<ApplicantRow | null>(null);
const loadingEdit = ref<string | null>(null);

/** Opens the full edit form over the list: the list row has no detail fields, so the whole record is fetched first. */
async function openEdit(row: ApplicantRow): Promise<void> {
    loadingEdit.value = row.uuid;

    try {
        const response = await fetch(`/applicants/${row.uuid}/edit`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        editing.value = (await response.json()).applicant as ApplicantRow;
        editOpen.value = true;
    } catch {
        router.visit(`/applicants/${row.uuid}?edit=1`);
    } finally {
        loadingEdit.value = null;
    }
}
const importOpen = ref(false);

function open(row: ApplicantRow): void {
    router.visit(`/applicants/${row.uuid}`);
}

function date(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
}

function dateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '—';
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
            <template #toolbar>
                <SavedViewMenu :views="saved_views" :query="query" storage-key="applicants" />
                <FilterBar :query="query" :filters="filters" />
            </template>
            <template #cell-full_name="{ row }">
                <InlineEditCell :uuid="row.uuid" field="full_name" :value="row.full_name" :can-edit="row.can_edit" trigger="icon">
                    <Link :href="`/applicants/${row.uuid}`" class="font-medium hover:underline" @click.stop>{{ row.full_name }}</Link>
                </InlineEditCell>
                <div class="text-xs text-muted-foreground">
                    <InlineEditCell :uuid="row.uuid" field="email" :value="row.email" :can-edit="row.can_edit" kind="email" placeholder="+ Thêm email" />
                </div>
            </template>
            <template #cell-phone="{ row }">
                <div class="flex items-center gap-1">
                    <InlineEditCell :uuid="row.uuid" field="phone" :value="row.phone" :can-edit="row.can_edit && !row.sensitive_masked" kind="tel" placeholder="+ Thêm SĐT" />
                    <template v-if="row.phone && !row.sensitive_masked">
                        <a :href="`tel:${row.phone}`" class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Gọi" data-test="call" @click.stop><Phone class="size-3.5" /></a>
                        <button type="button" class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Chép số điện thoại" data-test="copy-phone" @click.stop="copyPhone(row)">
                            <component :is="copied === row.uuid ? Check : Copy" class="size-3.5" />
                        </button>
                    </template>
                </div>
            </template>
            <template #cell-stage_name="{ row }">
                <StageBadge v-if="row.stage_name" :name="row.stage_name" :color="row.stage_color" />
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-owner_name="{ row }">{{ row.owner_name || '—' }}</template>
            <template #cell-last_activity_at="{ row }">{{ dateTime(row.last_activity_at ?? null) }}</template>
            <template #cell-high_school_name="{ row }">
                <InlineEditCell :uuid="row.uuid" field="high_school_id" :value="row.high_school_id" :label="row.high_school_name" :can-edit="row.can_edit" kind="entity" entity="high_schools" />
                <span v-if="row.class_name" class="text-xs text-muted-foreground">Lớp {{ row.class_name }}</span>
            </template>
            <template #cell-province_name="{ row }">
                <InlineEditCell :uuid="row.uuid" field="province_id" :value="row.province_id ?? null" :label="row.province_name" :can-edit="row.can_edit" kind="entity" entity="provinces" />
            </template>
            <template #cell-graduation_year="{ row }">
                <InlineEditCell :uuid="row.uuid" field="graduation_year" :value="row.graduation_year" :can-edit="row.can_edit" kind="number" />
            </template>
            <template #cell-created_at="{ row }">{{ date(row.created_at) }}</template>
            <template #cell-actions="{ row }">
                <button v-if="row.can_edit" type="button" class="inline-flex rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50" aria-label="Sửa" title="Sửa" :disabled="loadingEdit === row.uuid" data-test="edit-full" @click.stop="openEdit(row)"><Pencil class="size-4" /></button>
            </template>
            <template #empty>
                <span v-if="query.activeFilterCount.value > 0">Không có thí sinh phù hợp bộ lọc.</span>
                <span v-else>Chưa có thí sinh nào trong phạm vi của bạn.</span>
            </template>
        </DataTable>
    </div>

    <ApplicantFormDialog v-model:open="formOpen" :record="null" :options="options" />
    <ApplicantFormDialog v-model:open="editOpen" :record="editing" :options="options" />
    <ApplicantImportWizard v-model:open="importOpen" />
</template>
