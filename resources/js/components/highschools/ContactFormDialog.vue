<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';
import { selectClass, textareaClass } from './types';
import type { ContactRow, Option } from './types';

const props = defineProps<{
    schoolId: number;
    /** null = create */
    record: ContactRow | null;
    roles: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    full_name: '',
    role: '',
    homeroom_class: '',
    phone: '',
    email: '',
    zalo: '',
    relationship_level: '' as number | string,
    is_primary: false,
    note: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        full_name: r?.full_name ?? '',
        role: r?.role ?? '',
        homeroom_class: r?.homeroom_class ?? '',
        phone: r?.phone ?? '',
        email: r?.email ?? '',
        zalo: r?.zalo ?? '',
        relationship_level: r?.relationship_level ?? '',
        is_primary: r?.is_primary ?? false,
        note: r?.note ?? '',
    };
});

function submit(): void {
    const data = Object.fromEntries(
        Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]),
    );
    const base = `/high-schools/${props.schoolId}/contacts`;

    router.visit(isEdit.value ? `${base}/${props.record!.id}` : base, {
        method: isEdit.value ? 'put' : 'post',
        data: data as never,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg" data-test="contact-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa người liên hệ' : 'Thêm người liên hệ' }}</DialogTitle>
                    <DialogDescription>Mỗi trường có tối đa một người liên hệ chính.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="c-fullname">Họ tên <span class="text-destructive">*</span></Label>
                    <Input id="c-fullname" v-model="form.full_name" />
                    <InputError :message="errors.full_name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="c-role">Chức vụ</Label>
                        <select id="c-role" v-model="form.role" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in roles" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-class">Lớp chủ nhiệm</Label>
                        <Input id="c-class" v-model="form.homeroom_class" maxlength="20" placeholder="VD: 12A1" />
                        <InputError :message="errors.homeroom_class" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-phone">Điện thoại</Label>
                        <Input id="c-phone" v-model="form.phone" maxlength="20" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-zalo">Zalo</Label>
                        <Input id="c-zalo" v-model="form.zalo" maxlength="50" />
                        <InputError :message="errors.zalo" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-email">Email</Label>
                        <Input id="c-email" v-model="form.email" type="email" />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-level">Mức độ quan hệ (1–5)</Label>
                        <Input id="c-level" v-model="form.relationship_level" type="number" min="1" max="5" />
                        <InputError :message="errors.relationship_level" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_primary" type="checkbox" class="size-4" data-test="is-primary" />
                    Người liên hệ chính của trường
                </label>

                <div class="grid gap-1.5">
                    <Label for="c-note">Ghi chú</Label>
                    <textarea id="c-note" v-model="form.note" rows="2" :class="textareaClass" />
                    <InputError :message="errors.note" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm mới' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
