<script setup lang="ts">
import { computed } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';

/**
 * Pipeline stage label. `color` is pipeline_stages.color: a tone name
 * (blue, green…) or a hex color (#2563eb).
 */
const props = withDefaults(
    defineProps<{
        name: string;
        color?: string | null;
    }>(),
    { color: null },
);

const TONES: BadgeTone[] = [
    'gray',
    'blue',
    'green',
    'amber',
    'red',
    'violet',
    'cyan',
    'pink',
];

const isHex = computed(() => /^#[0-9a-f]{6}$/i.test(props.color ?? ''));

const tone = computed<BadgeTone>(() =>
    TONES.includes(props.color as BadgeTone)
        ? (props.color as BadgeTone)
        : 'gray',
);
</script>

<template>
    <span
        v-if="isHex"
        class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset"
        :style="{
            color: color!,
            backgroundColor: `${color}1a`,
            '--tw-ring-color': `${color}40`,
        }"
    >
        <span
            class="size-1.5 rounded-full"
            :style="{ backgroundColor: color! }"
        />
        {{ name }}
    </span>
    <ToneBadge v-else :tone="tone">
        <span class="size-1.5 rounded-full bg-current opacity-70" />
        {{ name }}
    </ToneBadge>
</template>
