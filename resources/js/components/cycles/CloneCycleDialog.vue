<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import type { CycleRow } from './types';

const props = defineProps<{ source: CycleRow | null }>();
const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ name: '', year: '' as number | string });

watch(open, (isOpen) => {
    if (!isOpen || !props.source) {
        return;
    }

    errors.value = {};
    const year = props.source.year + 1;
    form.value = { name: `Tuyển sinh năm ${year}`, year };
});

function submit(): void {
    if (!props.source) {
        return;
    }

    router.post(`/settings/cycles/${props.source.id}/clone`, form.value as never, {
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md" data-test="clone-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Sao chép từ {{ source?.name }}</DialogTitle>
                    <DialogDescription>
                        Tạo mùa mới (chuẩn bị) với các đợt (dời mốc thời gian theo
                        số năm chênh), phương thức và công thức điểm, chỉ tiêu cũ
                        làm gợi ý, tổ hợp và phương thức của từng ngành. Không sao
                        chép thí sinh, hồ sơ hay chiến dịch.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="cl-name">Tên mùa mới</Label>
                    <Input id="cl-name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cl-year">Năm</Label>
                    <Input id="cl-year" v-model="form.year" type="number" min="2000" max="2200" />
                    <InputError :message="errors.year" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="confirm-clone">
                        <Spinner v-if="processing" />
                        Sao chép
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
