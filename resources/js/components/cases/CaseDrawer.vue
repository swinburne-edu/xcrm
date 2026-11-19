<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { ref, watch } from 'vue';
import CustomFieldsCard from '@/components/custom/CustomFieldsCard.vue';
import type { CustomPanel } from '@/components/custom/types';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import RecordDrawer from '@/components/crm/RecordDrawer.vue';
import TemperatureBadge from '@/components/crm/TemperatureBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LostReasonDialog from './LostReasonDialog.vue';
import { formatDateTime, selectClass } from './types';
import type { CaseDetail, CaseOptions } from './types';

/**
 * Quick view of a case (row / card click): stage stepper and move, owner (assign / claim / back to the pool),
 * the day-to-day fields, and the last stage changes. Data comes from GET /cases/{uuid}.
 */
const props = defineProps<{ uuid: string | null; options: CaseOptions }>();
const open = defineModel<boolean>('open', { default: false });

const TONES: BadgeTone[] = ['gray', 'blue', 'green', 'amber', 'red', 'violet', 'cyan', 'pink'];
const detail = ref<CaseDetail | null>(null);
const loading = ref(false);
const processing = ref(false);
const message = ref<string | null>(null);
const lostOpen = ref(false);
const pendingStage = ref<number | null>(null);

const form = ref({
    temperature: '',
    next_action_at: '',
    primary_major_id: null as number | string | null,
    source_id: '',
});
const newOwner = ref<number | string | null>(null);
const targetStage = ref('');

async function load(): Promise<void> {
    if (!props.uuid) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/cases/${props.uuid}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const c = (await response.json()).case as CaseDetail;
            detail.value = c;
            form.value = {
                temperature: c.temperature ?? '',
                next_action_at: c.next_action_input ?? '',
                primary_major_id: c.primary_major_id,
                source_id: c.source_id ? String(c.source_id) : '',
            };
            newOwner.value = null;
            targetStage.value = '';
        }
    } finally {
        loading.value = false;
    }
}

watch(() => [open.value, props.uuid] as const, ([isOpen]) => {
    if (isOpen) {
        message.value = null;
        detail.value = null;
        void load();
    }
});

function tone(color: string | null): BadgeTone {
    return TONES.includes(color as BadgeTone) ? (color as BadgeTone) : 'gray';
}

function send(method: 'post' | 'put', url: string, data: Record<string, unknown> = {}): void {
    router[method](url, data as never, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processing.value = true;
            message.value = null;
        },
        onError: (errors) => (message.value = Object.values(errors)[0] ?? 'Không thực hiện được.'),
        onSuccess: () => void load(),
        onFinish: () => (processing.value = false),
    });
}

function moveTo(): void {
    const stage = props.options.stages.find((s) => s.value === targetStage.value);

    if (!stage || !detail.value) {
        return;
    }

    if (stage.type === 'lost') {
        pendingStage.value = Number(stage.value);
        lostOpen.value = true;

        return;
    }

    send('post', `/cases/${detail.value.uuid}/move`, { stage_id: stage.value });
}

function confirmLost(payload: { lost_reason_id: string; lost_note: string | null }): void {
    if (detail.value && pendingStage.value) {
        send('post', `/cases/${detail.value.uuid}/move`, { stage_id: pendingStage.value, ...payload });
    }
}

function save(): void {
    if (!detail.value) {
        return;
    }

    send('put', `/cases/${detail.value.uuid}`, {
        temperature: form.value.temperature || null,
        next_action_at: form.value.next_action_at || null,
        primary_major_id: form.value.primary_major_id,
        source_id: form.value.source_id || null,
    });
}

function assign(ownerId: number | string | null): void {
    if (detail.value) {
        send('post', `/cases/${detail.value.uuid}/assign`, { owner_id: ownerId });
    }
}

function claim(): void {
    if (detail.value) {
        send('post', `/cases/${detail.value.uuid}/claim`);
    }
}
</script>

