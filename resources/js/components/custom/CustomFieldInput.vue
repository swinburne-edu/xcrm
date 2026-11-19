<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CustomField, CustomFieldValue } from './types';

/**
 * One input for a custom field, chosen by its type. Used by the dynamic forms (create / edit dialogs) and by the
 * inline editor on detail pages. The value is a string for most types, a boolean for "Có / Không" and a list for multiselect.
 */
const props = defineProps<{ field: CustomField; error?: string; idPrefix?: string }>();
const value = defineModel<CustomFieldValue>({ default: null });

const id = computed(() => `${props.idPrefix ?? 'cf'}-${props.field.key}`);
const list = computed(() => (Array.isArray(value.value) ? value.value : []));
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function toggle(option: string, checked: boolean): void {
    value.value = checked ? [...list.value, option] : list.value.filter((o) => o !== option);
}
</script>

<template>
    <div class="grid gap-1.5" :data-test="`custom-${field.key}`">
        <Label v-if="field.type !== 'boolean'" :for="id">{{ field.label }} <span v-if="field.required" class="text-destructive">*</span></Label>

        <textarea
            v-if="field.type === 'textarea'"
            :id="id"
            :value="(value as string) ?? ''"
            rows="3"
            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            @input="value = ($event.target as HTMLTextAreaElement).value"
        />
        <label v-else-if="field.type === 'boolean'" class="flex items-center gap-2 text-sm">
            <Checkbox :model-value="value === true" @update:model-value="(v) => (value = v === true)" />
            <span>{{ field.label }}</span>
        </label>
        <select v-else-if="field.type === 'select'" :id="id" :value="(value as string) ?? ''" :class="selectClass" @change="value = ($event.target as HTMLSelectElement).value">
            <option value="">Chưa chọn</option>
            <option v-for="o in field.options" :key="o" :value="o">{{ o }}</option>
        </select>
        <div v-else-if="field.type === 'multiselect'" class="flex flex-wrap gap-x-4 gap-y-1.5" :id="id">
            <label v-for="o in field.options" :key="o" class="flex items-center gap-2 text-sm">
                <Checkbox :model-value="list.includes(o)" @update:model-value="(v) => toggle(o, v === true)" />
                <span>{{ o }}</span>
            </label>
        </div>
        <Input
            v-else
            :id="id"
            :model-value="(value as string | number) ?? ''"
            :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : field.type === 'url' ? 'url' : 'text'"
            :step="field.type === 'number' ? 'any' : undefined"
            @update:model-value="(v) => (value = String(v))"
        />
        <InputError :message="error" />
    </div>
</template>
