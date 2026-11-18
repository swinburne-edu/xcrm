<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/crm/DataTable.vue';
import FilterBar from '@/components/crm/FilterBar.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import TeamFormDialog from '@/components/settings/TeamFormDialog.vue';
import type { TeamRow } from '@/components/settings/TeamFormDialog.vue';
import UserFormDialog from '@/components/settings/UserFormDialog.vue';
import type { UserRow } from '@/components/settings/UserFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useListQuery } from '@/composables/useListQuery';
import type { CrmColumn } from '@/lib/dataTable';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FilterDefinition, FilterOption, ListResult } from '@/types';

type RoleRow = {
    code: string;
    label: string;
    scope_label: string;
    users_count: number;
    permissions_count: number;
    permissions: { object: string; label: string; actions: string[] }[];
};

const props = defineProps<{
    tab: 'users' | 'teams' | 'roles';
    tabs: { key: string; title: string; href: string }[];
    users?: ListResult<UserRow>;
    teams?: ListResult<TeamRow>;
    roles?: RoleRow[];
    roleOptions?: FilterOption[];
    scopeOptions?: FilterOption[];
    roleScopes?: Record<string, string>;
    can: { create?: boolean; update?: boolean; delete?: boolean };
}>();

const ACTION_LABELS: Record<string, string> = {
    view: 'xem',
    create: 'thêm',
    update: 'sửa',
    delete: 'xoá',
    export: 'xuất',
    import: 'nhập',
    assign: 'giao',
    merge: 'gộp',
    approve: 'duyệt',
    manage: 'quản lý',
    view_sensitive: 'xem SĐT/CCCD đầy đủ',
};

// --- Users tab ---
const userQuery = props.users
    ? useListQuery(() => props.users!, { only: ['users'] })
    : null;
const userFilters = computed<FilterDefinition[]>(() => [
    {
        key: 'q',
        label: 'Tìm kiếm',
        type: 'text',
        placeholder: 'Tên, email, SĐT…',
    },
    {
        key: 'role',
        label: 'Vai trò',
        type: 'select',
        multiple: true,
        options: props.roleOptions ?? [],
    },
    {
        key: 'data_scope',
        label: 'Phạm vi',
        type: 'select',
        options: props.scopeOptions ?? [],
    },
    {
        key: 'is_active',
        label: 'Trạng thái',
        type: 'boolean',
        trueLabel: 'Đang hoạt động',
        falseLabel: 'Đã khoá',
    },
]);
const userColumns: CrmColumn<UserRow>[] = [
    {
        accessorKey: 'name',
        header: 'Họ tên',
        enableSorting: true,
        enableHiding: false,
    },
    { accessorKey: 'email', header: 'Email', enableSorting: true },
    { accessorKey: 'role_label', header: 'Vai trò' },
    { accessorKey: 'data_scope_label', header: 'Phạm vi' },
    { accessorKey: 'teams_label', header: 'Nhóm' },
    { accessorKey: 'is_active', header: 'Trạng thái' },
    {
        accessorKey: 'last_login_at',
        header: 'Đăng nhập gần nhất',
        enableSorting: true,
    },
    {
        id: 'actions',
        header: '',
        enableHiding: false,
        meta: { class: 'w-12 text-right' },
    },
];

// --- Teams tab ---
const teamQuery = props.teams
    ? useListQuery(() => props.teams!, { only: ['teams'] })
    : null;
const teamFilters: FilterDefinition[] = [
    { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên nhóm…' },
];
const teamColumns: CrmColumn<TeamRow>[] = [
    {
        accessorKey: 'name',
        header: 'Nhóm',
        enableSorting: true,
        enableHiding: false,
    },
    { accessorKey: 'leader_name', header: 'Trưởng nhóm' },
    {
        accessorKey: 'members_count',
        header: 'Số thành viên',
        enableSorting: true,
    },
    {
        id: 'actions',
        header: '',
        enableHiding: false,
        meta: { class: 'w-12 text-right' },
    },
];

// --- Create / edit / delete (users and teams share the dialogs below) ---
const userForm = ref(false);
const teamForm = ref(false);
const editingUser = ref<UserRow | null>(null);
const editingTeam = ref<TeamRow | null>(null);
const deleting = ref<{
    kind: 'users' | 'teams';
    id: number;
    label: string;
} | null>(null);
const deleteOpen = ref(false);

function openCreate(): void {
    if (props.tab === 'users') {
        editingUser.value = null;
        userForm.value = true;
    } else {
        editingTeam.value = null;
        teamForm.value = true;
    }
}

function editUser(row: UserRow): void {
    if (props.can.update) {
        editingUser.value = row;
        userForm.value = true;
    }
}

function editTeam(row: TeamRow): void {
    if (props.can.update) {
        editingTeam.value = row;
        teamForm.value = true;
    }
}

function askDelete(kind: 'users' | 'teams', id: number, label: string): void {
    deleting.value = { kind, id, label };
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    const { kind, id } = deleting.value;
    router.delete(
        kind === 'users' ? `/settings/users/${id}` : `/settings/teams/${id}`,
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (deleteOpen.value = false),
        },
    );
}
</script>

