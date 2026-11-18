<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { FilterOption } from '@/types';

export type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string | null;
    role_label: string | null;
    data_scope: string;
    data_scope_label: string;
    is_active: boolean;
    is_self: boolean;
    team_ids: number[];
    teams: { id: number; label: string }[];
    teams_label: string;
    last_login_at: string | null;
};

const props = defineProps<{
    /** null = create */
    record: UserRow | null;
    roleOptions: FilterOption[];
    scopeOptions: FilterOption[];
    /** Default data scope of each role (applied when the role changes). */
    roleScopes: Record<string, string>;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    email: '',
    phone: '',
    password: '',
    role: '' as string,
    data_scope: '' as string,
    team_ids: [] as (number | string)[],
    is_active: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        name: r?.name ?? '',
        email: r?.email ?? '',
        phone: r?.phone ?? '',
        password: '',
        role: r?.role ?? '',
        data_scope: r?.data_scope ?? '',
        team_ids: [...(r?.team_ids ?? [])],
        is_active: r?.is_active ?? true,
    };
});

function onRoleChange(role: unknown): void {
    form.value.role = String(role);

    // A new role brings its default data scope; an existing user keeps theirs unless they pick again.
    if (!isEdit.value || props.record?.role !== form.value.role) {
        form.value.data_scope = props.roleScopes[form.value.role] ?? '';
    }
}

function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(
        ([k]) => k === key || k.startsWith(`${key}.`),
    )?.[1];
}

function submit(): void {
    router.visit(
        isEdit.value
            ? `/settings/users/${props.record!.id}`
            : '/settings/users',
        {
            method: isEdit.value ? 'put' : 'post',
            data: { ...form.value } as never,
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onError: (e) => (errors.value = e),
            onSuccess: () => (open.value = false),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            data-test="user-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa người dùng' : 'Thêm người dùng'
                    }}</DialogTitle>
                    <DialogDescription>
                        Vai trò quyết định thao tác được làm; phạm vi dữ liệu
                        quyết định thấy bản ghi của ai.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="u-name"
                        >Họ tên <span class="text-destructive">*</span></Label
                    >
                    <Input id="u-name" v-model="form.name" autocomplete="off" />
                    <InputError :message="fieldError('name')" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="u-email"
                            >Email
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="u-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                        />
                        <InputError :message="fieldError('email')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="u-phone">Số điện thoại</Label>
                        <Input
                            id="u-phone"
                            v-model="form.phone"
                            placeholder="0912 345 678"
                        />
                        <InputError :message="fieldError('phone')" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="u-password">
                        Mật khẩu
                        <span v-if="!isEdit" class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="u-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="isEdit ? 'Để trống nếu không đổi' : ''"
                    />
                    <InputError :message="fieldError('password')" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="u-role"
                            >Vai trò
                            <span class="text-destructive">*</span></Label
                        >
                        <Select
                            :model-value="form.role || undefined"
                            @update:model-value="onRoleChange"
                        >
                            <SelectTrigger id="u-role" class="w-full"
                                ><SelectValue placeholder="Chọn vai trò"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in roleOptions"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="fieldError('role')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="u-scope">Phạm vi dữ liệu</Label>
                        <Select
                            :model-value="form.data_scope || undefined"
                            @update:model-value="
                                (v) => (form.data_scope = String(v))
                            "
                        >
                            <SelectTrigger id="u-scope" class="w-full"
                                ><SelectValue placeholder="Theo vai trò"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in scopeOptions"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="fieldError('data_scope')" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="u-teams">Nhóm</Label>
                    <EntityPicker
                        id="u-teams"
                        v-model="form.team_ids"
                        entity="teams"
                        multiple
                        placeholder="Chọn nhóm"
                        :initial-items="
                            (record?.teams ?? []).map((t) => ({
                                id: t.id,
                                label: t.label,
                                description: null,
                            }))
                        "
                    />
                    <InputError :message="fieldError('team_ids')" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="u-active"
                        :model-value="form.is_active"
                        :disabled="record?.is_self"
                        @update:model-value="
                            (v) => (form.is_active = v === true)
                        "
                    />
                    <Label for="u-active" class="font-normal"
                        >Đang hoạt động (được đăng nhập)</Label
                    >
                </div>
                <InputError :message="fieldError('is_active')" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Huỷ</Button
                    >
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm mới' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
