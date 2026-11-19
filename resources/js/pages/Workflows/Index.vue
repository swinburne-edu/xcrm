<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Opt = { value: string | number; label: string };
type Field = { key: string; label: string; type: string; source?: string };
type Condition = { field: string; operator: string; value: string | number | null };
type Action = { type: string; [key: string]: string | number };
type Run = { id: number; status: string; status_label: string; subject_type: string; subject_id: number; log: { action: string; ok: boolean; message: string }[]; ran_at: string };
type Flow = {
    id: number;
    name: string;
    object: string;
    object_label: string;
    trigger: { type: string; field?: string };
    trigger_label: string;
    conditions: Condition[];
    actions: Action[];
    is_active: boolean;
    runs: Run[];
};

const props = defineProps<{
    workflows: Flow[];
    options: {
        objects: Opt[];
        triggers: Opt[];
        operators: Opt[];
        action_types: { value: string; label: string; objects: string[] }[];
        fields: Record<string, Field[]>;
        settable: Opt[];
        lists: Record<string, Opt[]>;
        templates: Opt[];
    };
    can: { manage: boolean };
}>();

const entities: Record<string, string> = { users: 'users', provinces: 'provinces', high_schools: 'high_schools' };
const open = ref(false);
const editing = ref<Flow | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const deleting = ref<Flow | null>(null);
const expanded = ref<number | null>(null);
const form = ref(blank());

function blank() {
    return {
        name: '',
        object: 'admission_case',
        trigger: { type: 'created', field: '' } as { type: string; field?: string },
        conditions: [] as Condition[],
        actions: [{ type: 'create_task', title: '', due_in_hours: 24, assignee: 'owner' }] as Action[],
        is_active: true,
    };
}

const fields = computed(() => props.options.fields[form.value.object] ?? []);
const triggers = computed(() => props.options.triggers.filter((t) => form.value.object === 'admission_case' || t.value !== 'stage_changed'));
const actionTypes = computed(() => props.options.action_types.filter((a) => a.objects.includes(form.value.object)));
const fieldOf = (key: string) => fields.value.find((f) => f.key === key);
const tones: Record<string, BadgeTone> = { success: 'green', partial: 'amber', failed: 'red', skipped: 'gray' };

function start(flow: Flow | null): void {
    editing.value = flow;
    errors.value = {};
    form.value = flow
        ? { name: flow.name, object: flow.object, trigger: { type: flow.trigger.type, field: flow.trigger.field ?? '' }, conditions: flow.conditions.map((c) => ({ ...c })), actions: flow.actions.map((a) => ({ ...a })), is_active: flow.is_active }
        : blank();
    open.value = true;
}

function changeObject(): void {
    form.value.conditions = [];
    form.value.actions = form.value.actions.filter((a) => actionTypes.value.some((t) => t.value === a.type));

    if (!triggers.value.some((t) => t.value === form.value.trigger.type)) {
        form.value.trigger = { type: 'created', field: '' };
    }
}

function addCondition(): void {
    form.value.conditions.push({ field: fields.value[0]?.key ?? '', operator: 'eq', value: '' });
}

function addAction(): void {
    form.value.actions.push(defaults(actionTypes.value[0]?.value ?? 'create_task'));
}

function defaults(type: string): Action {
    switch (type) {
        case 'assign':
            return { type, user_id: "" };
        case 'send_message':
            return { type, template_id: props.options.templates[0]?.value ?? "" };
        case 'update_field':
            return { type, field: 'temperature', value: '' };
        case 'webhook':
            return { type, url: '' };
        default:
            return { type: 'create_task', title: '', due_in_hours: 24, assignee: 'owner' };
    }
}

function retype(index: number): void {
    form.value.actions[index] = defaults(form.value.actions[index].type);
}

/** Choice list for a condition value: a select when the field has a fixed list. */
function listFor(source?: string): Opt[] | null {
    return source && props.options.lists[source] ? props.options.lists[source] : null;
}

function pickId(target: Record<string, unknown>, key: string, v: unknown): void {
    target[key] = Array.isArray(v) ? (v[0] ?? null) : v;
}

