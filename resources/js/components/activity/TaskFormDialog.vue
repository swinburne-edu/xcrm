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
import type { Option, TaskRow } from './types';

const props = defineProps<{
    /** null = create */
    record: TaskRow | null;
    types: Option[];
    priorities: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ title: '', type: 'other', priority: 'normal', due_at: '', assignee_id: null as number | string | null });

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = {
        title: r?.title ?? '',
        type: r?.type ?? 'other',
        priority: r?.priority ?? 'normal',
        due_at: r?.due_input ?? '',
        assignee_id: r?.assignee_id ?? null,
    };
});

function submit(): void {
    const data = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]));

    router.visit(isEdit.value ? `/tasks/${props.record!.id}` : '/tasks', {
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
        <DialogContent class="sm:max-w-lg" data-test="task-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa việc' : 'Thêm việc' }}</DialogTitle>
                    <DialogDescription>Việc được giao cho người thực hiện; mặc định là bạn.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="tk-title">Tiêu đề <span class="text-destructive">*</span></Label>
                    <Input id="tk-title" v-model="form.title" maxlength="255" data-test="task-title" />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="tk-type">Loại</Label>
                        <select id="tk-type" v-model="form.type" :class="selectClass">
                            <option v-for="o in types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tk-priority">Mức ưu tiên</Label>
                        <select id="tk-priority" v-model="form.priority" :class="selectClass">
                            <option v-for="o in priorities" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tk-due">Hạn</Label>
                        <Input id="tk-due" v-model="form.due_at" type="datetime-local" data-test="task-due" />
                        <InputError :message="errors.due_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tk-assignee">Người thực hiện</Label>
                        <EntityPicker
                            id="tk-assignee"
                            v-model="form.assignee_id"
                            entity="users"
                            placeholder="Mặc định: bạn"
                            :initial-items="record?.assignee_id ? [{ id: record.assignee_id, label: record.assignee_name ?? '', description: null }] : []"
                        />
                        <InputError :message="errors.assignee_id" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="task-save">
                        <Spinner v-if="processing" />
                        Lưu
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
