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
import type { FormulaOptions, MethodRow, ScoreFormula } from './types';

type MethodOption = { value: string; label: string; used: boolean };

const props = defineProps<{
    cycleId: number;
    /** null = create */
    record: MethodRow | null;
    methodOptions: MethodOption[];
    formulaOptions: FormulaOptions;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const mode = ref<'form' | 'json'>('form');
const jsonText = ref('');
const conditionsText = ref('');
const jsonError = ref<string | null>(null);

const form = ref({
    method_id: '' as string,
    display_name: '',
    is_direct: false,
    required_documents: [] as string[],
});

// Structured editor state. Keys the editor does not edit (certificate_conversion) are kept in `extra`.
const formula = ref({
    source: 'thpt_exam',
    use_combination: false,
    terms: [] as string[],
    aggregate: '',
    subject_scale: '' as number | string,
    scale_to: '' as number | string,
    priority_apply: false,
    taper_from: '' as number | string,
    encourage_bonus_max: '' as number | string,
    min_total: '' as number | string,
});
let extra: Record<string, unknown> = {};

function loadFormula(source: ScoreFormula | null): void {
    const f = source ?? {};
    formula.value = {
        source: f.source ?? 'thpt_exam',
        use_combination: f.use_combination ?? false,
        terms: [...(f.terms ?? [])],
        aggregate: f.aggregate ?? '',
        subject_scale: f.subject_scale ?? '',
        scale_to: f.scale_to ?? '',
        priority_apply: f.priority?.apply ?? false,
        taper_from: f.priority?.taper_from ?? '',
        encourage_bonus_max: f.encourage_bonus_max ?? '',
        min_total: f.min_total ?? '',
    };
    extra =
        f.certificate_conversion !== undefined
            ? { certificate_conversion: f.certificate_conversion }
            : {};
}

function num(value: number | string): number | undefined {
    return value === '' || value === null ? undefined : Number(value);
}

function buildFormula(): ScoreFormula {
    const f = formula.value;
    const out: Record<string, unknown> = { source: f.source };

    if (f.use_combination) {
        out.use_combination = true;
    }

    if (f.terms.length > 0) {
        out.terms = f.terms;
    }

    if (f.aggregate) {
        out.aggregate = f.aggregate;
    }

    for (const key of ['subject_scale', 'scale_to'] as const) {
        const v = num(f[key]);

        if (v !== undefined) {
            out[key] = v;
        }
    }

    if (f.priority_apply) {
        const taper = num(f.taper_from);
        out.priority =
            taper !== undefined
                ? { apply: true, taper_from: taper }
                : { apply: true };
    }

    for (const key of ['encourage_bonus_max', 'min_total'] as const) {
        const v = num(f[key]);

        if (v !== undefined) {
            out[key] = v;
        }
    }

    return { ...out, ...extra } as ScoreFormula;
}

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    jsonError.value = null;
    mode.value = 'form';
    const r = props.record;
    form.value = {
        method_id: r ? String(r.method_id) : '',
        display_name: r?.display_name ?? '',
        is_direct: r?.is_direct ?? false,
        required_documents: [...(r?.required_documents ?? [])],
    };
    loadFormula(r?.score_formula ?? null);
    jsonText.value = '';
    conditionsText.value = r?.conditions
        ? JSON.stringify(r.conditions, null, 2)
        : '';
});

/** Switch editor mode; the JSON mode replaces the whole formula object. */
function switchMode(next: 'form' | 'json'): void {
    jsonError.value = null;

    if (next === 'json' && mode.value === 'form') {
        jsonText.value = JSON.stringify(buildFormula(), null, 2);
    }

    if (next === 'form' && mode.value === 'json') {
        if (jsonText.value.trim() === '') {
            loadFormula(null);
        } else {
            try {
                const parsed = JSON.parse(jsonText.value);

                if (
                    parsed === null ||
                    typeof parsed !== 'object' ||
                    Array.isArray(parsed)
                ) {
                    throw new Error('not-object');
                }

                loadFormula(parsed as ScoreFormula);
            } catch {
                jsonError.value =
                    'JSON không hợp lệ — sửa lại trước khi quay về dạng form.';

                return;
            }
        }
    }

    mode.value = next;
}

function toggle(list: string[], value: string): void {
    const i = list.indexOf(value);

    if (i === -1) {
        list.push(value);
    } else {
        list.splice(i, 1);
    }
}

