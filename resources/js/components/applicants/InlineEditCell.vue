<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Edit one field of an applicant right in the list (PATCH /applicants/{uuid}/field).
 *
 *   <InlineEditCell :uuid="row.uuid" field="email" :value="row.email" :can-edit="row.can_edit" kind="email" />
 *   <InlineEditCell :uuid="row.uuid" field="province_id" :value="row.province_id" :label="row.province_name" kind="entity" entity="provinces" :can-edit="row.can_edit" />
 *
 * Click the value to edit: Enter or leaving the box saves, Esc cancels. A rejected value (bad format, a phone that
 * belongs to someone else…) keeps the box open with the message. `icon` trigger keeps the content of the default slot
 * (e.g. a link) and adds a small pencil next to it. Clicks never reach the row, so the row does not open the record.
 */
const props = withDefaults(
    defineProps<{
        uuid: string;
        field: string;
        value: string | number | null;
        /** Text shown when it differs from the value (entity pickers show a name). */
        label?: string | null;
        kind?: 'text' | 'tel' | 'email' | 'number' | 'entity';
        entity?: string;
        canEdit?: boolean;
        placeholder?: string;
        trigger?: 'cell' | 'icon';
    }>(),
    { label: null, kind: 'text', entity: undefined, canEdit: true, placeholder: '—', trigger: 'cell' },
);

const editing = ref(false);
const draft = ref<string | number | null>(null);
const error = ref<string | null>(null);
const processing = ref(false);
const input = ref<{ $el?: HTMLInputElement } | null>(null);

const shown = computed(() => (props.label ?? props.value) === null || (props.label ?? props.value) === '' ? null : String(props.label ?? props.value));
const items = computed(() => (props.value === null ? [] : [{ id: props.value, label: props.label ?? String(props.value), description: null }]));

function start(): void {
    if (!props.canEdit) {
        return;
    }

    draft.value = props.value;
    error.value = null;
    editing.value = true;
    void nextTick(() => (input.value?.$el ?? (document.activeElement as HTMLInputElement | null))?.focus?.());
}

function cancel(): void {
    editing.value = false;
    error.value = null;
}

function save(value: string | number | null = draft.value): void {
    if (processing.value || !editing.value) {
        return;
    }

    const next = value === null ? '' : String(value).trim();

    if (next === (props.value === null ? '' : String(props.value))) {
        cancel();

        return;
    }

    router.patch(
        `/applicants/${props.uuid}/field`,
        { field: props.field, value: next === '' ? null : next },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onSuccess: () => cancel(),
            onError: (errors) => (error.value = errors.value ?? errors.field ?? 'Không lưu được.'),
            onFinish: () => (processing.value = false),
        },
    );
}

function pick(id: string | number | null): void {
    draft.value = id;
    save(id);
}
</script>

<template>
    <div v-if="editing" class="min-w-32" data-test="inline-editor" @click.stop @keydown.esc.stop="cancel">
        <EntityPicker
            v-if="kind === 'entity' && entity"
            :model-value="draft"
            :entity="entity"
            :initial-items="items"
            :disabled="processing"
            trigger-class="h-8"
            @update:model-value="(v) => pick(Array.isArray(v) ? (v[0] ?? null) : v)"
        />
        <Input
            v-else
            ref="input"
            v-model="draft as string"
            :type="kind === 'number' ? 'number' : kind === 'email' ? 'email' : kind === 'tel' ? 'tel' : 'text'"
            :disabled="processing"
            class="h-8"
            autocomplete="off"
            data-test="inline-input"
            @keydown.enter.prevent="save()"
            @blur="save()"
        />
        <p v-if="error" class="mt-1 max-w-64 text-xs whitespace-normal text-destructive" role="alert" data-test="inline-error">{{ error }}</p>
    </div>

    <div v-else-if="trigger === 'icon'" class="group/edit flex items-center gap-1" @click.stop>
        <slot>{{ shown ?? placeholder }}</slot>
        <button
            v-if="canEdit"
            type="button"
            class="rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover/edit:opacity-100 focus-visible:opacity-100"
            aria-label="Sửa nhanh"
            data-test="inline-trigger"
            @click.stop="start"
        >
            <Pencil class="size-3.5" />
        </button>
    </div>

    <button
        v-else-if="canEdit"
        type="button"
        :class="cn('group/edit -mx-1 flex min-h-6 w-full items-center gap-1 rounded px-1 text-left hover:bg-muted', shown === null && 'text-muted-foreground')"
        data-test="inline-trigger"
        @click.stop="start"
    >
        <span class="truncate">{{ shown ?? placeholder }}</span>
        <Pencil class="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/edit:opacity-100" />
    </button>

    <span v-else :class="shown === null ? 'text-muted-foreground' : ''">{{ shown ?? placeholder }}</span>
</template>
