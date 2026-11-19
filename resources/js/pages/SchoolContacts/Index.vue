<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import type { FilterDefinition, ListResult } from '@/types';

type Row = {
    id: number;
    full_name: string;
    role_label: string | null;
    homeroom_class: string | null;
    high_school_id: number;
    high_school_name: string | null;
    phone: string | null;
    email: string | null;
    zalo: string | null;
    relationship_level: number | null;
    is_primary: boolean;
};

const props = defineProps<{ contacts: ListResult<Row>; options: { roles: { value: string; label: string }[] } }>();

const query = useListQuery(() => props.contacts, { only: ['contacts'] });

const filters = computed<FilterDefinition[]>(() => [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, SĐT, email…' },
    { key: 'role', label: 'Vai trò', type: 'select', options: props.options.roles },
    { key: 'high_school_id', label: 'Trường THPT', type: 'entity', entity: 'high_schools' },
]);

const columns: CrmColumn<Row>[] = [
    { accessorKey: 'full_name', header: 'Họ tên', enableSorting: true, enableHiding: false },
    { accessorKey: 'high_school_name', header: 'Trường THPT' },
    { accessorKey: 'role_label', header: 'Vai trò' },
    { accessorKey: 'phone', header: 'Điện thoại' },
    { accessorKey: 'email', header: 'Email' },
    { accessorKey: 'zalo', header: 'Zalo' },
    { accessorKey: 'relationship_level', header: 'Mức quan hệ' },
];

function open(row: Row): void {
    router.visit(`/high-schools/${row.high_school_id}`);
}
</script>

<template>
    <Head title="Người liên hệ" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Người liên hệ</h1>
            <p class="text-sm text-muted-foreground">Hiệu trưởng, tư vấn hướng nghiệp, giáo viên chủ nhiệm… của mọi trường THPT. Thêm hoặc sửa ở tab Người liên hệ trong trang trường.</p>
        </div>

        <DataTable :list="contacts" :query="query" :columns="columns" row-clickable storage-key="school-contacts" @row-click="open">
            <template #toolbar><FilterBar :query="query" :filters="filters" /></template>
            <template #cell-full_name="{ row }">
                <span class="font-medium">{{ row.full_name }}</span>
                <ToneBadge v-if="row.is_primary" tone="blue" class="ml-2">Đầu mối</ToneBadge>
                <div v-if="row.homeroom_class" class="text-xs text-muted-foreground">Lớp {{ row.homeroom_class }}</div>
            </template>
            <template #cell-high_school_name="{ row }">
                <Link :href="`/high-schools/${row.high_school_id}`" class="hover:underline" @click.stop>{{ row.high_school_name }}</Link>
            </template>
            <template #cell-role_label="{ row }">{{ row.role_label || '—' }}</template>
            <template #cell-phone="{ row }">{{ row.phone || '—' }}</template>
            <template #cell-email="{ row }">{{ row.email || '—' }}</template>
            <template #cell-zalo="{ row }">{{ row.zalo || '—' }}</template>
            <template #cell-relationship_level="{ row }">{{ row.relationship_level ?? '—' }}</template>
            <template #empty>Chưa có người liên hệ nào.</template>
        </DataTable>
    </div>
</template>
