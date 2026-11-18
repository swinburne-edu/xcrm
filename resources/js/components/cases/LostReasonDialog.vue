<script setup lang="ts">
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
import { Label } from '@/components/ui/label';
import { selectClass } from './types';
import type { Option } from './types';

/** Asks for the lost reason (rule R6) before a case enters a "lost" stage. */
const props = defineProps<{ reasons: Option[]; subject?: string | null }>();
const emit = defineEmits<{ confirm: [payload: { lost_reason_id: string; lost_note: string | null }]; cancel: [] }>();
const open = defineModel<boolean>('open', { default: false });

const reason = ref('');
const note = ref('');
const error = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
        note.value = '';
        error.value = null;
    }
});

function confirm(): void {
    if (!reason.value) {
        error.value = 'Chọn lý do thất bại.';

        return;
    }

    emit('confirm', { lost_reason_id: reason.value, lost_note: note.value.trim() || null });
    open.value = false;
}

function dismiss(next: boolean): void {
    open.value = next;

    if (!next) {
        emit('cancel');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="dismiss">
        <DialogContent class="sm:max-w-md" data-test="lost-dialog">
            <DialogHeader>
                <DialogTitle>Chuyển sang thất bại{{ props.subject ? `: ${props.subject}` : '' }}</DialogTitle>
                <DialogDescription>Bắt buộc chọn lý do để báo cáo chính xác.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-1.5">
                <Label for="lost-reason">Lý do</Label>
                <select id="lost-reason" v-model="reason" :class="selectClass" data-test="lost-reason">
                    <option value="" disabled>Chọn lý do</option>
                    <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
                <InputError :message="error ?? undefined" />
            </div>
            <div class="grid gap-1.5">
                <Label for="lost-note">Ghi chú</Label>
                <textarea
                    id="lost-note"
                    v-model="note"
                    rows="3"
                    class="w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="dismiss(false)">Huỷ</Button>
                <Button variant="destructive" data-test="lost-confirm" @click="confirm">Xác nhận</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
