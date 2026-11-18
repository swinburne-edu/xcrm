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

export type TerritoryRow = {
    id: number;
    name: string;
    user_id: number | null;
    user_name: string | null;
    team_id: number | null;
    team_name: string | null;
    targets: Record<string, { id: number; label: string }[]>;
    provinces_count: number;
    high_schools_count: number;
    scope_label: string;
};

export type TerritoryTarget = { type: string; label: string; lookup: string };

const props = defineProps<{
    /** null = create */
    record: TerritoryRow | null;
    /** What a territory can cover (provinces; high schools once available). */
    targets: TerritoryTarget[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    name: '',
    user_id: null as number | string | null,
    team_id: null as number | string | null,
    targets: {} as Record<string, (number | string)[]>,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    form.value = {
        name: props.record?.name ?? '',
        user_id: props.record?.user_id ?? null,
        team_id: props.record?.team_id ?? null,
        targets: Object.fromEntries(
            props.targets.map((t) => [
                t.type,
                (props.record?.targets[t.type] ?? []).map((item) => item.id),
            ]),
        ),
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
            ? `/settings/territories/${props.record!.id}`
            : '/settings/territories',
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
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            data-test="territory-form"
        >
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Sửa vùng phụ trách' : 'Thêm vùng phụ trách'
                    }}</DialogTitle>
                    <DialogDescription>
                        Hồ sơ mới của tỉnh/trường trong vùng được tự giao cho
                        người và nhóm phụ trách. Mỗi tỉnh hoặc trường chỉ thuộc
                        một vùng.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="v-name"
                        >Tên vùng <span class="text-destructive">*</span></Label
                    >
                    <Input
                        id="v-name"
                        v-model="form.name"
                        placeholder="VD: Cụm Bắc Trung Bộ"
                    />
                    <InputError :message="fieldError('name')" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="v-user">Người phụ trách</Label>
                        <EntityPicker
                            id="v-user"
                            v-model="form.user_id"
                            entity="users"
                            placeholder="Chọn người"
                            :initial-items="
                                record?.user_id
                                    ? [
                                          {
                                              id: record.user_id,
                                              label: record.user_name ?? '',
                                              description: null,
                                          },
                                      ]
                                    : []
                            "
                        />
                        <InputError :message="fieldError('user_id')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="v-team">Nhóm phụ trách</Label>
                        <EntityPicker
                            id="v-team"
                            v-model="form.team_id"
                            entity="teams"
                            placeholder="Chọn nhóm"
                            :initial-items="
                                record?.team_id
                                    ? [
                                          {
                                              id: record.team_id,
                                              label: record.team_name ?? '',
                                              description: null,
                                          },
                                      ]
                                    : []
                            "
                        />
                        <InputError :message="fieldError('team_id')" />
                    </div>
                </div>

                <div
                    v-for="target in targets"
                    :key="target.type"
                    class="grid gap-1.5"
                >
                    <Label :for="`v-${target.type}`">{{ target.label }}</Label>
                    <EntityPicker
                        :id="`v-${target.type}`"
                        v-model="form.targets[target.type]"
                        :entity="target.lookup"
                        multiple
                        :placeholder="`Chọn ${target.label.toLowerCase()}`"
                        :initial-items="
                            (record?.targets[target.type] ?? []).map((i) => ({
                                id: i.id,
                                label: i.label,
                                description: null,
                            }))
                        "
                    />
                    <InputError
                        :message="fieldError(`targets.${target.type}`)"
                    />
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
