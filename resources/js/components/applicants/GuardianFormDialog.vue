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
import { selectClass } from './types';
import type { GuardianRow, Option } from './types';

const props = defineProps<{
    applicantUuid: string;
    /** null = create */
    record: GuardianRow | null;
    relationships: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    full_name: '',
    relationship: '',
    phone: '',
    email: '',
    zalo_id: '',
    occupation: '',
    is_decision_maker: false,
    is_primary_contact: false,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        full_name: r?.full_name ?? '',
        relationship: r?.relationship ?? '',
        // a masked phone is never sent back
        phone: r && r.phone?.includes('*') ? '' : (r?.phone ?? ''),
        email: r?.email ?? '',
        zalo_id: r?.zalo_id ?? '',
        occupation: r?.occupation ?? '',
        is_decision_maker: r?.is_decision_maker ?? false,
        is_primary_contact: r?.is_primary_contact ?? false,
    };
});

function submit(): void {
    const data = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]));
    const base = `/applicants/${props.applicantUuid}/guardians`;

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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg" data-test="guardian-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa phụ huynh' : 'Thêm phụ huynh' }}</DialogTitle>
                    <DialogDescription>Phụ huynh trùng số điện thoại với người đã có sẽ được liên kết, không tạo bản sao.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="g-name">Họ tên <span class="text-destructive">*</span></Label>
                        <Input id="g-name" v-model="form.full_name" />
                        <InputError :message="errors.full_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-rel">Quan hệ</Label>
                        <select id="g-rel" v-model="form.relationship" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in relationships" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.relationship" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="g-phone">Điện thoại</Label>
                        <Input id="g-phone" v-model="form.phone" maxlength="20" :placeholder="record?.phone?.includes('*') ? 'Để trống = giữ nguyên' : ''" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-email">Email</Label>
                        <Input id="g-email" v-model="form.email" type="email" />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-zalo">Zalo</Label>
                        <Input id="g-zalo" v-model="form.zalo_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-job">Nghề nghiệp</Label>
                        <Input id="g-job" v-model="form.occupation" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_decision_maker" type="checkbox" class="size-4" />
                    Người quyết định chọn trường
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_primary_contact" type="checkbox" class="size-4" data-test="guardian-primary" />
                    Đầu mối liên hệ chính
                </label>

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
