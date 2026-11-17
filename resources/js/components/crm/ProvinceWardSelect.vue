<script setup lang="ts">
import EntityPicker from '@/components/crm/EntityPicker.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { LookupItem } from '@/types';

type Id = number | string | null;

/**
 * Province → ward picker for the post-07/2025 administrative map (no district level).
 * Uses the `provinces` and `wards` lookups (registered with the catalog tables, task 1.1).
 *
 *   <ProvinceWardSelect v-model:province-id="form.province_id" v-model:ward-id="form.ward_id" />
 */
const props = withDefaults(
    defineProps<{
        disabled?: boolean;
        provinceLabel?: string;
        wardLabel?: string;
        /** Labels for the initial values (avoids extra requests on edit forms). */
        initialItems?: { province?: LookupItem; ward?: LookupItem };
        class?: string;
    }>(),
    {
        disabled: false,
        provinceLabel: 'Tỉnh/Thành phố',
        wardLabel: 'Xã/Phường',
        initialItems: () => ({}),
        class: undefined,
    },
);

const provinceId = defineModel<Id>('provinceId', { default: null });
const wardId = defineModel<Id>('wardId', { default: null });

const uid = Math.random().toString(36).slice(2, 8);

function onProvinceChange(value: unknown): void {
    const next = (value ?? null) as Id;

    if (String(next) !== String(provinceId.value)) {
        // A ward belongs to exactly one province.
        wardId.value = null;
    }

    provinceId.value = next;
}
</script>

<template>
    <div :class="cn('grid gap-3 sm:grid-cols-2', props.class)">
        <div class="grid gap-1.5">
            <Label :for="`province-${uid}`">{{ provinceLabel }}</Label>
            <EntityPicker
                :id="`province-${uid}`"
                :model-value="provinceId"
                entity="provinces"
                placeholder="Chọn tỉnh/thành phố"
                search-placeholder="Tìm tỉnh/thành phố…"
                :disabled="disabled"
                :initial-items="
                    initialItems.province ? [initialItems.province] : []
                "
                @update:model-value="onProvinceChange"
            />
        </div>
        <div class="grid gap-1.5">
            <Label :for="`ward-${uid}`">{{ wardLabel }}</Label>
            <EntityPicker
                :id="`ward-${uid}`"
                v-model="wardId"
                entity="wards"
                :params="{ province_id: provinceId }"
                :placeholder="
                    provinceId ? 'Chọn xã/phường' : 'Chọn tỉnh/thành phố trước'
                "
                search-placeholder="Tìm xã/phường…"
                :disabled="disabled || !provinceId"
                :initial-items="initialItems.ward ? [initialItems.ward] : []"
            />
        </div>
    </div>
</template>
