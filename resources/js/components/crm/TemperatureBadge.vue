<script setup lang="ts">
import { Flame, Snowflake, Sun } from '@lucide/vue';
import { computed } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';

/** admission_cases.temperature: hot | warm | cold */
const props = defineProps<{
    value: 'hot' | 'warm' | 'cold' | string | null;
}>();

const config = {
    hot: { label: 'Nóng', tone: 'red', icon: Flame },
    warm: { label: 'Ấm', tone: 'amber', icon: Sun },
    cold: { label: 'Lạnh', tone: 'blue', icon: Snowflake },
} as const;

const item = computed(() =>
    props.value && props.value in config
        ? config[props.value as keyof typeof config]
        : null,
);
</script>

<template>
    <ToneBadge v-if="item" :tone="item.tone">
        <component :is="item.icon" class="size-3" />
        {{ item.label }}
    </ToneBadge>
</template>
