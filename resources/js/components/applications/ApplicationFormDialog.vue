<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from '@/components/campaigns/types';
import type { ApplicationItem, ApplicationOptions } from './types';

/**
 * Add or edit a draft nguyện vọng. The major decides which methods and combinations can be picked (rule R4);
 * the server checks the same rules and the 15-per-case limit (R3).
 */
const props = defineProps<{
    caseUuid: string;
    /** null = create */
    record: ApplicationItem | null;
    options: ApplicationOptions;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ round_id: '' as number | '', major_id: '' as number | '', cycle_method_id: '' as number | '', combination_id: '' as number | '', preference_order: '' as number | '' });

const major = computed(() => props.options.majors.find((m) => m.id === form.value.major_id) ?? null);
const method = computed(() => major.value?.methods.find((m) => m.id === form.value.cycle_method_id) ?? null);
const needsCombination = computed(() => method.value?.needs_combination ?? false);
const showCombination = computed(() => !!method.value && !method.value.direct && (major.value?.combinations.length ?? 0) > 0);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        round_id: r?.round_id ?? (props.options.rounds.length === 1 ? props.options.rounds[0].id : ''),
        major_id: r?.major_id ?? '',
        cycle_method_id: r?.cycle_method_id ?? '',
        combination_id: r?.combination_id ?? '',
        preference_order: r?.preference_order ?? '',
    };
});

function onMajorChange(): void {
    const methods = major.value?.methods ?? [];
    form.value.cycle_method_id = methods.length === 1 ? methods[0].id : '';
    form.value.combination_id = '';
    onMethodChange();
}

function onMethodChange(): void {
    const combinations = major.value?.combinations ?? [];

    if (!showCombination.value) {
        form.value.combination_id = '';
    } else if (combinations.length === 1) {
        form.value.combination_id = combinations[0].id;
    }
}

function submit(): void {
    const url = isEdit.value ? `/applications/${props.record!.id}` : `/cases/${props.caseUuid}/applications`;

    router.visit(url, {
        method: isEdit.value ? 'put' : 'post',
        data: { ...form.value } as never,
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
        <DialogContent class="sm:max-w-lg" data-test="application-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa nguyện vọng' : 'Thêm nguyện vọng' }}</DialogTitle>
                    <DialogDescription>Điểm được tính tự động từ điểm của thí sinh theo công thức của phương thức.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="ap-round">Đợt xét tuyển <span class="text-destructive">*</span></Label>
                    <select id="ap-round" v-model="form.round_id" :class="selectClass" data-test="ap-round">
                        <option value="" disabled>Chọn đợt</option>
                        <option v-for="r in options.rounds" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <InputError :message="errors.round_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="ap-major">Ngành <span class="text-destructive">*</span></Label>
                    <select id="ap-major" v-model="form.major_id" :class="selectClass" data-test="ap-major" @change="onMajorChange">
                        <option value="" disabled>Chọn ngành</option>
                        <option v-for="m in options.majors" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <InputError :message="errors.major_id" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="ap-method">Phương thức <span class="text-destructive">*</span></Label>
                        <select id="ap-method" v-model="form.cycle_method_id" :class="selectClass" :disabled="!major" data-test="ap-method" @change="onMethodChange">
                            <option value="" disabled>Chọn phương thức</option>
                            <option v-for="m in major?.methods ?? []" :key="m.id" :value="m.id">{{ m.label }}</option>
                        </select>
                        <InputError :message="errors.cycle_method_id" />
                    </div>
                    <div v-if="showCombination" class="grid gap-1.5">
                        <Label for="ap-combination">Tổ hợp <span v-if="needsCombination" class="text-destructive">*</span></Label>
                        <select id="ap-combination" v-model="form.combination_id" :class="selectClass" data-test="ap-combination">
                            <option value="">{{ needsCombination ? 'Chọn tổ hợp' : 'Không dùng' }}</option>
                            <option v-for="c in major?.combinations ?? []" :key="c.id" :value="c.id">{{ c.code }}</option>
                        </select>
                        <InputError :message="errors.combination_id" />
                    </div>
                </div>

                <div class="grid gap-1.5 sm:max-w-40">
                    <Label for="ap-order">Thứ tự (1–15)</Label>
                    <Input id="ap-order" v-model="form.preference_order" type="number" min="1" max="15" placeholder="Tự động" data-test="ap-order" />
                    <InputError :message="errors.preference_order" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing || !form.round_id || !form.major_id || !form.cycle_method_id" data-test="ap-submit">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm nguyện vọng' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
