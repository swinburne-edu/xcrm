<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type FormRow = {
    id: number;
    uuid: string;
    name: string;
    campaign_id: number | null;
    campaign_name: string | null;
    source_id: number | null;
    source_name: string | null;
    fields: { key: string; required: boolean }[];
    success_message: string | null;
    is_active: boolean;
    embed: string;
};

export type FormOptions = {
    fields: { key: string; label: string }[];
    sources: { value: string; label: string }[];
    campaigns: { value: string; label: string }[];
};

const props = defineProps<{ record: FormRow | null; options: FormOptions }>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const optional = computed(() => props.options.fields.filter((f) => f.key !== 'full_name' && f.key !== 'phone'));
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    source_id: '' as string,
    campaign_id: '' as string,
    success_message: '',
    is_active: true,
    shown: {} as Record<string, boolean>,
    required: {} as Record<string, boolean>,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        name: r?.name ?? '',
        source_id: r?.source_id ? String(r.source_id) : '',
        campaign_id: r?.campaign_id ? String(r.campaign_id) : '',
        success_message: r?.success_message ?? '',
        is_active: r?.is_active ?? true,
        shown: Object.fromEntries((r?.fields ?? [{ key: 'email', required: false }]).map((f) => [f.key, true])),
        required: Object.fromEntries((r?.fields ?? []).filter((f) => f.required).map((f) => [f.key, true])),
    };
});

function submit(): void {
    const f = form.value;
    const data = {
        name: f.name,
        source_id: f.source_id || null,
        campaign_id: f.campaign_id || null,
        success_message: f.success_message || null,
        is_active: f.is_active,
        fields: optional.value.filter((o) => f.shown[o.key]).map((o) => ({ key: o.key, required: !!f.required[o.key] })),
    };

    router.visit(isEdit.value ? `/forms/${props.record!.id}` : '/forms', {
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

const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl" data-test="form-dialog">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa biểu mẫu' : 'Thêm biểu mẫu' }}</DialogTitle>
                    <DialogDescription>Họ tên và số điện thoại luôn bắt buộc. Chọn thêm các trường cần hỏi.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="fm-name">Tên biểu mẫu <span class="text-destructive">*</span></Label>
                    <Input id="fm-name" v-model="form.name" maxlength="255" data-test="form-name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="fm-source">Nguồn (mặc định: Website)</Label>
                        <select id="fm-source" v-model="form.source_id" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.sources" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.source_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="fm-campaign">Chiến dịch</Label>
                        <select id="fm-campaign" v-model="form.campaign_id" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.campaigns" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.campaign_id" />
                    </div>
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Các trường hiển thị</legend>
                    <div v-for="f in optional" :key="f.key" class="flex items-center justify-between gap-3 text-sm">
                        <label class="flex items-center gap-2">
                            <Checkbox :model-value="!!form.shown[f.key]" :data-test="`show-${f.key}`" @update:model-value="(v) => (form.shown[f.key] = v === true)" />
                            {{ f.label }}
                        </label>
                        <label v-if="form.shown[f.key]" class="flex items-center gap-2 text-muted-foreground">
                            <Checkbox :model-value="!!form.required[f.key]" @update:model-value="(v) => (form.required[f.key] = v === true)" />
                            Bắt buộc
                        </label>
                    </div>
                </fieldset>

                <div class="grid gap-1.5">
                    <Label for="fm-msg">Thông báo sau khi gửi</Label>
                    <Input id="fm-msg" v-model="form.success_message" maxlength="1000" placeholder="Cảm ơn bạn! Nhà trường sẽ liên hệ sớm." />
                    <InputError :message="errors.success_message" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" />
                    Đang nhận đăng ký
                </label>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="form-save">
                        <Spinner v-if="processing" />
                        Lưu
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
