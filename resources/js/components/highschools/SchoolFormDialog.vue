<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import ProvinceWardSelect from '@/components/crm/ProvinceWardSelect.vue';
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
import type { SchoolOptions, SchoolRow } from './types';

const props = defineProps<{
    /** null = create */
    record: SchoolRow | null;
    options: SchoolOptions;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    code: '',
    name: '',
    type: '',
    province_id: null as number | string | null,
    ward_id: null as number | string | null,
    address: '',
    priority_area_id: '',
    grade12_students: '' as number | string,
    grade12_classes: '' as number | string,
    strong_combinations: [] as string[],
    tier: '',
    partnership_status: 'not_contacted',
    mou_signed_at: '',
    owner_id: null as number | string | null,
    phone: '',
    email: '',
    website: '',
    note: '',
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
        type: r?.type ?? '',
        province_id: r?.province_id ?? null,
        ward_id: r?.ward_id ?? null,
        address: r?.address ?? '',
        priority_area_id: r?.priority_area_id ? String(r.priority_area_id) : '',
        grade12_students: r?.grade12_students ?? '',
        grade12_classes: r?.grade12_classes ?? '',
        strong_combinations: [...(r?.strong_combinations ?? [])],
        tier: r?.tier ?? '',
        partnership_status: r?.partnership_status ?? 'not_contacted',
        mou_signed_at: r?.mou_signed_at ?? '',
        owner_id: r?.owner_id ?? null,
        phone: r?.phone ?? '',
        email: r?.email ?? '',
        website: r?.website ?? '',
        note: r?.note ?? '',
    };
});

function toggleCombo(code: string): void {
    const list = form.value.strong_combinations;
    const i = list.indexOf(code);

    if (i === -1) {
        list.push(code);
    } else {
        list.splice(i, 1);
    }
}

function submit(): void {
    const data = Object.fromEntries(
        Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]),
    );

    router.visit(isEdit.value ? `/high-schools/${props.record!.id}` : '/high-schools', {
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl" data-test="school-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa trường THPT' : 'Thêm trường THPT' }}</DialogTitle>
                    <DialogDescription>
                        Trường mới chưa có người phụ trách sẽ được giao theo vùng phụ trách của tỉnh (nếu có).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="s-code">Mã trường</Label>
                        <Input id="s-code" v-model="form.code" maxlength="20" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="s-name">Tên trường <span class="text-destructive">*</span></Label>
                        <Input id="s-name" v-model="form.name" />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="s-type">Loại hình</Label>
                        <select id="s-type" v-model="form.type" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-tier">Hạng tiềm năng</Label>
                        <select id="s-tier" v-model="form.tier" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.tiers" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.tier" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-partner">Trạng thái hợp tác</Label>
                        <select id="s-partner" v-model="form.partnership_status" :class="selectClass">
                            <option v-for="o in options.partnership" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.partnership_status" />
                    </div>
                </div>

                <ProvinceWardSelect
                    v-model:province-id="form.province_id"
                    v-model:ward-id="form.ward_id"
                    :initial-items="{
                        province: record?.province_id ? { id: record.province_id, label: record.province_name ?? '', description: null } : undefined,
                        ward: record?.ward_id ? { id: record.ward_id, label: record.ward_name ?? '', description: null } : undefined,
                    }"
                />
                <InputError :message="errors.province_id ?? errors.ward_id" />

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="s-address">Địa chỉ</Label>
                        <Input id="s-address" v-model="form.address" />
                        <InputError :message="errors.address" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-area">Khu vực ưu tiên</Label>
                        <select id="s-area" v-model="form.priority_area_id" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.priorityAreas" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.priority_area_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="s-students">Học sinh khối 12</Label>
                        <Input id="s-students" v-model="form.grade12_students" type="number" min="0" />
                        <InputError :message="errors.grade12_students" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-classes">Số lớp khối 12</Label>
                        <Input id="s-classes" v-model="form.grade12_classes" type="number" min="0" />
                        <InputError :message="errors.grade12_classes" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-mou">Ngày ký MOU</Label>
                        <Input id="s-mou" v-model="form.mou_signed_at" type="date" />
                        <InputError :message="errors.mou_signed_at" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label>Tổ hợp mạnh</Label>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <label v-for="c in options.combinations" :key="c" class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" class="size-4" :checked="form.strong_combinations.includes(c)" @change="toggleCombo(c)" />
                            {{ c }}
                        </label>
                    </div>
                    <InputError :message="errors.strong_combinations" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="s-owner">Người phụ trách</Label>
                    <EntityPicker
                        id="s-owner"
                        v-model="form.owner_id"
                        entity="users"
                        placeholder="Chọn người phụ trách"
                        :initial-items="record?.owner_id ? [{ id: record.owner_id, label: record.owner_name ?? '', description: null }] : []"
                    />
                    <InputError :message="errors.owner_id" />
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="s-phone">Điện thoại</Label>
                        <Input id="s-phone" v-model="form.phone" maxlength="20" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-email">Email</Label>
                        <Input id="s-email" v-model="form.email" type="email" />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-web">Website</Label>
                        <Input id="s-web" v-model="form.website" />
                        <InputError :message="errors.website" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="s-note">Ghi chú</Label>
                    <textarea id="s-note" v-model="form.note" rows="3" :class="textareaClass" />
                    <InputError :message="errors.note" />
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
