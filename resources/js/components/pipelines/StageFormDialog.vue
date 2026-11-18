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
import type { Option } from './PipelineFormDialog.vue';

export type StageRow = {
    id: number;
    code: string;
    name: string;
    color: string | null;
    sort_order: number;
    type: string;
    type_label: string;
    probability: number;
    sla_hours: number | null;
    required_fields: string[];
    cases_count: number;
};

const props = defineProps<{
    pipelineId: number;
    /** null = create */
    record: StageRow | null;
    types: Option[];
    colors: string[];
    requirable: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    code: '',
    name: '',
    color: '',
    sort_order: '' as number | string,
    type: 'open',
    probability: 0 as number | string,
    sla_hours: '' as number | string,
    required_fields: [] as string[],
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        code: r?.code ?? '',
        name: r?.name ?? '',
        color: r?.color ?? '',
        sort_order: r?.sort_order ?? '',
        type: r?.type ?? 'open',
        probability: r?.probability ?? 0,
        sla_hours: r?.sla_hours ?? '',
        required_fields: [...(r?.required_fields ?? [])],
    };
});

function toggle(field: string): void {
    const list = form.value.required_fields;
    const i = list.indexOf(field);

    if (i === -1) {
        list.push(field);
    } else {
        list.splice(i, 1);
    }
}

function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(([k]) => k === key || k.startsWith(`${key}.`))?.[1];
}

function submit(): void {
    const data = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]));
    const base = `/settings/pipelines/${props.pipelineId}/stages`;

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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg" data-test="stage-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa giai đoạn' : 'Thêm giai đoạn' }}</DialogTitle>
                    <DialogDescription>
                        Hồ sơ mới vào giai đoạn "đang xử lý" đầu tiên. Trạng thái hồ sơ luôn theo loại của giai đoạn.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="st-code">Mã <span class="text-destructive">*</span></Label>
                        <Input id="st-code" v-model="form.code" maxlength="50" placeholder="counseling" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-name">Tên <span class="text-destructive">*</span></Label>
                        <Input id="st-name" v-model="form.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-type">Loại</Label>
                        <select id="st-type" v-model="form.type" :class="selectClass">
                            <option v-for="o in types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-color">Màu</Label>
                        <select id="st-color" v-model="form.color" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="c in colors" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <InputError :message="errors.color" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-order">Thứ tự</Label>
                        <Input id="st-order" v-model="form.sort_order" type="number" min="0" placeholder="Cuối danh sách" />
                        <InputError :message="errors.sort_order" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-prob">Xác suất thành công (%)</Label>
                        <Input id="st-prob" v-model="form.probability" type="number" min="0" max="100" />
                        <InputError :message="errors.probability" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="st-sla">SLA (giờ)</Label>
                        <Input id="st-sla" v-model="form.sla_hours" type="number" min="1" placeholder="Không giới hạn" />
                        <InputError :message="errors.sla_hours" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label>Trường bắt buộc để vào giai đoạn này</Label>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <label v-for="o in requirable" :key="o.value" class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" class="size-4" :checked="form.required_fields.includes(o.value)" @change="toggle(o.value)" />
                            {{ o.label }}
                        </label>
                    </div>
                    <InputError :message="fieldError('required_fields')" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm mới' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
