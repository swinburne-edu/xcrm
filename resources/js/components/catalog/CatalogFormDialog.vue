<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CombinationSubjectsField from '@/components/catalog/CombinationSubjectsField.vue';
import type {
    CatalogField,
    CatalogRow,
    CatalogSchema,
    CombinationSubjectRow,
} from '@/components/catalog/types';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

/**
 * Create / edit form for one catalog row, generated from the catalog's field schema.
 */
const props = defineProps<{
    catalog: CatalogSchema;
    /** null = create */
    record: CatalogRow | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const NONE = '__none';

const data = ref<Record<string, unknown>>({});
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const isEdit = computed(() => props.record !== null);

function initialValue(field: CatalogField): unknown {
    if (props.record) {
        if (field.type === 'combination_subjects') {
            return (
                (props.record[field.key] as CombinationSubjectRow[]) ?? []
            ).map((s) => ({ ...s }));
        }

        return props.record[field.key] ?? null;
    }

    if (field.type === 'combination_subjects') {
        return [0, 1, 2].map(() => ({ subject_id: null, weight: 1 }));
    }

    return field.default ?? (field.type === 'boolean' ? false : null);
}

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    data.value = Object.fromEntries(
        props.catalog.fields.map((field) => [field.key, initialValue(field)]),
    );
});

/** Errors for a field, including nested ones (subjects.0.weight…). */
function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(
        ([errorKey]) => errorKey === key || errorKey.startsWith(`${key}.`),
    )?.[1];
}

function submit(): void {
    const url = isEdit.value
        ? `/settings/catalogs/${props.catalog.key}/${props.record!.id}`
        : `/settings/catalogs/${props.catalog.key}`;

    const payload = { ...data.value };

    for (const field of props.catalog.fields) {
        if (field.type === 'combination_subjects') {
            payload[field.key] = (
                (payload[field.key] as CombinationSubjectRow[]) ?? []
            ).map((row) => ({
                subject_id: row.subject_id,
                weight: row.weight,
            }));
        }
    }

    router.visit(url, {
        method: isEdit.value ? 'put' : 'post',
        data: payload as never,
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processing.value = true;
        },
        onError: (e) => {
            errors.value = e;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg" data-test="catalog-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEdit ? 'Sửa' : 'Thêm' }} —
                        {{ catalog.title.toLowerCase() }}
                    </DialogTitle>
                    <DialogDescription v-if="catalog.description">
                        {{ catalog.description }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-for="field in catalog.fields"
                    :key="field.key"
                    class="grid gap-1.5"
                >
                    <!-- Boolean: checkbox with inline label -->
                    <div
                        v-if="field.type === 'boolean'"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`f-${field.key}`"
                            :model-value="Boolean(data[field.key])"
                            @update:model-value="
                                (value) => (data[field.key] = value === true)
                            "
                        />
                        <Label :for="`f-${field.key}`" class="font-normal">{{
                            field.label
                        }}</Label>
                    </div>

                    <template v-else>
                        <Label :for="`f-${field.key}`">
                            {{ field.label }}
                            <span v-if="field.required" class="text-destructive"
                                >*</span
                            >
                        </Label>

                        <Input
                            v-if="field.type === 'text'"
                            :id="`f-${field.key}`"
                            :model-value="(data[field.key] as string) ?? ''"
                            :maxlength="field.maxlength"
                            :placeholder="field.placeholder"
                            @update:model-value="
                                (value) => (data[field.key] = value)
                            "
                        />

                        <Input
                            v-else-if="field.type === 'number'"
                            :id="`f-${field.key}`"
                            type="number"
                            :step="field.step ?? 1"
                            :model-value="(data[field.key] as number) ?? ''"
                            @update:model-value="
                                (value) => (data[field.key] = value)
                            "
                        />

                        <Select
                            v-else-if="field.type === 'select'"
                            :model-value="(data[field.key] as string) ?? NONE"
                            @update:model-value="
                                (value) =>
                                    (data[field.key] =
                                        value === NONE ? null : value)
                            "
                        >
                            <SelectTrigger
                                :id="`f-${field.key}`"
                                class="w-full"
                            >
                                <SelectValue placeholder="Chọn…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-if="!field.required" :value="NONE"
                                    >— Không chọn —</SelectItem
                                >
                                <SelectItem
                                    v-for="option in field.options ?? []"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ option.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <EntityPicker
                            v-else-if="field.type === 'entity'"
                            :id="`f-${field.key}`"
                            :model-value="
                                (data[field.key] as number | null) ?? null
                            "
                            :entity="field.entity!"
                            :placeholder="`Chọn ${field.label.toLowerCase()}`"
                            :initial-items="
                                record && field.labelKey && record[field.key]
                                    ? [
                                          {
                                              id: record[field.key] as number,
                                              label: String(
                                                  record[field.labelKey] ?? '',
                                              ),
                                              description: null,
                                          },
                                      ]
                                    : []
                            "
                            @update:model-value="
                                (value) => (data[field.key] = value)
                            "
                        />

                        <CombinationSubjectsField
                            v-else-if="field.type === 'combination_subjects'"
                            v-model="data[field.key] as CombinationSubjectRow[]"
                        />
                    </template>

                    <p v-if="field.help" class="text-xs text-muted-foreground">
                        {{ field.help }}
                    </p>
                    <InputError :message="fieldError(field.key)" />
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
