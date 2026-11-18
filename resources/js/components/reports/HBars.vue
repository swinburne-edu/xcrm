<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from '@/lib/format';

/**
 * Horizontal bars: one row per item, the bar length relative to the biggest value.
 *   <HBars :items="[{ label: 'Facebook', value: 120 }]" />
 */
const props = defineProps<{
    items: { label: string; value: number; color?: string | null; hint?: string }[];
    empty?: string;
}>();

const palette: Record<string, string> = {
    gray: '#9ca3af',
    cyan: '#06b6d4',
    blue: '#3b82f6',
    violet: '#8b5cf6',
    amber: '#f59e0b',
    green: '#22c55e',
    red: '#ef4444',
};

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
const colorOf = (c?: string | null): string => (c && palette[c]) || '#3b82f6';
</script>

<template>
    <p v-if="items.length === 0" class="text-sm text-muted-foreground">{{ empty ?? 'Chưa có dữ liệu.' }}</p>
    <ul v-else class="grid gap-1.5" data-test="bars">
        <li v-for="(i, idx) in items" :key="idx" class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-2 text-sm">
            <span class="truncate" :title="i.label">{{ i.label }}</span>
            <span class="h-3 overflow-hidden rounded-full bg-muted">
                <span class="block h-full rounded-full" :style="{ width: `${(i.value / max) * 100}%`, backgroundColor: colorOf(i.color) }" />
            </span>
            <span class="tabular-nums text-muted-foreground">{{ formatNumber(i.value) }}<template v-if="i.hint"> · {{ i.hint }}</template></span>
        </li>
    </ul>
</template>
