<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
import CustomFieldInput from '@/components/custom/CustomFieldInput.vue';
import { blankValues } from '@/components/custom/types';
import type { CustomValues } from '@/components/custom/types';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from './types';
import type { ApplicantOptions, ApplicantRow, DuplicateMatch } from './types';

const props = defineProps<{
    /** null = create */
    record: ApplicantRow | null;
    options: ApplicantOptions;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const masked = computed(() => isEdit.value && props.record!.sensitive_masked === true);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const duplicates = ref<DuplicateMatch[]>([]);

const form = ref({
    full_name: '',
    gender: '',
    dob: '',
    phone: '',
    email: '',
    citizen_id: '',
    zalo_id: '',
    facebook_id: '',
    province_id: null as number | string | null,
    ward_id: null as number | string | null,
    address: '',
    high_school_id: null as number | string | null,
    class_name: '',
    current_grade: '',
    graduation_year: '' as number | string,
    priority_area_id: '',
    priority_group_id: '',
    first_source_id: '',
    first_campaign_id: null as number | string | null,
    referred_by_user_id: null as number | string | null,
    consent_marketing: false,
});

const custom = ref<CustomValues>({});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    custom.value = blankValues(props.options.customFields, props.record?.custom_data);
    duplicates.value = [];
    const r = props.record;
    const m = r?.sensitive_masked === true;
    form.value = {
        full_name: r?.full_name ?? '',
        gender: r?.gender ?? '',
        dob: r?.dob ?? '',
        // A masked value is never sent back: blank means "unchanged".
        phone: m ? '' : (r?.phone ?? ''),
        email: r?.email ?? '',
        citizen_id: m ? '' : (r?.citizen_id ?? ''),
        zalo_id: r?.zalo_id ?? '',
        facebook_id: r?.facebook_id ?? '',
        province_id: r?.province_id ?? null,
        ward_id: r?.ward_id ?? null,
        address: r?.address ?? '',
        high_school_id: r?.high_school_id ?? null,
        class_name: r?.class_name ?? '',
        current_grade: r?.current_grade ? String(r.current_grade) : '',
        graduation_year: r?.graduation_year ?? '',
        priority_area_id: r?.priority_area_id ? String(r.priority_area_id) : '',
        priority_group_id: r?.priority_group_id ? String(r.priority_group_id) : '',
        first_source_id: r?.first_source_id ? String(r.first_source_id) : '',
        first_campaign_id: r?.first_campaign_id ?? null,
        referred_by_user_id: r?.referred_by_user_id ?? null,
        consent_marketing: r?.consent_marketing ?? false,
    };
    // immediate: the dialog can be mounted already open (/applicants/{uuid}?edit=1), when open never changes.
}, { immediate: true });

// Realtime duplicate warning (debounced).
let timer: ReturnType<typeof setTimeout> | undefined;
let requestId = 0;

watch(
    () => [form.value.phone, form.value.email, form.value.citizen_id],
    () => {
        clearTimeout(timer);
        timer = setTimeout(checkDuplicates, 450);
    },
);

onBeforeUnmount(() => clearTimeout(timer));

