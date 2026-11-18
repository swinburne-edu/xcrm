<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from './types';
import type { AppointmentRow, Option } from './types';

const props = defineProps<{
    /** null = book a new one */
    record: AppointmentRow | null;
    types: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    case_id: null as number | string | null,
    type: 'counseling',
    start_at: '',
    end_at: '',
    location: '',
    host_id: null as number | string | null,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        case_id: r?.case_id ?? null,
        type: r?.type ?? 'counseling',
        start_at: r?.start_input ?? '',
        end_at: r?.end_input ?? '',
        location: r?.location ?? '',
        host_id: r?.host_id ?? null,
    };
});

function submit(): void {
    const { case_id, ...rest } = form.value;
    const data: Record<string, unknown> = Object.fromEntries(Object.entries(rest).map(([k, v]) => [k, v === '' ? null : v]));

    if (!isEdit.value) {
        data.case_id = case_id;
    }

    router.visit(isEdit.value ? `/appointments/${props.record!.id}` : '/appointments', {
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
        <DialogContent class="sm:max-w-lg" data-test="appointment-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Đổi lịch hẹn' : 'Đặt lịch hẹn' }}</DialogTitle>
                    <DialogDescription>Giờ theo múi giờ Việt Nam. Lịch hẹn được ghi vào timeline của hồ sơ.</DialogDescription>
                </DialogHeader>

                <div v-if="!isEdit" class="grid gap-1.5">
                    <Label for="ap-case">Hồ sơ <span class="text-destructive">*</span></Label>
                    <EntityPicker id="ap-case" v-model="form.case_id" entity="cases" placeholder="Tìm thí sinh, mã hồ sơ…" />
                    <InputError :message="errors.case_id" />
                </div>
                <p v-else class="text-sm">
                    Thí sinh: <span class="font-medium">{{ record?.applicant_name }}</span>
                </p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="ap-type">Loại <span class="text-destructive">*</span></Label>
                        <select id="ap-type" v-model="form.type" :class="selectClass">
                            <option v-for="o in types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ap-host">Người phụ trách</Label>
                        <EntityPicker
                            id="ap-host"
                            v-model="form.host_id"
                            entity="users"
                            placeholder="Mặc định: bạn"
                            :initial-items="record?.host_id ? [{ id: record.host_id, label: record.host_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.host_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ap-start">Bắt đầu <span class="text-destructive">*</span></Label>
                        <Input id="ap-start" v-model="form.start_at" type="datetime-local" data-test="appointment-start" />
                        <InputError :message="errors.start_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ap-end">Kết thúc</Label>
                        <Input id="ap-end" v-model="form.end_at" type="datetime-local" />
                        <InputError :message="errors.end_at" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="ap-location">Địa điểm</Label>
                    <Input id="ap-location" v-model="form.location" maxlength="255" />
                    <InputError :message="errors.location" />
                </div>
                <InputError :message="errors.attendance" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="appointment-save">
                        <Spinner v-if="processing" />
                        Lưu
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