function submit(): void {
    const data = {
        ...form.value,
        trigger: form.value.trigger.type === 'updated' ? form.value.trigger : { type: form.value.trigger.type },
        conditions: form.value.conditions.map((c) => ({ ...c, value: ['empty', 'not_empty'].includes(c.operator) ? null : c.value })),
    };

    router.visit(editing.value ? `/settings/workflows/${editing.value.id}` : '/settings/workflows', {
        method: editing.value ? 'put' : 'post',
        data: data as never,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}

function toggle(flow: Flow): void {
    router.patch(`/settings/workflows/${flow.id}/active`, { is_active: !flow.is_active }, { preserveScroll: true });
}

function confirmDelete(): void {
    if (deleting.value) {
        router.delete(`/settings/workflows/${deleting.value.id}`, { preserveScroll: true, onFinish: () => (deleting.value = null) });
    }
}

function describe(flow: Flow): string {
    const label = (type: string) => props.options.action_types.find((a) => a.value === type)?.label ?? type;

    return flow.actions.map((a) => label(a.type)).join(' → ');
}

const errorOf = (key: string) => errors.value[key];
</script>

<template>
    <Head title="Workflow tự động" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Workflow tự động</h1>
                <p class="text-sm text-muted-foreground">Khi có sự kiện, nếu thoả điều kiện thì tự làm các hành động: giao người, tạo việc, gửi tin, cập nhật trường, gọi webhook.</p>
            </div>
            <Button v-if="can.manage" data-test="create-workflow" @click="start(null)"><Plus class="size-4" />Thêm workflow</Button>
        </div>

        <p v-if="!workflows.length" class="text-sm text-muted-foreground">Chưa có workflow nào.</p>
        <ul v-else class="grid gap-3" data-test="workflows">
            <li v-for="w in workflows" :key="w.id" class="grid gap-2 rounded-md border p-3" data-test="workflow-row">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ w.name }}</span>
                    <ToneBadge :tone="w.is_active ? 'green' : 'gray'">{{ w.is_active ? 'Đang bật' : 'Đang tắt' }}</ToneBadge>
                    <span class="ml-auto flex items-center gap-1">
                        <Button v-if="can.manage" variant="outline" size="sm" data-test="toggle-workflow" @click="toggle(w)">{{ w.is_active ? 'Tắt' : 'Bật' }}</Button>
                        <Button v-if="can.manage" variant="ghost" size="icon" class="size-8" aria-label="Sửa workflow" @click="start(w)"><Pencil class="size-4" /></Button>
                        <Button v-if="can.manage" variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá workflow" @click="deleting = w"><Trash2 class="size-4" /></Button>
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ w.object_label }} · {{ w.trigger_label }}<template v-if="w.trigger.field"> ({{ options.fields[w.object]?.find((f) => f.key === w.trigger.field)?.label }})</template>
                    <template v-if="w.conditions.length"> · {{ w.conditions.length }} điều kiện</template>
                </p>
                <p class="text-sm">{{ describe(w) }}</p>
                <button type="button" class="flex w-fit items-center gap-1 text-xs text-muted-foreground hover:underline" @click="expanded = expanded === w.id ? null : w.id">
                    <ChevronDown class="size-3.5" />Lần chạy gần đây ({{ w.runs.length }})
                </button>
                <ul v-if="expanded === w.id" class="grid gap-2 text-sm" data-test="runs">
                    <li v-if="!w.runs.length" class="text-muted-foreground">Chưa chạy lần nào.</li>
                    <li v-for="r in w.runs" :key="r.id" class="rounded border p-2">
                        <div class="flex items-center gap-2"><ToneBadge :tone="tones[r.status] ?? 'gray'">{{ r.status_label }}</ToneBadge><span class="text-xs text-muted-foreground">{{ r.ran_at }} · {{ r.subject_type }} #{{ r.subject_id }}</span></div>
                        <ul class="mt-1 grid gap-0.5 text-xs">
                            <li v-for="(l, i) in r.log" :key="i" :class="l.ok ? '' : 'text-destructive'">{{ l.ok ? '✓' : '✗' }} {{ l.message }}</li>
                        </ul>
                    </li>
                </ul>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl" data-test="workflow-form">
            <form class="grid gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Sửa workflow' : 'Thêm workflow' }}</DialogTitle>
                    <DialogDescription>Khi (sự kiện) · Nếu (điều kiện, tuỳ chọn) · Thì (hành động).</DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="wf-name">Tên <span class="text-destructive">*</span></Label>
                        <Input id="wf-name" v-model="form.name" autocomplete="off" data-test="wf-name" />
                        <InputError :message="errorOf('name')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="wf-object">Áp dụng cho</Label>
                        <select id="wf-object" v-model="form.object" :class="selectClass" :disabled="!!editing" data-test="wf-object" @change="changeObject">
                            <option v-for="o in options.objects" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                </div>

                <section class="grid gap-2">
                    <h3 class="text-sm font-semibold">Khi</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select v-model="form.trigger.type" :class="selectClass" data-test="wf-trigger">
                            <option v-for="t in triggers" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <select v-if="form.trigger.type === 'updated'" v-model="form.trigger.field" :class="selectClass" data-test="wf-trigger-field">
                            <option value="">Chọn trường…</option>
                            <option v-for="f in fields" :key="f.key" :value="f.key">{{ f.label }}</option>
                        </select>
                    </div>
                    <InputError :message="errorOf('trigger.field') ?? errorOf('trigger.type')" />
                    <p v-if="form.trigger.type === 'schedule'" class="text-xs text-muted-foreground">Chạy mỗi sáng 07:00 trên các bản ghi thoả điều kiện, mỗi bản ghi chỉ một lần.</p>
                </section>

                <section class="grid gap-2">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Nếu (tất cả điều kiện đều đúng)</h3><Button type="button" variant="outline" size="sm" data-test="add-condition" @click="addCondition"><Plus class="size-3.5" />Điều kiện</Button></div>
                    <div v-for="(c, i) in form.conditions" :key="i" class="grid gap-1" data-test="condition-row">
                        <div class="grid grid-cols-[1fr_8rem_1fr_auto] items-start gap-2">
                            <select v-model="c.field" :class="selectClass" @change="c.value = ''">
                                <option v-for="f in fields" :key="f.key" :value="f.key">{{ f.label }}</option>
                            </select>
                            <select v-model="c.operator" :class="selectClass">
                                <option v-for="o in options.operators" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <template v-if="!['empty', 'not_empty'].includes(c.operator)">
                                <EntityPicker v-if="fieldOf(c.field)?.source && entities[fieldOf(c.field)!.source!]" :model-value="c.value ? Number(c.value) : null" :entity="entities[fieldOf(c.field)!.source!]" @update:model-value="(v) => pickId(c, 'value', v)" />
                                <select v-else-if="listFor(fieldOf(c.field)?.source)" v-model="c.value" :class="selectClass"><option value="">Chọn…</option><option v-for="o in listFor(fieldOf(c.field)?.source)" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                                <select v-else-if="fieldOf(c.field)?.type === 'bool'" v-model="c.value" :class="selectClass"><option value="1">Có</option><option value="0">Không</option></select>
                                <Input v-else v-model="c.value as string" :type="fieldOf(c.field)?.type === 'number' ? 'number' : 'text'" />
                            </template>
                            <span v-else></span>
                            <Button type="button" variant="ghost" size="icon" class="size-9" aria-label="Bỏ điều kiện" @click="form.conditions.splice(i, 1)"><X class="size-4" /></Button>
                        </div>
                        <InputError :message="errorOf(`conditions.${i}.field`) ?? errorOf(`conditions.${i}.value`)" />
                    </div>
                </section>

                <section class="grid gap-2">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Thì</h3><Button type="button" variant="outline" size="sm" data-test="add-action" @click="addAction"><Plus class="size-3.5" />Hành động</Button></div>
                    <div v-for="(a, i) in form.actions" :key="i" class="grid gap-2 rounded-md border p-3" data-test="action-row">
                        <div class="flex items-center gap-2">
                            <select v-model="a.type" :class="selectClass" data-test="action-type" @change="retype(i)">
                                <option v-for="t in actionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <Button type="button" variant="ghost" size="icon" class="size-9 shrink-0" aria-label="Bỏ hành động" @click="form.actions.splice(i, 1)"><X class="size-4" /></Button>
                        </div>
                        <InputError :message="errorOf(`actions.${i}.type`)" />

                        <template v-if="a.type === 'assign'">
                            <EntityPicker :model-value="a.user_id ? Number(a.user_id) : null" entity="users" placeholder="Kho chung (không giao ai)" @update:model-value="(v) => pickId(a, 'user_id', v)" />
                        </template>
                        <template v-else-if="a.type === 'create_task'">
                            <Input v-model="a.title" placeholder="Tên việc, vd. Gọi lần đầu" data-test="action-title" />
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="grid gap-1"><Label class="text-xs">Hạn sau (giờ)</Label><Input v-model="a.due_in_hours" type="number" min="0" max="720" /></div>
                                <div class="grid gap-1"><Label class="text-xs">Giao cho</Label>
                                    <select v-model="a.assignee" :class="selectClass"><option value="owner">Người phụ trách hồ sơ</option></select>
                                </div>
                            </div>
                        </template>
                        <template v-else-if="a.type === 'send_message'">
                            <select v-model="a.template_id" :class="selectClass"><option v-for="t in options.templates" :key="t.value" :value="t.value">{{ t.label }}</option></select>
                            <p class="text-xs text-muted-foreground">Chỉ gửi nếu thí sinh đã đồng ý nhận tin.</p>
                        </template>
                        <template v-else-if="a.type === 'update_field'">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <select v-model="a.field" :class="selectClass" @change="a.value = ''"><option v-for="o in options.settable" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                                <select v-if="a.field === 'temperature'" v-model="a.value" :class="selectClass"><option value="">Xoá giá trị</option><option v-for="o in options.lists.temperatures" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                                <select v-else v-model="a.value" :class="selectClass"><option value="">Xoá giá trị</option><option v-for="o in options.lists.majors" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                            </div>
                        </template>
                        <template v-else-if="a.type === 'webhook'">
                            <Input v-model="a.url" placeholder="https://…" data-test="action-url" />
                            <p class="text-xs text-muted-foreground">Gửi POST JSON (tên workflow, sự kiện, loại và mã bản ghi). Chỉ nhận địa chỉ https công khai.</p>
                        </template>
                    </div>
                    <InputError :message="errorOf('actions')" />
                </section>

                <Label class="flex items-center gap-2 font-normal"><Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" />Bật ngay</Label>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="save-workflow">Lưu</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader><DialogTitle>Xoá workflow {{ deleting?.name }}?</DialogTitle><DialogDescription>Lịch sử chạy cũng bị xoá.</DialogDescription></DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