async function checkDuplicates(): Promise<void> {
    const { phone, email, citizen_id } = form.value;

    if (!open.value || (!phone && !email && !citizen_id)) {
        duplicates.value = [];

        return;
    }

    const params = new URLSearchParams();

    for (const [k, v] of Object.entries({ phone, email, citizen_id })) {
        if (v) {
            params.set(k, v);
        }
    }

    if (props.record) {
        params.set('except', props.record.uuid);
    }

    const id = ++requestId;

    try {
        const response = await fetch(`/applicants/check-duplicate?${params}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (response.ok && id === requestId) {
            duplicates.value = (await response.json()).matches as DuplicateMatch[];
        }
    } catch {
        // The server validates again on save; a failed hint is not an error.
    }
}

function submit(): void {
    const data = Object.fromEntries(
        Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]),
    );

    router.visit(isEdit.value ? `/applicants/${props.record!.uuid}` : '/applicants', {
        method: isEdit.value ? 'put' : 'post',
        data: { ...data, custom: custom.value } as never,
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl" data-test="applicant-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa thí sinh' : 'Thêm thí sinh' }}</DialogTitle>
                    <DialogDescription>
                        Hệ thống kiểm tra trùng theo số điện thoại, email và số CCCD khi bạn nhập.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="duplicates.length"
                    class="grid gap-1 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                    role="alert"
                    data-test="duplicate-warning"
                >
                    <div class="flex items-center gap-2 font-medium"><TriangleAlert class="size-4" />Có thể trùng với thí sinh đã có</div>
                    <p v-for="d in duplicates" :key="d.field">
                        {{ d.label }} đã thuộc về
                        <Link v-if="d.visible && d.uuid" :href="`/applicants/${d.uuid}`" class="underline">{{ d.code }} – {{ d.full_name }}</Link>
                        <span v-else>một thí sinh khác (ngoài phạm vi dữ liệu của bạn)</span>.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="a-name">Họ tên <span class="text-destructive">*</span></Label>
                        <Input id="a-name" v-model="form.full_name" />
                        <InputError :message="errors.full_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-gender">Giới tính</Label>
                        <select id="a-gender" v-model="form.gender" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.genders" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.gender" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="a-phone">Số điện thoại</Label>
                        <Input id="a-phone" v-model="form.phone" maxlength="20" :placeholder="masked ? (record?.phone ?? '') + ' (để trống = giữ nguyên)' : '09xx xxx xxx'" />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-email">Email</Label>
                        <Input id="a-email" v-model="form.email" type="email" />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-cccd">Số CCCD</Label>
                        <Input id="a-cccd" v-model="form.citizen_id" inputmode="numeric" maxlength="12" :placeholder="masked ? (record?.citizen_id ?? '') + ' (để trống = giữ nguyên)' : ''" />
                        <InputError :message="errors.citizen_id" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="a-dob">Ngày sinh</Label>
                        <Input id="a-dob" v-model="form.dob" type="date" />
                        <InputError :message="errors.dob" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-zalo">Zalo</Label>
                        <Input id="a-zalo" v-model="form.zalo_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-fb">Facebook</Label>
                        <Input id="a-fb" v-model="form.facebook_id" />
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
                <div class="grid gap-1.5">
                    <Label for="a-address">Địa chỉ</Label>
                    <Input id="a-address" v-model="form.address" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="a-school">Trường THPT</Label>
                        <EntityPicker
                            id="a-school"
                            v-model="form.high_school_id"
                            entity="high_schools"
                            :params="{ province_id: form.province_id }"
                            placeholder="Chọn trường"
                            :initial-items="record?.high_school_id ? [{ id: record.high_school_id, label: record.high_school_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.high_school_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-class">Lớp</Label>
                        <Input id="a-class" v-model="form.class_name" maxlength="20" placeholder="12A1" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="a-grade">Khối</Label>
                            <select id="a-grade" v-model="form.current_grade" :class="selectClass">
                                <option value="">—</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                                <option value="13">Đã tốt nghiệp</option>
                            </select>
                            <InputError :message="errors.current_grade" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="a-year">Năm tốt nghiệp</Label>
                            <Input id="a-year" v-model="form.graduation_year" type="number" min="2000" max="2100" />
                            <InputError :message="errors.graduation_year" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="a-area">Khu vực ưu tiên</Label>
                        <select id="a-area" v-model="form.priority_area_id" :class="selectClass">
                            <option value="">Theo trường THPT</option>
                            <option v-for="o in options.priorityAreas" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.priority_area_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-group">Đối tượng ưu tiên</Label>
                        <select id="a-group" v-model="form.priority_group_id" :class="selectClass">
                            <option value="">Không</option>
                            <option v-for="o in options.priorityGroups" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.priority_group_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-source">Nguồn đầu tiên</Label>
                        <select id="a-source" v-model="form.first_source_id" :class="selectClass">
                            <option value="">—</option>
                            <option v-for="o in options.sources" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.first_source_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="a-campaign">Chiến dịch đầu tiên</Label>
                        <EntityPicker
                            id="a-campaign"
                            v-model="form.first_campaign_id"
                            entity="campaigns"
                            placeholder="Chọn chiến dịch"
                            :initial-items="record?.first_campaign_id ? [{ id: record.first_campaign_id, label: record.first_campaign_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.first_campaign_id" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="a-ref">Người giới thiệu (cộng tác viên/đại sứ)</Label>
                        <EntityPicker
                            id="a-ref"
                            v-model="form.referred_by_user_id"
                            entity="users"
                            placeholder="Không có"
                            :initial-items="record?.referred_by_user_id ? [{ id: record.referred_by_user_id, label: record.referrer_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.referred_by_user_id" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.consent_marketing" type="checkbox" class="size-4" data-test="consent" />
                    Đồng ý nhận thông tin tuyển sinh
                </label>

                <div v-if="options.customFields.length" class="grid gap-3 border-t pt-4 sm:grid-cols-2" data-test="custom-form">
                    <CustomFieldInput v-for="f in options.customFields" :key="f.key" v-model="custom[f.key]" :field="f" :error="errors[`custom.${f.key}`]" id-prefix="cff" />
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
