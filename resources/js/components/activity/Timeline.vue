<script setup lang="ts">
import { CalendarClock, Check, MessageSquareText, Phone, PhoneIncoming, PhoneOutgoing, StickyNote, Users } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import type { Component } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';
import { selectClass, textareaClass } from './types';
import type { TaskRow, TimelineEntry, TimelineSubjectType } from './types';

/**
 * Timeline of a record: add a note / call (with its result) / message / meeting, see the history (including the
 * entries the system writes: stage and owner changes) and the open tasks of the record.
 *
 *   <Timeline subject-type="applicant" :subject="applicant.uuid" />
 */
const props = defineProps<{
    subjectType: TimelineSubjectType;
    subject: string | number;
}>();

const emit = defineEmits<{ changed: [] }>();

const entries = ref<TimelineEntry[]>([]);
const tasks = ref<TaskRow[]>([]);
const callResults = ref<{ id: number; name: string }[]>([]);
const canWrite = ref(false);
const hasMore = ref(false);
const loading = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const failed = ref(false);

const kinds: { value: string; label: string }[] = [
    { value: 'note', label: 'Ghi chú' },
    { value: 'call', label: 'Cuộc gọi' },
    { value: 'zalo', label: 'Zalo' },
    { value: 'sms', label: 'SMS' },
    { value: 'email', label: 'Email' },
    { value: 'meeting', label: 'Gặp mặt' },
];

const blank = () => ({ type: 'note', body: '', direction: 'out', call_result_id: '' as number | '', duration_min: '' as number | '', callback_at: '' });
const form = ref(blank());
const isCall = computed(() => form.value.type === 'call');

const icons: Record<string, Component> = {
    note: StickyNote,
    call: Phone,
    meeting: Users,
    system: CalendarClock,
};

function iconFor(entry: TimelineEntry): Component {
    if (entry.type === 'call') {
        return entry.direction === 'in' ? PhoneIncoming : entry.direction === 'out' ? PhoneOutgoing : Phone;
    }

    return icons[entry.type] ?? MessageSquareText;
}

const system = (entry: TimelineEntry) => ['system', 'stage_changed', 'owner_changed'].includes(entry.type);

function query(before?: number): string {
    const params = new URLSearchParams({ subject_type: props.subjectType, subject: String(props.subject) });

    if (before) {
        params.set('before', String(before));
    }

    if (callResults.value.length === 0) {
        params.set('options', '1');
    }

    return params.toString();
}