<template>
    <Head title="Người dùng & vai trò" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Người dùng &amp; vai trò</h1>
                <p class="text-sm text-muted-foreground">
                    Tài khoản, nhóm và quyền của tổ chức. Vai trò cho biết làm
                    được gì; phạm vi dữ liệu cho biết thấy được bản ghi của ai.
                </p>
            </div>
            <Button
                v-if="tab !== 'roles' && can.create"
                data-test="create"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Thêm
            </Button>
        </div>

        <nav
            class="-mx-1 flex gap-1 overflow-x-auto border-b px-1"
            aria-label="Người dùng & vai trò"
        >
            <Link
                v-for="t in tabs"
                :key="t.key"
                :href="t.href"
                :class="
                    cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                        t.key === tab
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )
                "
                :aria-current="t.key === tab ? 'page' : undefined"
            >
                {{ t.title }}
            </Link>
        </nav>

        <!-- Users -->
        <DataTable
            v-if="tab === 'users' && users && userQuery"
            :list="users"
            :query="userQuery"
            :columns="userColumns"
            :row-clickable="can.update"
            storage-key="settings-users"
            @row-click="editUser"
        >
            <template #toolbar
                ><FilterBar :query="userQuery" :filters="userFilters"
            /></template>
            <template #cell-name="{ row }">
                <span class="font-medium">{{ row.name }}</span>
                <Badge v-if="row.is_self" variant="secondary" class="ml-2"
                    >Bạn</Badge
                >
            </template>
            <template #cell-role_label="{ row }">
                <ToneBadge v-if="row.role_label" tone="violet">{{
                    row.role_label
                }}</ToneBadge>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-teams_label="{ row }">{{
                row.teams_label || '—'
            }}</template>
            <template #cell-is_active="{ row }">
                <ToneBadge :tone="row.is_active ? 'green' : 'gray'">{{
                    row.is_active ? 'Đang hoạt động' : 'Đã khoá'
                }}</ToneBadge>
            </template>
            <template #cell-last_login_at="{ row }">{{
                formatDateTime(row.last_login_at, 'Chưa đăng nhập')
            }}</template>
            <template #cell-actions="{ row }">
                <DropdownMenu v-if="can.update || can.delete">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thao tác"
                            ><MoreHorizontal class="size-4"
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-if="can.update"
                            @select="editUser(row)"
                            ><Pencil class="size-4" />Sửa</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="can.delete && !row.is_self"
                            class="text-destructive focus:text-destructive"
                            @select="askDelete('users', row.id, row.name)"
                            ><Trash2 class="size-4" />Xoá</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </DataTable>

        <!-- Teams -->
        <DataTable
            v-else-if="tab === 'teams' && teams && teamQuery"
            :list="teams"
            :query="teamQuery"
            :columns="teamColumns"
            :row-clickable="can.update"
            storage-key="settings-teams"
            @row-click="editTeam"
        >
            <template #toolbar
                ><FilterBar :query="teamQuery" :filters="teamFilters"
            /></template>
            <template #cell-name="{ row }"
                ><span class="font-medium">{{ row.name }}</span></template
            >
            <template #cell-leader_name="{ row }">{{
                row.leader_name || '—'
            }}</template>
            <template #cell-actions="{ row }">
                <DropdownMenu v-if="can.update || can.delete">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thao tác"
                            ><MoreHorizontal class="size-4"
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-if="can.update"
                            @select="editTeam(row)"
                            ><Pencil class="size-4" />Sửa</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="can.delete"
                            class="text-destructive focus:text-destructive"
                            @select="askDelete('teams', row.id, row.name)"
                            ><Trash2 class="size-4" />Xoá</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </DataTable>

        <!-- Roles (read-only) -->
        <div
            v-else-if="tab === 'roles' && roles"
            class="grid gap-3 lg:grid-cols-2"
            data-test="roles"
        >
            <section
                v-for="role in roles"
                :key="role.code"
                class="rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-medium">{{ role.label }}</h2>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ role.code }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <ToneBadge tone="blue">{{
                            role.scope_label
                        }}</ToneBadge>
                        <ToneBadge tone="gray"
                            >{{ role.users_count }} người dùng</ToneBadge
                        >
                    </div>
                </div>
                <details class="mt-3 text-sm">
                    <summary
                        class="cursor-pointer text-muted-foreground hover:text-foreground"
                    >
                        {{ role.permissions_count }} quyền
                    </summary>
                    <dl class="mt-2 grid gap-1.5">
                        <div
                            v-for="p in role.permissions"
                            :key="p.object"
                            class="grid grid-cols-[9rem_1fr] gap-2"
                        >
                            <dt class="text-muted-foreground">{{ p.label }}</dt>
                            <dd>
                                {{
                                    p.actions
                                        .map((a) => ACTION_LABELS[a] ?? a)
                                        .join(', ')
                                }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </section>
        </div>
    </div>

    <UserFormDialog
        v-if="tab === 'users'"
        v-model:open="userForm"
        :record="editingUser"
        :role-options="roleOptions ?? []"
        :scope-options="scopeOptions ?? []"
        :role-scopes="roleScopes ?? {}"
    />
    <TeamFormDialog
        v-if="tab === 'teams'"
        v-model:open="teamForm"
        :record="editingTeam"
    />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ deleting?.label }}?</DialogTitle>
                <DialogDescription>
                    {{
                        deleting?.kind === 'users'
                            ? 'Người dùng sẽ không đăng nhập được nữa; dữ liệu họ đã tạo vẫn được giữ.'
                            : 'Nhóm bị xoá và các thành viên được gỡ khỏi nhóm.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false"
                    >Huỷ</Button
                >
                <Button
                    variant="destructive"
                    data-test="confirm-delete"
                    @click="confirmDelete"
                    >Xoá</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
