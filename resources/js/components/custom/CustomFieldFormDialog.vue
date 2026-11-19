<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CustomFieldDefinitionRow } from './types';

/**
 * Add or edit a custom field definition. Object, key and type are chosen on create and then fixed (values are stored
 * under that key). Select types list their choices one per line. Server rules: CustomFieldDefinitionRequest.
 */
export type CustomFieldOptions = {
    objects: { value: string; label: string }[];
    types: { value: string; label: string; has_options: boolean }[];
};

const props = defineProps<{ record: CustomFieldDefinitionRow | null; options: CustomFieldOptions; defaultObject: string }>();
const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const keyTouched = ref(false);
const form = ref(blank());

function blank() {
    return { object: props.defaultObject, key: '', label: '', type: 'text', options: '', is_required: false, is_filterable: true };
}

const hasOptions = computed(() => props.options.types.find((t) => t.value === (isEdit.value ? props.record!.type : form.value.type))?.has_options ?? false);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    keyTouched.value = false;
    const r = props.record;
    form.value = r
        ? { object: r.object, key: r.key, label: r.label, type: r.type, options: r.options.join('\n'), is_required: r.is_required, is_filterable: r.is_filterable }
        : blank();
});

/** Suggests a key from the label until the person edits the key by hand. */
function suggestKey(): void {
    if (isEdit.value || keyTouched.value) {
        return;
    }

    form.value.key = form.value.label
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^(\d)/, 'f_$1')
        .slice(0, 64);
}

function submit(): void {
    const f = form.value;
    const payload = isEdit.value
        ? { label: f.label, options: f.options.split('\n'), is_required: f.is_required, is_filterable: f.is_filterable }
        : { ...f, options: f.options.split('\n') };

    router.visit(isEdit.value ? `/settings/custom-fields/${props.record!.id}` : '/settings/custom-fields', {
        method: isEdit.value ? 'put' : 'post',
        data: payload as never,
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg" data-test="custom-field-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa trường tuỳ chỉnh' : 'Thêm trường tuỳ chỉnh' }}</DialogTitle>
                    <DialogDescription>Trường xuất hiện trong form thêm/sửa, trang chi tiết và bộ lọc của đối tượng đã chọn.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cf-object">Đối tượng</Label>
                        <select id="cf-object" v-model="form.object" :class="selectClass" :disabled="isEdit" data-test="cf-object">
                            <option v-for="o in options.objects" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.object" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cf-type">Kiểu dữ liệu</Label>
                        <select id="cf-type" v-model="form.type" :class="selectClass" :disabled="isEdit" data-test="cf-type">
                            <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="cf-label">Tên hiển thị <span class="text-destructive">*</span></Label>
                    <Input id="cf-label" v-model="form.label" autocomplete="off" placeholder="VD: Học bổng quan tâm" data-test="cf-label" @input="suggestKey" />
                    <InputError :message="errors.label" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="cf-key">Mã trường <span class="text-destructive">*</span></Label>
                    <Input id="cf-key" v-model="form.key" :disabled="isEdit" autocomplete="off" placeholder="hoc_bong_quan_tam" data-test="cf-key" @input="keyTouched = true" />
                    <p class="text-xs text-muted-foreground">Chữ thường không dấu, số và dấu gạch dưới. Không đổi được sau khi tạo.</p>
                    <InputError :message="errors.key" />
                </div>

                <div v-if="hasOptions" class="grid gap-1.5">
                    <Label for="cf-options">Các lựa chọn (mỗi dòng một lựa chọn) <span class="text-destructive">*</span></Label>
                    <textarea
                        id="cf-options"
                        v-model="form.options"
                        rows="4"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        data-test="cf-options"
                    />
                    <InputError :message="errors.options" />
                </div>

                <div class="grid gap-2 text-sm">
                    <label class="flex items-center gap-2">
                        <Checkbox :model-value="form.is_required" data-test="cf-required" @update:model-value="(v) => (form.is_required = v === true)" />
                        Bắt buộc nhập
                    </label>
                    <label class="flex items-center gap-2">
                        <Checkbox :model-value="form.is_filterable" data-test="cf-filterable" @update:model-value="(v) => (form.is_filterable = v === true)" />
                        Cho phép lọc theo trường này trong danh sách
                    </label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="cf-submit">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm trường' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
