<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Check, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ApplicationFormDialog from './ApplicationFormDialog.vue';
import { CONFIRM_ACTIONS, SOURCE_LABELS } from './types';
import type { ApplicationItem, ApplicationPanelProps } from './types';

/**
 * "Nguyện vọng" tab of the applicant page: the nguyện vọng of the case in the selected cycle, their score and
 * breakdown, the documents each method asks for, and the steps the user may take (submit, decide, confirm, withdraw).
 */
const props = defineProps<{ panel: ApplicationPanelProps }>();

const formOpen = ref(false);
const editing = ref<ApplicationItem | null>(null);
const scoring = ref<ApplicationItem | null>(null);
const bonus = ref<string>('');
const busy = ref(false);
const pending = ref<{ item: ApplicationItem; action: string; label: string } | null>(null);
const deleting = ref<ApplicationItem | null>(null);

function openForm(item: ApplicationItem | null): void {
    editing.value = item;
    formOpen.value = true;
}

function send(item: ApplicationItem, action: string): void {
    router.post(`/applications/${item.id}/transition`, { action }, { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => ((busy.value = false), (pending.value = null)) });
}

function act(item: ApplicationItem, action: string, label: string): void {
    CONFIRM_ACTIONS.has(action) ? (pending.value = { item, action, label }) : send(item, action);
}

function openScore(item: ApplicationItem): void {
    scoring.value = item;
    bonus.value = item.encourage_bonus === null ? '' : String(item.encourage_bonus);
}

function saveScore(): void {
    if (!scoring.value) {
        return;
    }

    router.post(`/applications/${scoring.value.id}/score`, { encourage_bonus: bonus.value === '' ? null : bonus.value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onSuccess: () => (scoring.value = null),
        onFinish: () => (busy.value = false),
    });
}

function remove(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(`/applications/${deleting.value.id}`, { preserveScroll: true, preserveState: true, onFinish: () => (deleting.value = null) });
}

const fmt = (n: number | null): string => (n === null ? '—' : n.toLocaleString('vi-VN', { maximumFractionDigits: 2 }));
</script>

