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

export type TeamRow = {
    id: number;
    name: string;
    leader_id: number | null;
    leader_name: string | null;
    members_count: number;
    member_ids: number[];
    members: { id: number; label: string }[];
};

const props = defineProps<{ record: TeamRow | null }>();
const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    leader_id: null as number | string | null,
    member_ids: [] as (number | string)[],
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    form.value = {
        name: props.record?.name ?? '',
        leader_id: props.record?.leader_id ?? null,
        member_ids: [...(props.record?.member_ids ?? [])],
    };
});

function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(
        ([k]) => k === key || k.startsWith(`${key}.`),
    )?.[1];
}

function submit(): void {
    router.visit(
        isEdit.value
            ? `/settings/teams/${props.record!.id}`
            : '/settings/teams',
        {
            method: isEdit.value ? 'put' : 'post',
            data: { ...form.value } as never,
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
        <DialogContent class="sm:max-w-lg" data-test="team-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa nhóm' : 'Thêm nhóm'
                    }}</DialogTitle>
                    <DialogDescription>
                        Người dùng có phạm vi "Theo nhóm" thấy hồ sơ của các
                        nhóm mình thuộc về.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="t-name"
                        >Tên nhóm <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="t-name"
                        v-model="form.name"
                        placeholder="VD: Nhóm Bắc Trung Bộ"
                    />
                    <InputError :message="fieldError('name')" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="t-leader">Trưởng nhóm</Label>
                    <EntityPicker
                        id="t-leader"
                        v-model="form.leader_id"
                        entity="users"
                        placeholder="Chọn trưởng nhóm"
                        :initial-items="
                            record?.leader_id
                                ? [
                                      {
                                          id: record.leader_id,
                                          label: record.leader_name ?? '',
                                          description: null,
                                      },
                                  ]
                                : []
                        "
                    />
                    <InputError :message="fieldError('leader_id')" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="t-members">Thành viên</Label>
                    <EntityPicker
                        id="t-members"
                        v-model="form.member_ids"
                        entity="users"
                        multiple
                        placeholder="Chọn thành viên"
                        :initial-items="
                            (record?.members ?? []).map((m) => ({
                                id: m.id,
                                label: m.label,
                                description: null,
                            }))
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        Trưởng nhóm tự được tính là thành viên.
                    </p>
                    <InputError :message="fieldError('member_ids')" />
                </div>

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
