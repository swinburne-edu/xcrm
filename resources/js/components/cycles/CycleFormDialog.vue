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
import type { CycleRow, Option } from './types';

const props = defineProps<{
    /** null = create */
    record: CycleRow | null;
    levelOptions: Option[];
    statusOptions: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    year: new Date().getFullYear() as number | string,
    level: 'university',
    status: 'preparing',
    start_date: '',
    end_date: '',
    target_enrolled: '' as number | string,
    regulation_ref: '',
    is_current: false,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    const year = r?.year ?? new Date().getFullYear() + 1;
    form.value = {
        name: r?.name ?? `Tuyển sinh năm ${year}`,
        year,
        level: r?.level ?? 'university',
        status: r?.status ?? 'preparing',
        start_date: r?.start_date ?? '',
        end_date: r?.end_date ?? '',
        target_enrolled: r?.target_enrolled ?? '',
        regulation_ref: r?.regulation_ref ?? '',
        is_current: r?.is_current ?? false,
    };
});

function submit(): void {
    const data = {
        ...form.value,
        start_date: form.value.start_date || null,
        end_date: form.value.end_date || null,
        target_enrolled:
            form.value.target_enrolled === ''
                ? null
                : form.value.target_enrolled,
        regulation_ref: form.value.regulation_ref || null,
    };

    router.visit(
        isEdit.value
            ? `/settings/cycles/${props.record!.id}`
            : '/settings/cycles',
        {
            method: isEdit.value ? 'put' : 'post',
            data: data as never,
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onError: (e) => (errors.value = e),
            onSuccess: () => (open.value = false),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            data-test="cycle-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa mùa tuyển sinh' : 'Thêm mùa tuyển sinh'
                    }}</DialogTitle>
                    <DialogDescription>
                        Mọi cấu hình tuyển sinh (phương thức, chỉ tiêu, mốc thời
                        gian) gắn với một mùa. Mỗi bậc chỉ có một mùa hiện tại.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="c-name"
                        >Tên mùa <span class="text-destructive">*</span></Label
                    >
                    <Input id="c-name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="c-year"
                            >Năm <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="c-year"
                            v-model="form.year"
                            type="number"
                            min="2000"
                            max="2200"
                        />
                        <InputError :message="errors.year" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-level">Bậc</Label>
                        <select
                            id="c-level"
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
                    <div class="grid gap-1.5">
                        <Label for="c-status">Trạng thái</Label>
                        <select
                            id="c-status"
                            v-model="form.status"
                            :class="selectClass"
                        >
                            <option
                                v-for="o in statusOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="c-start">Bắt đầu</Label>
                        <Input
                            id="c-start"
                            v-model="form.start_date"
                            type="date"
                        />
                        <InputError :message="errors.start_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-end">Kết thúc</Label>
                        <Input
                            id="c-end"
                            v-model="form.end_date"
                            type="date"
                        />
                        <InputError :message="errors.end_date" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="c-target">Chỉ tiêu nhập học (mục tiêu)</Label>
                        <Input
                            id="c-target"
                            v-model="form.target_enrolled"
                            type="number"
                            min="0"
                        />
                        <InputError :message="errors.target_enrolled" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-ref">Căn cứ quy chế</Label>
                        <Input
                            id="c-ref"
                            v-model="form.regulation_ref"
                            placeholder="VD: Thông tư 08/2022"
                        />
                        <InputError :message="errors.regulation_ref" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_current"
                        type="checkbox"
                        class="size-4"
                        data-test="is-current"
                    />
                    Đặt làm mùa hiện tại (tự chuyển sang "Đang tuyển sinh")
                </label>
                <InputError :message="errors.is_current" />

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