function submit(): void {
    const data = {
        method_id: form.value.method_id,
        display_name: form.value.display_name || null,
        is_direct: form.value.is_direct,
        required_documents: form.value.required_documents,
        score_formula:
            mode.value === 'json' ? jsonText.value : buildFormula(),
        conditions: conditionsText.value.trim() || null,
    };
    const base = `/settings/cycles/${props.cycleId}/methods`;

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

const formulaErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([k]) => k === 'score_formula' || k.startsWith('score_formula'))
        .map(([, v]) => v),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            data-test="method-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa phương thức' : 'Thêm phương thức'
                    }}</DialogTitle>
                    <DialogDescription>
                        Mỗi mùa có tối đa
                        {{ formulaOptions.maxMethods }} phương thức xét tuyển
                        (không tính xét tuyển thẳng).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="m-method"
                            >Phương thức
                            <span class="text-destructive">*</span></Label
                        >
                        <select
                            id="m-method"
                            v-model="form.method_id"
                            :class="selectClass"
                        >
                            <option value="" disabled>Chọn phương thức</option>
                            <option
                                v-for="o in methodOptions"
                                :key="o.value"
                                :value="o.value"
                                :disabled="
                                    o.used &&
                                    o.value !== String(record?.method_id)
                                "
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.method_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="m-display">Tên hiển thị</Label>
                        <Input
                            id="m-display"
                            v-model="form.display_name"
                            placeholder="Để trống = dùng tên phương thức"
                        />
                        <InputError :message="errors.display_name" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_direct"
                        type="checkbox"
                        class="size-4"
                        data-test="is-direct"
                    />
                    Xét tuyển thẳng (không tính vào giới hạn 5 phương thức,
                    không bắt buộc công thức điểm)
                </label>
                <InputError :message="errors.is_direct" />

                <fieldset class="grid gap-3 rounded-md border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <legend class="text-sm font-medium">Công thức điểm</legend>
                        <div class="flex gap-1 text-xs">
                            <Button
                                type="button"
                                size="sm"
                                :variant="mode === 'form' ? 'secondary' : 'ghost'"
                                data-test="mode-form"
                                @click="switchMode('form')"
                                >Biểu mẫu</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                :variant="mode === 'json' ? 'secondary' : 'ghost'"
                                data-test="mode-json"
                                @click="switchMode('json')"
                                >JSON nâng cao</Button
                            >
                        </div>
                    </div>

                    <template v-if="mode === 'form'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="f-source">Nguồn điểm</Label>
                                <select
                                    id="f-source"
                                    v-model="formula.source"
                                    :class="selectClass"
                                >
                                    <option
                                        v-for="o in formulaOptions.sources"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="f-agg">Cách gộp điểm các kỳ</Label>
                                <select
                                    id="f-agg"
                                    v-model="formula.aggregate"
                                    :class="selectClass"
                                >
                                    <option value="">—</option>
                                    <option
                                        v-for="o in formulaOptions.aggregates"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="formula.use_combination"
                                type="checkbox"
                                class="size-4"
                            />
                            Tính theo 3 môn của tổ hợp xét tuyển
                        </label>

                        <div class="grid gap-1.5">
                            <Label>Kỳ học (học bạ)</Label>
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                <label
                                    v-for="t in formulaOptions.terms"
                                    :key="t"
                                    class="flex items-center gap-1.5 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="formula.terms.includes(t)"
                                        @change="toggle(formula.terms, t)"
                                    />
                                    {{ t }}
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="grid gap-1.5">
                                <Label for="f-ss">Thang môn</Label>
                                <Input
                                    id="f-ss"
                                    v-model="formula.subject_scale"
                                    type="number"
                                    step="any"
                                    min="0"
                                    placeholder="10"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="f-st">Thang tổng</Label>
                                <Input
                                    id="f-st"
                                    v-model="formula.scale_to"
                                    type="number"
                                    step="any"
                                    min="0"
                                    placeholder="30"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="f-bonus">Điểm khuyến khích tối đa</Label>
                                <Input
                                    id="f-bonus"
                                    v-model="formula.encourage_bonus_max"
                                    type="number"
                                    step="any"
                                    min="0"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="f-min">Điểm sàn</Label>
                                <Input
                                    id="f-min"
                                    v-model="formula.min_total"
                                    type="number"
                                    step="any"
                                    min="0"
                                />
                            </div>
                        </div>

                        <div class="grid items-end gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="formula.priority_apply"
                                    type="checkbox"
                                    class="size-4"
                                />
                                Cộng điểm ưu tiên khu vực/đối tượng
                            </label>
                            <div
                                v-if="formula.priority_apply"
                                class="grid gap-1.5"
                            >
                                <Label for="f-taper"
                                    >Giảm dần ưu tiên từ điểm</Label
                                >
                                <Input
                                    id="f-taper"
                                    v-model="formula.taper_from"
                                    type="number"
                                    step="any"
                                    min="0"
                                    placeholder="22.5"
                                />
                            </div>
                        </div>
                        <p
                            v-if="Object.keys(extra).length > 0"
                            class="text-xs text-muted-foreground"
                        >
                            Công thức có bảng quy đổi chứng chỉ — được giữ
                            nguyên, chỉnh sửa ở chế độ JSON nâng cao.
                        </p>
                    </template>

                    <template v-else>
                        <textarea
                            v-model="jsonText"
                            rows="12"
                            spellcheck="false"
                            class="w-full rounded-md border border-input bg-transparent p-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            data-test="formula-json"
                        />
                        <p class="text-xs text-muted-foreground">
                            Thay toàn bộ công thức bằng JSON. Chỉ nhận các
                            trường: source, use_combination, terms, aggregate,
                            subject_scale, scale_to, certificate_conversion,
                            priority, encourage_bonus_max, min_total.
                        </p>
                    </template>

                    <InputError :message="jsonError ?? undefined" />
                    <InputError
                        v-for="(message, i) in formulaErrors"
                        :key="i"
                        :message="message"
                    />
                </fieldset>

                <div class="grid gap-1.5">
                    <Label>Giấy tờ bắt buộc</Label>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <label
                            v-for="d in formulaOptions.documents"
                            :key="d.value"
                            class="flex items-center gap-1.5 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4"
                                :checked="
                                    form.required_documents.includes(d.value)
                                "
                                @change="
                                    toggle(form.required_documents, d.value)
                                "
                            />
                            {{ d.label }}
                        </label>
                    </div>
                    <InputError :message="errors.required_documents" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="m-conditions">Điều kiện xét tuyển (JSON, tuỳ chọn)</Label>
                    <textarea
                        id="m-conditions"
                        v-model="conditionsText"
                        rows="3"
                        spellcheck="false"
                        class="w-full rounded-md border border-input bg-transparent p-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.conditions" />
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
