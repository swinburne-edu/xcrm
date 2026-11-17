<script setup lang="ts">
import { computed } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';

/** high_schools.tier: A | B | C (potential ranking) */
const props = defineProps<{
    tier: 'A' | 'B' | 'C' | string | null;
}>();

const tones: Record<string, BadgeTone> = { A: 'green', B: 'blue', C: 'gray' };

const tone = computed<BadgeTone>(() => tones[props.tier ?? ''] ?? 'gray');
</script>

<template>
    <ToneBadge v-if="tier" :tone="tone" :title="`Hạng tiềm năng ${tier}`">
        Hạng {{ tier }}
    </ToneBadge>
</template>
