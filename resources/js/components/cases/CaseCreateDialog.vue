<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from './types';
import type { CaseOptions } from './types';

/** Opens the case of an existing applicant in the selected cycle. */
const props = defineProps<{ cycleName: string | null; options: CaseOptions; canAssign: boolean }>();
const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    applicant_id: null as number | string | null,
    source_id: '',
    campaign_id: null as number | string | null,
    temperature: '',
    owner_id: null as number | string | null,
});

watch(open, (isOpen) => {
    if (isOpen) {
        errors.value = {};
        form.value = { applicant_id: null, source_id: '', campaign_id: null, temperature: '', owner_id: null };
    }
});

function submit(): void {
    const data: Record<string, unknown> = {
        applicant_id: form.value.applicant_id,
        source_id: form.value.source_id || null,
        campaign_id: form.value.campaign_id,
        temperature: form.value.temperature || null,
    };

    if (props.canAssign && form.value.owner_id !== null) {
        data.owner_id = form.value.owner_id;
    }

    router.post('/cases', data as never, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg" data-test="case-create">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Thêm hồ sơ tuyển sinh</DialogTitle>
                    <DialogDescription>
                        Tạo hồ sơ cho một thí sinh có sẵn trong {{ cycleName ?? 'mùa đang chọn' }}. Hồ sơ vào giai đoạn đầu; người phụ trách tự giao theo vùng nếu không chọn.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="cc-applicant">Thí sinh <span class="text-destructive">*</span></Label>
                    <EntityPicker id="cc-applicant" v-model="form.applicant_id" entity="applicants" placeholder="Tìm theo tên, mã, SĐT…" />
                    <InputError :message="errors.applicant_id" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cc-source">Nguồn</Label>
                        <select id="cc-source" v-model="form.source_id" :class="selectClass">
                            <option value="">Theo thí sinh</option>
                            <option v-for="o in options.sources" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.source_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cc-temp">Mức độ quan tâm</Label>
                        <select id="cc-temp" v-model="form.temperature" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.temperatures" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.temperature" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cc-campaign">Chiến dịch</Label>
                        <EntityPicker id="cc-campaign" v-model="form.campaign_id" entity="campaigns" placeholder="Theo thí sinh" />
                        <InputError :message="errors.campaign_id" />
                    </div>
                    <div v-if="canAssign" class="grid gap-1.5">
                        <Label for="cc-owner">Người phụ trách</Label>
                        <EntityPicker id="cc-owner" v-model="form.owner_id" entity="users" placeholder="Tự giao theo vùng" />
                        <InputError :message="errors.owner_id" />
                    </div>
                </div>

                <InputError :message="errors.cycle_id" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="case-create-submit">
                        <Spinner v-if="processing" />
                        Tạo hồ sơ
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