async function load(more = false): Promise<void> {
    loading.value = true;
    failed.value = false;

    try {
        const data = await requestJson<{
            entries: TimelineEntry[];
            has_more: boolean;
            tasks: TaskRow[];
            call_results: { id: number; name: string }[];
            can: { write: boolean };
        }>('GET', `/timeline?${query(more ? entries.value.at(-1)?.id : undefined)}`);

        entries.value = more ? [...entries.value, ...data.entries] : data.entries;
        hasMore.value = data.has_more;

        if (!more) {
            tasks.value = data.tasks;
        }

        if (data.call_results.length > 0) {
            callResults.value = data.call_results;
        }

        canWrite.value = data.can.write;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};

    const f = form.value;
    const payload: Record<string, unknown> = {
        subject_type: props.subjectType,
        subject: String(props.subject),
        type: f.type,
        body: f.body || null,
    };

    if (isCall.value) {
        payload.direction = f.direction;
        payload.call_result_id = f.call_result_id || null;
        payload.duration_sec = f.duration_min === '' ? null : Number(f.duration_min) * 60;
        payload.callback_at = f.callback_at || null;
    }

    try {
        await requestJson('POST', '/timeline', payload);
        form.value = { ...blank(), type: f.type };
        await load();
        emit('changed');
    } catch (e) {
        errors.value = (e as HttpError).errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function complete(task: TaskRow): Promise<void> {
    try {
        await requestJson('POST', `/tasks/${task.id}/status`, { status: 'done' });
        await load();
        emit('changed');
    } catch {
        failed.value = true;
    }
}

function duration(seconds: number | null): string {
    if (!seconds) {
        return '';
    }

    return seconds >= 60 ? `${Math.round(seconds / 60)} phút` : `${seconds} giây`;
}

watch(() => [props.subjectType, props.subject], () => load());
onMounted(() => load());

defineExpose({ reload: () => load() });
</script>

<template>
    <section class="grid gap-4" data-test="timeline">
        <form v-if="canWrite" class="grid gap-3 rounded-md border p-3" data-test="timeline-form" @submit.prevent="submit">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="k in kinds"
                    :key="k.value"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs"
                    :class="form.type === k.value ? 'border-primary bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                    :data-test="`kind-${k.value}`"
                    @click="form.type = k.value"
                >
                    {{ k.label }}
                </button>
            </div>

            <template v-if="isCall">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="tl-direction">Chiều gọi</Label>
                        <select id="tl-direction" v-model="form.direction" :class="selectClass">
                            <option value="out">Gọi đi</option>
                            <option value="in">Gọi đến</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tl-result">Kết quả</Label>
                        <select id="tl-result" v-model="form.call_result_id" :class="selectClass" data-test="call-result">
                            <option value="">— Chọn —</option>
                            <option v-for="r in callResults" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <InputError :message="errors.call_result_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tl-duration">Thời lượng (phút)</Label>
                        <Input id="tl-duration" v-model="form.duration_min" type="number" min="0" />
                    </div>
                </div>
                <div class="grid gap-1.5 sm:max-w-xs">
                    <Label for="tl-callback">Hẹn gọi lại lúc</Label>
                    <Input id="tl-callback" v-model="form.callback_at" type="datetime-local" data-test="callback-at" />
                    <InputError :message="errors.callback_at" />
                </div>
            </template>

            <div class="grid gap-1.5">
                <Label for="tl-body" class="sr-only">Nội dung</Label>
                <textarea id="tl-body" v-model="form.body" rows="3" :class="textareaClass" :placeholder="isCall ? 'Nội dung cuộc gọi (không bắt buộc)…' : 'Nhập nội dung…'" data-test="timeline-body" />
                <InputError :message="errors.body" />
                <InputError :message="errors.type" />
                <InputError :message="errors.subject" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" size="sm" :disabled="saving" data-test="timeline-submit">
                    <Spinner v-if="saving" />
                    Lưu
                </Button>
            </div>
        </form>

        <div v-if="tasks.length > 0" class="grid gap-1.5" data-test="timeline-tasks">
            <h3 class="text-sm font-medium">Việc cần làm</h3>
            <ul class="grid gap-1">
                <li v-for="t in tasks" :key="t.id" class="flex items-center justify-between gap-2 rounded-md border px-3 py-1.5 text-sm" data-test="timeline-task">
                    <span>
                        {{ t.title }}
                        <span class="ml-1 text-xs" :class="t.overdue ? 'text-destructive' : 'text-muted-foreground'">{{ t.due_at ? formatDateTime(t.due_at) : 'Không hạn' }}</span>
                    </span>
                    <Button v-if="canWrite" variant="ghost" size="icon" class="size-7" aria-label="Hoàn thành" data-test="complete-task" @click="complete(t)">
                        <Check class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>

        <p v-if="failed" class="text-sm text-destructive" data-test="timeline-error">Không tải được timeline. Thử lại sau.</p>

        <ol class="grid gap-3" data-test="timeline-list">
            <li v-for="e in entries" :key="e.id" class="flex gap-3" :class="{ 'opacity-80': system(e) }" data-test="timeline-entry">
                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground">
                    <component :is="iconFor(e)" class="size-3.5" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
                        <span class="font-medium">{{ e.title || e.type_label }}</span>
                        <span v-if="e.result && !e.title?.includes(e.result)" class="text-muted-foreground">· {{ e.result }}</span>
                        <span v-if="e.duration_sec" class="text-xs text-muted-foreground">{{ duration(e.duration_sec) }}</span>
                    </div>
                    <p v-if="e.body" class="whitespace-pre-line text-sm text-muted-foreground">{{ e.body }}</p>
                    <div class="text-xs text-muted-foreground">
                        {{ formatDateTime(e.occurred_at) }}<template v-if="e.user_name"> · {{ e.user_name }}</template>
                    </div>
                </div>
            </li>
        </ol>

        <p v-if="!loading && !failed && entries.length === 0" class="text-sm text-muted-foreground" data-test="timeline-empty">Chưa có hoạt động nào.</p>

        <div v-if="hasMore" class="flex justify-center">
            <Button variant="outline" size="sm" :disabled="loading" data-test="timeline-more" @click="load(true)">Xem thêm</Button>
        </div>
    </section>
</template>
