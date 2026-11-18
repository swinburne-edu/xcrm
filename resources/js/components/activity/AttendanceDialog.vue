<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass, textareaClass } from './types';
import type { AppointmentRow } from './types';

const props = defineProps<{ record: AppointmentRow | null }>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ attendance: 'attended', result: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        errors.value = {};
        form.value = { attendance: 'attended', result: props.record?.result ?? '' };
    }
});

function submit(): void {
    router.post(`/appointments/${props.record!.id}/attendance`, form.value, {
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
        <DialogContent class="sm:max-w-md" data-test="attendance-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Ghi nhận kết quả</DialogTitle>
                    <DialogDescription>
                        {{ record?.type_label }} · {{ record?.applicant_name }}. Sau khi ghi nhận không sửa được lịch hẹn.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="at-attendance">Kết quả</Label>
                    <select id="at-attendance" v-model="form.attendance" :class="selectClass" data-test="attendance-select">
                        <option value="attended">Có đến</option>
                        <option value="no_show">Vắng mặt</option>
                        <option value="cancelled">Đã huỷ</option>
                    </select>
                    <InputError :message="errors.attendance" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="at-result">Ghi chú kết quả</Label>
                    <textarea id="at-result" v-model="form.result" rows="3" :class="textareaClass" />
                    <InputError :message="errors.result" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Đóng</Button>
                    <Button type="submit" :disabled="processing" data-test="attendance-save">
                        <Spinner v-if="processing" />
                        Ghi nhận
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
