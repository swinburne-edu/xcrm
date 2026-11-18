<script setup lang="ts">
import { computed } from 'vue';

/**
 * Column chart of one value per day (the last 30 days of new applicants).
 */
const props = defineProps<{ points: { date: string; count: number }[] }>();

const W = 600;
const H = 140;
const PAD = 18;

const max = computed(() => Math.max(1, ...props.points.map((p) => p.count)));
const step = computed(() => (W - PAD * 2) / Math.max(1, props.points.length));
const bars = computed(() =>
    props.points.map((p, i) => {
        const h = (p.count / max.value) * (H - PAD * 2);

        return { x: PAD + i * step.value + 1, y: H - PAD - h, w: Math.max(2, step.value - 2), h, p };
    }),
);
const label = (date: string): string => date.slice(8, 10) + '/' + date.slice(5, 7);
</script>

<template>
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Thí sinh mới theo ngày" data-test="daily-chart">
        <line :x1="PAD" :x2="W - PAD" :y1="H - PAD" :y2="H - PAD" stroke="currentColor" class="text-border" />
        <rect v-for="b in bars" :key="b.p.date" :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="2" class="fill-primary/80">
            <title>{{ label(b.p.date) }}: {{ b.p.count }}</title>
        </rect>
        <text :x="PAD" :y="H - 4" class="fill-muted-foreground text-[10px]">{{ points[0] ? label(points[0].date) : '' }}</text>
        <text :x="W - PAD" :y="H - 4" text-anchor="end" class="fill-muted-foreground text-[10px]">{{ points.length ? label(points[points.length - 1].date) : '' }}</text>
        <text :x="W - PAD" :y="10" text-anchor="end" class="fill-muted-foreground text-[10px]">tối đa {{ max }}/ngày</text>
    </svg>
</template>
