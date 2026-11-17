<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/crm/DataTable.vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ProvinceWardSelect from '@/components/crm/ProvinceWardSelect.vue';
import RecordDrawer from '@/components/crm/RecordDrawer.vue';
import StageBadge from '@/components/crm/StageBadge.vue';
import TemperatureBadge from '@/components/crm/TemperatureBadge.vue';
import TierBadge from '@/components/crm/TierBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatDateTime, formatMoney, formatScore } from '@/lib/format';
import type { FilterDefinition, FilterOption, ListResult } from '@/types';

type DemoUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    data_scope: string;
    data_scope_label: string;
    created_at: string | null;
};

const props = defineProps<{
    users: ListResult<DemoUser>;
    dataScopeOptions: FilterOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nhà phát triển' },
            { title: 'Component dùng chung' },
        ],
    },
});

// --- List: FilterBar + DataTable on the shared ListQuery contract ---
const query = useListQuery(() => props.users, { only: ['users'] });

const filters: FilterDefinition[] = [
    {
        key: 'q',
        label: 'Tìm kiếm',
        type: 'text',
        placeholder: 'Tên, email, SĐT…',
    },
    {
        key: 'data_scope',
        label: 'Phạm vi dữ liệu',
        type: 'select',
        multiple: true,
        options: props.dataScopeOptions,
    },
    {
        key: 'is_active',
        label: 'Trạng thái',
        type: 'boolean',
        trueLabel: 'Đang hoạt động',
        falseLabel: 'Đã khoá',
    },
    { key: 'created_at', label: 'Ngày tạo', type: 'date' },
];

const columns: CrmColumn<DemoUser>[] = [
    {
        accessorKey: 'name',
        header: 'Họ tên',
        enableSorting: true,
        enableHiding: false,
    },
    { accessorKey: 'email', header: 'Email', enableSorting: true },
    { accessorKey: 'phone', header: 'Số điện thoại' },
    { accessorKey: 'data_scope_label', header: 'Phạm vi' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    {
        accessorKey: 'created_at',
        header: 'Ngày tạo',
        enableSorting: true,
        meta: { class: 'whitespace-nowrap' },
    },
];

// --- Drawer ---
const drawerOpen = ref(false);
const selected = ref<DemoUser | null>(null);

function openRow(row: DemoUser): void {
    selected.value = row;
    drawerOpen.value = true;
}

// --- Pickers ---
const ownerId = ref<number | string | null>(null);
const teamIds = ref<(number | string)[]>([]);
const provinceId = ref<number | string | null>(null);
const wardId = ref<number | string | null>(null);
</script>

<template>
    <Head title="Component dùng chung" />

    <div class="flex flex-col gap-8 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Component dùng chung</h1>
            <p class="text-sm text-muted-foreground">
                Trang chỉ có ở môi trường local. Bảng bên dưới là danh sách
                người dùng thật của tổ chức, chạy qua chuẩn list (lọc, sắp xếp,
                phân trang phía server).
            </p>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="font-medium">DataTable + FilterBar + RecordDrawer</h2>
            <DataTable
                :list="users"
                :query="query"
                :columns="columns"
                selectable
                row-clickable
                storage-key="dev-users"
                max-height="28rem"
                @row-click="openRow"
            >
                <template #toolbar>
                    <FilterBar :query="query" :filters="filters" />
                </template>
                <template #bulk-actions="{ selectedIds, clear }">
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-7"
                        @click="
                            toast.success(
                                `Giao người phụ trách cho ${selectedIds.length} bản ghi (demo)`,
                            );
                            clear();
                        "
                    >
                        Giao người
                    </Button>
                </template>
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                </template>
                <template #cell-phone="{ value }">
                    <span :class="{ 'text-muted-foreground': !value }">{{
                        value ?? '—'
                    }}</span>
                </template>
                <template #cell-is_active="{ value }">
                    <ToneBadge :tone="value ? 'green' : 'gray'">{{
                        value ? 'Đang hoạt động' : 'Đã khoá'
                    }}</ToneBadge>
                </template>
                <template #cell-created_at="{ row }">
                    {{ formatDateTime(row.created_at) }}
                </template>
            </DataTable>

            <RecordDrawer
                v-model:open="drawerOpen"
                :title="selected?.name"
                :description="selected?.email"
                size="md"
            >
                <dl
                    v-if="selected"
                    class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm"
                >
                    <dt class="text-muted-foreground">Số điện thoại</dt>
                    <dd class="col-span-2">{{ selected.phone ?? '—' }}</dd>
                    <dt class="text-muted-foreground">Phạm vi dữ liệu</dt>
                    <dd class="col-span-2">{{ selected.data_scope_label }}</dd>
                    <dt class="text-muted-foreground">Ngày tạo</dt>
                    <dd class="col-span-2">
                        {{ formatDateTime(selected.created_at) }}
                    </dd>
                </dl>
                <template #footer>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="drawerOpen = false"
                        >Đóng</Button
                    >
                </template>
            </RecordDrawer>
        </section>

        <section class="grid max-w-3xl gap-4">
            <h2 class="font-medium">EntityPicker &amp; ProvinceWardSelect</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="demo-owner">Người phụ trách (users)</Label>
                    <EntityPicker
                        id="demo-owner"
                        v-model="ownerId"
                        entity="users"
                        placeholder="Chọn người phụ trách"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="demo-teams">Nhóm (teams, chọn nhiều)</Label>
                    <EntityPicker
                        id="demo-teams"
                        v-model="teamIds"
                        entity="teams"
                        multiple
                        placeholder="Chọn nhóm"
                    />
                </div>
            </div>
            <ProvinceWardSelect
                v-model:province-id="provinceId"
                v-model:ward-id="wardId"
            />
            <p class="text-xs text-muted-foreground">
                Giá trị: owner={{ ownerId ?? 'null' }}, teams=[{{
                    teamIds.join(', ')
                }}], province={{ provinceId ?? 'null' }}, ward={{
                    wardId ?? 'null'
                }}. Danh mục tỉnh/xã có dữ liệu từ Task 1.1.
            </p>
        </section>

        <section class="grid gap-3">
            <h2 class="font-medium">Badge &amp; định dạng</h2>
            <div class="flex flex-wrap items-center gap-2">
                <StageBadge name="Mới" color="gray" />
                <StageBadge name="Đang tư vấn" color="blue" />
                <StageBadge name="Trúng tuyển" color="#7c3aed" />
                <StageBadge name="Nhập học" color="green" />
                <StageBadge name="Thất bại" color="red" />
                <TemperatureBadge value="hot" />
                <TemperatureBadge value="warm" />
                <TemperatureBadge value="cold" />
                <TierBadge tier="A" />
                <TierBadge tier="B" />
                <TierBadge tier="C" />
            </div>
            <p class="text-sm text-muted-foreground">
                {{ formatMoney(1250000) }} · điểm {{ formatScore(24.5) }} ·
                {{ formatDateTime(new Date()) }}
            </p>
        </section>
    </div>
</template>
