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
import type { Option, RoundRow } from './types';

const props = defineProps<{
    cycleId: number;
    /** null = create */
    record: RoundRow | null;
    typeOptions: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    type: '',
    apply_start: '',
    apply_end: '',
    result_date: '',
    confirm_deadline: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        name: r?.name ?? '',
        type: r?.type ?? '',
        apply_start: r?.apply_start ?? '',
        apply_end: r?.apply_end ?? '',
        result_date: r?.result_date ?? '',
        confirm_deadline: r?.confirm_deadline ?? '',
    };
});

function submit(): void {
    const data = Object.fromEntries(
        Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]),
    );
    const base = `/settings/cycles/${props.cycleId}/rounds`;

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
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            data-test="round-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa đợt xét tuyển' : 'Thêm đợt xét tuyển'
                    }}</DialogTitle>
                    <DialogDescription>
                        Thời gian nhập theo giờ Việt Nam (GMT+7).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="r-name"
                            >Tên đợt
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="r-name"
                            v-model="form.name"
                            placeholder="VD: Đợt 1"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-type">Loại</Label>
                        <select
                            id="r-type"
                            v-model="form.type"
                            :class="selectClass"
                        >
                            <option value="">—</option>
                            <option
                                v-for="o in typeOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="r-start">Mở đăng ký</Label>
                        <Input
                            id="r-start"
                            v-model="form.apply_start"
                            type="datetime-local"
                        />
                        <InputError :message="errors.apply_start" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-end">Đóng đăng ký</Label>
                        <Input
                            id="r-end"
                            v-model="form.apply_end"
                            type="datetime-local"
                        />
                        <InputError :message="errors.apply_end" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-result">Ngày công bố kết quả</Label>
                        <Input
                            id="r-result"
                            v-model="form.result_date"
                            type="date"
                        />
                        <InputError :message="errors.result_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-confirm">Hạn xác nhận nhập học</Label>
                        <Input
                            id="r-confirm"
                            v-model="form.confirm_deadline"
                            type="datetime-local"
                        />
                        <InputError :message="errors.confirm_deadline" />
                    </div>
                </div>

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
