<script setup lang="ts">
import { ref, watch } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';
import type { FilterValue } from '@/types';

/**
 * Bulk send of a message template to everyone matched by the list's current filters (POST /messages/preview, then
 * /messages/send). Only people who agreed to marketing messages and have the right contact are reached.
 */
const props = defineProps<{ templates: { id: number; name: string; channel_label: string }[]; filters: Record<string, FilterValue> }>();
const open = defineModel<boolean>('open', { default: false });

type Plan = { total: number; recipients: number; no_consent: number; no_contact: number; sample: string | null };

const templateId = ref<number | ''>('');
const plan = ref<Plan | null>(null);
const result = ref<{ queued: number } | null>(null);
const error = ref<string | null>(null);
const busy = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        templateId.value = props.templates[0]?.id ?? '';
        plan.value = null;
        result.value = null;
        error.value = null;
    }
});

watch(templateId, () => {
    plan.value = null;
    error.value = null;
});

/** Same shape as the list's query string: arrays joined by commas, booleans as 1/0. */
function serialize(): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(props.filters)
            .filter(([, v]) => !(v === '' || (Array.isArray(v) && v.length === 0)))
            .map(([k, v]) => [k, Array.isArray(v) ? v.join(',') : typeof v === 'boolean' ? (v ? '1' : '0') : v]),
    );
}

async function call<T>(url: string): Promise<T | null> {
    busy.value = true;
    error.value = null;

    try {
        return await requestJson<T>('POST', url, { template_id: templateId.value, filter: serialize() });
    } catch (e) {
        const err = e as HttpError;
        error.value = Object.values(err.errors)[0] || err.message;

        return null;
    } finally {
        busy.value = false;
    }
}

async function preview(): Promise<void> {
    plan.value = await call<Plan>('/messages/preview');
}

async function send(): Promise<void> {
    const done = await call<{ queued: number }>('/messages/send');

    if (done) {
        result.value = done;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg" data-test="send-message-dialog">
            <DialogHeader>
                <DialogTitle>Gửi tin cho thí sinh đang lọc</DialogTitle>
                <DialogDescription>Gửi cho mọi thí sinh khớp bộ lọc hiện tại của danh sách, không chỉ trang đang xem. Chỉ gửi cho người đã đồng ý nhận tin.</DialogDescription>
            </DialogHeader>

            <p v-if="!templates.length" class="text-sm text-muted-foreground">Chưa có mẫu tin đang dùng. Tạo ở Cài đặt, Mẫu tin nhắn.</p>

            <div v-else class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label for="sm-template">Mẫu tin</Label>
                    <select id="sm-template" v-model="templateId" :class="selectClass" :disabled="busy || result !== null" data-test="sm-template">
                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.channel_label }})</option>
                    </select>
                </div>

                <InputError :message="error ?? undefined" />

                <div v-if="plan" class="grid gap-2 rounded-md border p-3 text-sm" data-test="send-plan">
                    <p><strong>{{ plan.recipients }}</strong> người sẽ nhận tin, trong {{ plan.total }} thí sinh khớp bộ lọc.</p>
                    <p v-if="plan.no_consent" class="text-muted-foreground">{{ plan.no_consent }} người chưa đồng ý nhận tin nên bị bỏ qua.</p>
                    <p v-if="plan.no_contact" class="text-muted-foreground">{{ plan.no_contact }} người thiếu số điện thoại hoặc email nên bị bỏ qua.</p>
                    <div v-if="plan.sample">
                        <p class="text-xs text-muted-foreground">Tin mẫu (người đầu tiên):</p>
                        <p class="whitespace-pre-line">{{ plan.sample }}</p>
                    </div>
                </div>

                <p v-if="result" class="rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm" data-test="send-result">
                    Đã xếp hàng gửi {{ result.queued }} tin. Xem kết quả ở Marketing, Tin nhắn đã gửi.
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ result ? 'Đóng' : 'Huỷ' }}</Button>
                <Button v-if="templates.length && !result && !plan" :disabled="busy || templateId === ''" data-test="sm-preview" @click="preview"><Spinner v-if="busy" />Xem trước</Button>
                <Button v-if="plan && !result" :disabled="busy || plan.recipients === 0" data-test="sm-send" @click="send"><Spinner v-if="busy" />Gửi {{ plan.recipients }} tin</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
