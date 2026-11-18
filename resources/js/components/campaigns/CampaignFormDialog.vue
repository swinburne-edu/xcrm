<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass, textareaClass } from './types';
import type { CampaignOptions, CampaignRow, TargetItem } from './types';

const props = defineProps<{
    /** null = create */
    record: CampaignRow | null;
    /** cycle a new campaign is created in (the one selected in the header) */
    cycleId: number | null;
    options: CampaignOptions;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const codeLocked = computed(() => isEdit.value && props.record!.status !== 'draft');
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    code: '',
    name: '',
    type: '',
    channel: '',
    parent_id: null as number | string | null,
    start_date: '',
    end_date: '',
    budget: '' as number | string,
    actual_cost: '' as number | string,
    target_leads: '' as number | string,
    target_applications: '' as number | string,
    target_enrolled: '' as number | string,
    owner_id: null as number | string | null,
    landing_url: '',
    description: '',
    majors: [] as (number | string)[],
    provinces: [] as (number | string)[],
    high_schools: [] as (number | string)[],
});

const ids = (items?: TargetItem[]): number[] => (items ?? []).map((i) => i.id);
const asItems = (items?: TargetItem[]) => (items ?? []).map((i) => ({ id: i.id, label: i.label, description: null }));

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        code: r?.code ?? '',
        name: r?.name ?? '',
        type: r?.type ?? '',
        channel: r?.channel ?? '',
        parent_id: r?.parent_id ?? null,
        start_date: r?.start_date ?? '',
        end_date: r?.end_date ?? '',
        budget: r?.budget ?? '',
        actual_cost: r?.actual_cost ?? '',
        target_leads: r?.target_leads ?? '',
        target_applications: r?.target_applications ?? '',
        target_enrolled: r?.target_enrolled ?? '',
        owner_id: r?.owner_id ?? null,
        landing_url: r?.landing_url ?? '',
        description: r?.description ?? '',
        majors: ids(r?.targets?.majors),
        provinces: ids(r?.targets?.provinces),
        high_schools: ids(r?.targets?.high_schools),
    };
});

function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(([k]) => k === key || k.startsWith(`${key}.`))?.[1];
}

function submit(): void {
    const { majors, provinces, high_schools, ...rest } = form.value;
    const data: Record<string, unknown> = Object.fromEntries(
        Object.entries(rest).map(([k, v]) => [k, v === '' ? null : v]),
    );
    data.targets = { majors, provinces, high_schools };

    if (!isEdit.value) {
        data.cycle_id = props.cycleId;
    }

    router.visit(isEdit.value ? `/campaigns/${props.record!.id}` : '/campaigns', {
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl" data-test="campaign-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa chiến dịch' : 'Thêm chiến dịch' }}</DialogTitle>
                    <DialogDescription>
                        Mã chiến dịch dùng làm <code>utm_campaign</code>, chỉ sửa được khi còn là nháp.
                    </DialogDescription>
                </DialogHeader>

                <InputError :message="fieldError('cycle_id')" />

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="cp-code">Mã (utm_campaign) <span class="text-destructive">*</span></Label>
                        <Input id="cp-code" v-model="form.code" maxlength="50" :disabled="codeLocked" placeholder="tuyen-sinh-2027-fb" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="cp-name">Tên chiến dịch <span class="text-destructive">*</span></Label>
                        <Input id="cp-name" v-model="form.name" />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cp-type">Loại</Label>
                        <select id="cp-type" v-model="form.type" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-channel">Kênh</Label>
                        <select id="cp-channel" v-model="form.channel" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.channels" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.channel" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-start">Bắt đầu</Label>
                        <Input id="cp-start" v-model="form.start_date" type="date" />
                        <InputError :message="errors.start_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-end">Kết thúc</Label>
                        <Input id="cp-end" v-model="form.end_date" type="date" />
                        <InputError :message="errors.end_date" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cp-owner">Người phụ trách</Label>
                        <EntityPicker
                            id="cp-owner"
                            v-model="form.owner_id"
                            entity="users"
                            placeholder="Mặc định: người tạo"
                            :initial-items="record?.owner_id ? [{ id: record.owner_id, label: record.owner_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.owner_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-parent">Chiến dịch cha</Label>
                        <EntityPicker
                            id="cp-parent"
                            v-model="form.parent_id"
                            entity="campaigns"
                            :params="{ cycle_id: record?.cycle_id ?? cycleId }"
                            placeholder="Không có"
                            :initial-items="record?.parent_id ? [{ id: record.parent_id, label: record.parent_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.parent_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="cp-budget">Ngân sách (VNĐ)</Label>
                        <Input id="cp-budget" v-model="form.budget" type="number" min="0" />
                        <InputError :message="errors.budget" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-cost">Chi phí thực (VNĐ)</Label>
                        <Input id="cp-cost" v-model="form.actual_cost" type="number" min="0" />
                        <InputError :message="errors.actual_cost" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="cp-tl">Mục tiêu thí sinh</Label>
                        <Input id="cp-tl" v-model="form.target_leads" type="number" min="0" />
                        <InputError :message="errors.target_leads" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-ta">Mục tiêu hồ sơ</Label>
                        <Input id="cp-ta" v-model="form.target_applications" type="number" min="0" />
                        <InputError :message="errors.target_applications" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cp-te">Mục tiêu nhập học</Label>
                        <Input id="cp-te" v-model="form.target_enrolled" type="number" min="0" />
                        <InputError :message="errors.target_enrolled" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="cp-majors">Ngành mục tiêu</Label>
                    <EntityPicker id="cp-majors" v-model="form.majors" entity="majors" multiple placeholder="Chọn ngành" :initial-items="asItems(record?.targets?.majors)" />
                    <InputError :message="fieldError('targets.majors')" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-provinces">Tỉnh/thành phố mục tiêu</Label>
                    <EntityPicker id="cp-provinces" v-model="form.provinces" entity="provinces" multiple placeholder="Chọn tỉnh/thành phố" :initial-items="asItems(record?.targets?.provinces)" />
                    <InputError :message="fieldError('targets.provinces')" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-schools">Trường THPT mục tiêu</Label>
                    <EntityPicker id="cp-schools" v-model="form.high_schools" entity="high_schools" multiple placeholder="Chọn trường" :initial-items="asItems(record?.targets?.high_schools)" />
                    <InputError :message="fieldError('targets.high_schools')" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="cp-url">Trang đích</Label>
                    <Input id="cp-url" v-model="form.landing_url" placeholder="https://" />
                    <InputError :message="errors.landing_url" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-desc">Mô tả</Label>
                    <textarea id="cp-desc" v-model="form.description" rows="3" :class="textareaClass" />
                    <InputError :message="errors.description" />
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
