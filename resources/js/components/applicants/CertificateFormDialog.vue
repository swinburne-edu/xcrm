<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from './types';
import type { CertificateRow, Option } from './types';

const props = defineProps<{
    applicantUuid: string;
    /** null = create */
    record: CertificateRow | null;
    types: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ type: 'IELTS', score: '', issued_at: '', expires_at: '' });

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = { type: r?.type ?? 'IELTS', score: r?.score ?? '', issued_at: r?.issued_at ?? '', expires_at: r?.expires_at ?? '' };
});

function submit(): void {
    const data = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]));
    const base = `/applicants/${props.applicantUuid}/certificates`;

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
        <DialogContent class="sm:max-w-md" data-test="certificate-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa chứng chỉ' : 'Thêm chứng chỉ' }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="ce-type">Loại</Label>
                        <select id="ce-type" v-model="form.type" :class="selectClass">
                            <option v-for="o in types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ce-score">Điểm</Label>
                        <Input id="ce-score" v-model="form.score" maxlength="20" placeholder="6.5" />
                        <InputError :message="errors.score" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ce-issued">Ngày cấp</Label>
                        <Input id="ce-issued" v-model="form.issued_at" type="date" />
                        <InputError :message="errors.issued_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ce-expires">Ngày hết hạn</Label>
                        <Input id="ce-expires" v-model="form.expires_at" type="date" />
                        <InputError :message="errors.expires_at" />
                    </div>
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