<template>
    <section class="grid gap-3" data-test="applications-tab">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-medium">Nguyện vọng xét tuyển</h3>
                <p v-if="panel.cycle" class="text-xs text-muted-foreground">{{ panel.cycle }}{{ panel.closed ? ' · đã đóng (chỉ đọc)' : '' }}</p>
            </div>
            <Button v-if="panel.can_create && panel.options" size="sm" data-test="add-application" @click="openForm(null)"><Plus class="size-4" />Thêm nguyện vọng</Button>
        </div>

        <p v-if="!panel.case_uuid" class="rounded-md border p-4 text-sm text-muted-foreground" data-test="applications-no-case">
            Thí sinh chưa có hồ sơ tuyển sinh trong mùa đang chọn, nên chưa thêm được nguyện vọng.
        </p>
        <p v-else-if="panel.items.length === 0" class="rounded-md border p-4 text-sm text-muted-foreground" data-test="applications-empty">Chưa có nguyện vọng nào.</p>

        <article v-for="a in panel.items" :key="a.id" class="grid gap-2 rounded-md border p-3 text-sm" data-test="application-row">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">{{ a.preference_order }}</span>
                    <div>
                        <div class="font-medium">{{ a.major }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ a.method }}<template v-if="a.combination"> · tổ hợp {{ a.combination }}</template> · {{ a.round }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <ToneBadge :tone="a.status_tone">{{ a.status_label }}</ToneBadge>
                    <Button v-if="a.can.update" variant="ghost" size="icon" class="size-7" aria-label="Sửa" data-test="edit-application" @click="openForm(a)"><Pencil class="size-3.5" /></Button>
                    <Button v-if="a.can.delete" variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá nháp" data-test="delete-application" @click="deleting = a"><Trash2 class="size-3.5" /></Button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1" data-test="application-score">
                <template v-if="a.final_score !== null">
                    <span class="text-base font-semibold">{{ fmt(a.final_score) }} điểm</span>
                    <span class="text-xs text-muted-foreground">
                        gốc {{ fmt(a.raw_score) }} + ưu tiên {{ fmt(a.priority_bonus) }} + khuyến khích {{ fmt(a.encourage_bonus) }}
                    </span>
                    <span v-if="a.meets_minimum === false" class="flex items-center gap-1 text-xs text-destructive"><AlertTriangle class="size-3.5" />Dưới ngưỡng đầu vào ({{ a.breakdown.min_total }})</span>
                </template>
                <span v-else-if="a.breakdown.source === 'direct'" class="text-muted-foreground">Xét tuyển thẳng — không tính điểm.</span>
                <span v-else class="flex items-center gap-1 text-amber-600"><AlertTriangle class="size-3.5" />Chưa đủ điểm<template v-if="a.missing.length"> (thiếu: {{ a.missing.join(', ') }})</template></span>
                <Button v-if="a.can.score" variant="link" size="sm" class="h-auto p-0 text-xs" data-test="score-application" @click="openScore(a)">Tính lại / điểm khuyến khích</Button>
            </div>

            <details v-if="a.breakdown.source && a.breakdown.source !== 'direct'" class="text-xs text-muted-foreground">
                <summary class="cursor-pointer hover:text-foreground">Cách tính điểm</summary>
                <div class="mt-1 grid gap-0.5">
                    <div>Nguồn điểm: {{ SOURCE_LABELS[a.breakdown.source] ?? a.breakdown.source }} (thang {{ a.breakdown.scale_to }})</div>
                    <div v-for="s in a.breakdown.subjects ?? []" :key="s.subject">
                        {{ s.subject }}<template v-if="s.weight !== 1"> × {{ s.weight }}</template>: {{ s.score === null ? 'chưa có' : fmt(s.score) }}<template v-if="s.from_certificate"> (quy đổi từ chứng chỉ)</template>
                    </div>
                    <div v-if="a.breakdown.exam">{{ a.breakdown.exam.name ?? 'Bài thi' }}: {{ a.breakdown.exam.score }}/{{ a.breakdown.exam.max }}</div>
                    <div v-if="a.breakdown.priority">
                        Ưu tiên: KV {{ fmt(a.breakdown.priority.area) }} + đối tượng {{ fmt(a.breakdown.priority.group) }}<template v-if="a.breakdown.priority.tapered"> → giảm dần còn {{ fmt(a.breakdown.priority.applied) }}</template>
                    </div>
                </div>
            </details>

            <div v-if="a.required_documents.length" class="flex flex-wrap items-center gap-1.5 text-xs" data-test="required-documents">
                <span class="text-muted-foreground">Giấy tờ cần:</span>
                <span v-for="d in a.required_documents" :key="d.type" class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5" :class="d.approved ? 'border-green-600/40 text-green-700' : 'text-muted-foreground'">
                    <Check v-if="d.approved" class="size-3" />{{ d.label }}
                </span>
            </div>

            <div v-if="a.actions.length" class="flex flex-wrap gap-2 pt-1">
                <Button
                    v-for="x in a.actions"
                    :key="x.action"
                    size="sm"
                    :variant="x.action === 'withdraw' || x.action === 'reject' || x.action === 'ineligible' ? 'outline' : x.action === 'submit' || x.action === 'admit' ? 'default' : 'secondary'"
                    :disabled="busy"
                    :data-test="`app-action-${x.action}`"
                    @click="act(a, x.action, x.label)"
                >{{ x.label }}</Button>
            </div>
        </article>
    </section>

    <ApplicationFormDialog v-if="panel.options && panel.case_uuid" v-model:open="formOpen" :case-uuid="panel.case_uuid" :record="editing" :options="panel.options" />

    <Dialog :open="scoring !== null" @update:open="(v) => !v && (scoring = null)">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Tính lại điểm</DialogTitle>
                <DialogDescription>Điểm được tính lại từ điểm hiện có của thí sinh. Điểm khuyến khích bị giới hạn theo công thức của phương thức.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-1.5">
                <Label for="ap-bonus">Điểm khuyến khích</Label>
                <Input id="ap-bonus" v-model="bonus" type="number" step="0.01" min="0" data-test="ap-bonus" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="scoring = null">Huỷ</Button>
                <Button :disabled="busy" data-test="ap-score-save" @click="saveScore">Tính lại</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="pending !== null" @update:open="(v) => !v && (pending = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ pending?.label }} — nguyện vọng {{ pending?.item.preference_order }}?</DialogTitle>
                <DialogDescription>{{ pending?.item.major }}. Có thể xem lại trong timeline của thí sinh.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="pending = null">Huỷ</Button>
                <Button variant="destructive" :disabled="busy" data-test="confirm-app-action" @click="pending && send(pending.item, pending.action)">Xác nhận</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá nguyện vọng nháp {{ deleting?.preference_order }}?</DialogTitle>
                <DialogDescription>{{ deleting?.major }}. Chỉ nguyện vọng nháp mới xoá được; nguyện vọng đã nộp hãy chọn "Rút".</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-application" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