<template>
    <RecordDrawer
        v-model:open="open"
        :title="detail?.applicant_name ?? ''"
        :description="detail ? `${detail.applicant_code} · ${detail.stage_name}` : ''"
        :href="detail?.applicant_uuid ? `/applicants/${detail.applicant_uuid}` : null"
        :loading="loading && !detail"
        size="lg"
    >
        <div v-if="detail" class="grid gap-5 p-5 text-sm" data-test="case-detail">
            <p v-if="message" class="rounded-md border border-red-300 bg-red-50 p-2 text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200" role="alert" data-test="case-error">
                {{ message }}
            </p>
            <p v-if="detail.cycle_closed" class="text-amber-700 dark:text-amber-400">Mùa tuyển sinh đã đóng — hồ sơ chỉ đọc.</p>

            <section class="grid gap-2" data-test="stepper">
                <div class="flex flex-wrap gap-1">
                    <ToneBadge
                        v-for="s in detail.stages"
                        :key="s.id"
                        :tone="s.current ? tone(s.color) : 'gray'"
                        :class="s.current ? 'ring-2 ring-primary' : 'opacity-60'"
                        >{{ s.name }}</ToneBadge
                    >
                </div>
                <div v-if="detail.can.update" class="flex gap-2">
                    <select v-model="targetStage" :class="selectClass" data-test="target-stage">
                        <option value="">Chuyển sang giai đoạn…</option>
                        <option v-for="s in options.stages" :key="s.value" :value="s.value" :disabled="Number(s.value) === detail.stage_id">{{ s.label }}</option>
                    </select>
                    <Button :disabled="!targetStage || processing" data-test="move-stage" @click="moveTo">Chuyển</Button>
                </div>
                <p v-if="detail.status === 'lost'" class="text-muted-foreground">Thất bại: {{ detail.lost_reason ?? '—' }}<template v-if="detail.lost_note"> — {{ detail.lost_note }}</template></p>
                <p v-if="detail.status === 'won'" class="text-muted-foreground">Nhập học {{ formatDateTime(detail.enrolled_at) }}</p>
            </section>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
                <div><dt class="text-muted-foreground">Điện thoại</dt><dd data-test="phone">{{ detail.phone || '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Email</dt><dd>{{ detail.email || '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Trường THPT</dt><dd>{{ detail.high_school_name || '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Tỉnh/Thành phố</dt><dd>{{ detail.province_name || '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Nguồn</dt><dd>{{ detail.source_name || '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Chiến dịch</dt><dd>{{ detail.campaign_name || '—' }}</dd></div>
                <div>
                    <dt class="text-muted-foreground">Người phụ trách</dt>
                    <dd>
                        <span v-if="detail.in_pool" class="inline-flex items-center gap-1"><Inbox class="size-3.5" />Kho chung</span>
                        <template v-else>{{ detail.owner_name }}<span v-if="detail.team_name" class="text-muted-foreground"> · {{ detail.team_name }}</span></template>
                    </dd>
                </div>
                <div><dt class="text-muted-foreground">Mức độ quan tâm</dt><dd><TemperatureBadge :value="detail.temperature" /><span v-if="!detail.temperature">—</span></dd></div>
                <div><dt class="text-muted-foreground">Liên hệ lần đầu</dt><dd>{{ formatDateTime(detail.first_contacted_at) }}</dd></div>
                <div><dt class="text-muted-foreground">Hoạt động gần nhất</dt><dd>{{ formatDateTime(detail.last_activity_at) }}</dd></div>
            </dl>

            <section class="grid gap-2" data-test="assignment">
                <h3 class="font-medium">Phụ trách</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="detail.can.claim" :disabled="processing" data-test="claim" @click="claim">Nhận hồ sơ</Button>
                    <template v-if="detail.can.assign">
                        <div class="min-w-48 flex-1">
                            <EntityPicker v-model="newOwner" entity="users" placeholder="Giao cho…" />
                        </div>
                        <Button variant="outline" :disabled="newOwner === null || processing" data-test="assign" @click="assign(newOwner)">Giao</Button>
                        <Button v-if="!detail.in_pool" variant="outline" :disabled="processing" data-test="to-pool" @click="assign(null)">Trả về kho chung</Button>
                    </template>
                </div>
            </section>

            <section v-if="detail.can.update" class="grid gap-3" data-test="edit-fields">
                <h3 class="font-medium">Cập nhật</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cd-temp">Mức độ quan tâm</Label>
                        <select id="cd-temp" v-model="form.temperature" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.temperatures" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cd-next">Hành động tiếp theo</Label>
                        <Input id="cd-next" v-model="form.next_action_at" type="datetime-local" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cd-source">Nguồn</Label>
                        <select id="cd-source" v-model="form.source_id" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.sources" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cd-major">Ngành quan tâm chính</Label>
                        <EntityPicker
                            id="cd-major"
                            v-model="form.primary_major_id"
                            entity="majors"
                            placeholder="Chọn ngành"
                            :initial-items="detail.primary_major_id && detail.major_name ? [{ id: detail.primary_major_id, label: detail.major_name, description: null }] : []"
                        />
                    </div>
                </div>
                <div><Button :disabled="processing" data-test="save-case" @click="save">Lưu thay đổi</Button></div>
            </section>

            <CustomFieldsCard :panel="detail.custom_fields" :can-edit="detail.can.update" @saved="load" />

            <section v-if="detail.history.length" class="grid gap-2" data-test="history">
                <h3 class="font-medium">Lịch sử giai đoạn</h3>
                <ul class="grid gap-1 text-muted-foreground">
                    <li v-for="(h, i) in detail.history" :key="i">
                        {{ formatDateTime(h.at) }} · {{ h.from ?? 'Mới tạo' }} → <span class="text-foreground">{{ h.to }}</span>
                        <template v-if="h.by"> · {{ h.by }}</template>
                        <template v-if="h.hours !== null"> · {{ h.hours }} giờ ở giai đoạn trước</template>
                    </li>
                </ul>
            </section>
        </div>
    </RecordDrawer>

    <LostReasonDialog v-model:open="lostOpen" :reasons="options.lostReasons" :subject="detail?.applicant_name" @confirm="confirmLost" />
</template>
