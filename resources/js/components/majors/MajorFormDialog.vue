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

export type Option = { value: string; label: string };

export type MajorRow = {
    id: number;
    moet_code: string | null;
    admission_code: string;
    name: string;
    faculty_id: number | null;
    faculty_name: string | null;
    campus_id: number | null;
    campus_name: string | null;
    program_type: string;
    program_type_label: string;
    level: string;
    level_label: string;
    duration_years: number | null;
    is_active: boolean;
    description?: string | null;
    career_info?: string | null;
};

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const props = defineProps<{
    /** null = create */
    record: MajorRow | null;
    levelOptions: Option[];
    programTypeOptions: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    admission_code: '',
    moet_code: '',
    name: '',
    faculty_id: null as number | string | null,
    campus_id: null as number | string | null,
    program_type: 'standard',
    level: 'university',
    duration_years: '' as number | string,
    description: '',
    career_info: '',
    is_active: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        admission_code: r?.admission_code ?? '',
        moet_code: r?.moet_code ?? '',
        name: r?.name ?? '',
        faculty_id: r?.faculty_id ?? null,
        campus_id: r?.campus_id ?? null,
        program_type: r?.program_type ?? 'standard',
        level: r?.level ?? 'university',
        duration_years: r?.duration_years ?? 4,
        description: r?.description ?? '',
        career_info: r?.career_info ?? '',
        is_active: r?.is_active ?? true,
    };
});

function submit(): void {
    const f = form.value;
    const data = {
        ...f,
        moet_code: f.moet_code || null,
        duration_years: f.duration_years === '' ? null : f.duration_years,
        description: f.description || null,
        career_info: f.career_info || null,
    };

    router.visit(isEdit.value ? `/majors/${props.record!.id}` : '/majors', {
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
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            data-test="major-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa ngành' : 'Thêm ngành'
                    }}</DialogTitle>
                    <DialogDescription>
                        Chỉ tiêu, tổ hợp và phương thức được cấu hình theo từng
                        mùa ở trang chi tiết ngành.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="mj-code"
                            >Mã xét tuyển
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="mj-code"
                            v-model="form.admission_code"
                            maxlength="20"
                            placeholder="VD: CNTT01"
                        />
                        <InputError :message="errors.admission_code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mj-moet">Mã ngành của Bộ (7 số)</Label>
                        <Input
                            id="mj-moet"
                            v-model="form.moet_code"
                            maxlength="7"
                            inputmode="numeric"
                            placeholder="7480201"
                        />
                        <InputError :message="errors.moet_code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mj-years">Thời gian đào tạo (năm)</Label>
                        <Input
                            id="mj-years"
                            v-model="form.duration_years"
                            type="number"
                            step="0.5"
                            min="0.5"
                            max="10"
                        />
                        <InputError :message="errors.duration_years" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="mj-name"
                        >Tên ngành <span class="text-destructive">*</span></Label
                    >
                    <Input id="mj-name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="mj-faculty">Khoa</Label>
                        <EntityPicker
                            id="mj-faculty"
                            v-model="form.faculty_id"
                            entity="faculties"
                            placeholder="Chọn khoa"
                            :initial-items="
                                record?.faculty_id
                                    ? [
                                          {
                                              id: record.faculty_id,
                                              label: record.faculty_name ?? '',
                                              description: null,
                                          },
                                      ]
                                    : []
                            "
                        />
                        <InputError :message="errors.faculty_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mj-campus">Cơ sở</Label>
                        <EntityPicker
                            id="mj-campus"
                            v-model="form.campus_id"
                            entity="campuses"
                            placeholder="Chọn cơ sở"
                            :initial-items="
                                record?.campus_id
                                    ? [
                                          {
                                              id: record.campus_id,
                                              label: record.campus_name ?? '',
                                              description: null,
                                          },
                                      ]
                                    : []
                            "
                        />
                        <InputError :message="errors.campus_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mj-type">Loại chương trình</Label>
                        <select
                            id="mj-type"
                            v-model="form.program_type"
                            :class="selectClass"
                        >
                            <option
                                v-for="o in programTypeOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.program_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mj-level">Bậc</Label>
                        <select
                            id="mj-level"
                            v-model="form.level"
                            :class="selectClass"
                        >
                            <option
                                v-for="o in levelOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.level" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="mj-desc">Giới thiệu ngành</Label>
                    <textarea
                        id="mj-desc"
                        v-model="form.description"
                        rows="3"
                        :class="textareaClass"
                    />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="mj-career">Cơ hội nghề nghiệp</Label>
                    <textarea
                        id="mj-career"
                        v-model="form.career_info"
                        rows="3"
                        :class="textareaClass"
                    />
                    <InputError :message="errors.career_info" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Đang sử dụng (bỏ chọn để ngừng tuyển ngành này)
                </label>

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
