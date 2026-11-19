<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import CustomFieldInput from './CustomFieldInput.vue';
import { blankValues, displayValue } from './types';
import type { CustomPanel, CustomValues } from './types';

/**
 * Custom fields of a record on its detail page / drawer, with an inline editor. Saves to PUT /custom-data/{object}/{id};
 * the record's own update permission decides whether the edit button shows. Emits "saved" so a JSON-driven drawer can reload.
 */
const props = defineProps<{ panel: CustomPanel; canEdit: boolean; title?: string }>();
const emit = defineEmits<{ saved: [] }>();

const editing = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const values = ref<CustomValues>({});

watch(() => props.panel, () => (values.value = blankValues(props.panel.fields)), { immediate: true });

function start(): void {
    values.value = blankValues(props.panel.fields);
    errors.value = {};
    editing.value = true;
}

function save(): void {
    router.put(
        `/custom-data/${props.panel.object}/${props.panel.id}`,
        { custom: Object.fromEntries(Object.entries(values.value).map(([k, v]) => [k, v === '' ? null : v])) } as never,
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onError: (e) => (errors.value = Object.fromEntries(Object.entries(e).map(([k, m]) => [k.replace(/^custom\./, ''), m]))),
            onSuccess: () => {
                editing.value = false;
                emit('saved');
            },
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <section v-if="panel.fields.length" class="grid gap-3 rounded-lg border p-4 text-sm" data-test="custom-fields">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-medium">{{ title ?? 'Trường tuỳ chỉnh' }}</h2>
            <Button v-if="canEdit && !editing" variant="ghost" size="sm" data-test="edit-custom" @click="start"><Pencil class="size-3.5" />Sửa</Button>
        </div>

        <dl v-if="!editing" class="grid gap-x-8 gap-y-2 sm:grid-cols-2">
            <div v-for="f in panel.fields" :key="f.key" :data-test="`custom-view-${f.key}`">
                <dt class="text-muted-foreground">{{ f.label }}</dt>
                <dd class="whitespace-pre-line break-words">
                    <a v-if="f.type === 'url' && f.value" :href="String(f.value)" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ f.value }}</a>
                    <template v-else>{{ displayValue(f) }}</template>
                </dd>
            </div>
        </dl>

        <form v-else class="grid gap-3" @submit.prevent="save">
            <div class="grid gap-3 sm:grid-cols-2">
                <CustomFieldInput v-for="f in panel.fields" :key="f.key" v-model="values[f.key]" :field="f" :error="errors[f.key]" id-prefix="cfe" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="processing" data-test="save-custom"><Spinner v-if="processing" />Lưu</Button>
                <Button type="button" variant="outline" size="sm" @click="editing = false">Huỷ</Button>
            </div>
        </form>
    </section>
</template>
