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

export type Option = { value: string; label: string };
export type PipelineRow = { id: number; name: string; level: string; is_default: boolean };

const props = defineProps<{ record: PipelineRow | null; levels: Option[] }>();
const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ name: '', level: 'university', is_default: false });

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    form.value = { name: props.record?.name ?? '', level: props.record?.level ?? 'university', is_default: props.record?.is_default ?? false };
});

function submit(): void {
    router.visit(isEdit.value ? `/settings/pipelines/${props.record!.id}` : '/settings/pipelines', {
        method: isEdit.value ? 'put' : 'post',
        data: form.value as never,
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
        <DialogContent class="sm:max-w-md" data-test="pipeline-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa pipeline' : 'Thêm pipeline' }}</DialogTitle>
                    <DialogDescription>Mỗi bậc có một pipeline mặc định, dùng cho hồ sơ mới.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="pl-name">Tên <span class="text-destructive">*</span></Label>
                    <Input id="pl-name" v-model="form.name" />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pl-level">Bậc</Label>
                    <select
                        id="pl-level"
                        v-model="form.level"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option v-for="o in levels" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <InputError :message="errors.level" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_default" type="checkbox" class="size-4" data-test="is-default" />
                    Pipeline mặc định của bậc này
                </label>
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
