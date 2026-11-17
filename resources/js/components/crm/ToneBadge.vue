<script setup lang="ts">
import { cn } from '@/lib/utils';

export type BadgeTone =
    | 'gray'
    | 'blue'
    | 'green'
    | 'amber'
    | 'red'
    | 'violet'
    | 'cyan'
    | 'pink';

/** Small colored label; base of StageBadge / TemperatureBadge / TierBadge. */
const props = withDefaults(
    defineProps<{
        tone?: BadgeTone;
        class?: string;
    }>(),
    { tone: 'gray', class: undefined },
);

const tones: Record<BadgeTone, string> = {
    gray: 'bg-neutral-100 text-neutral-700 ring-neutral-500/20 dark:bg-neutral-800 dark:text-neutral-300',
    blue: 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/15 dark:text-blue-300',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300',
    amber: 'bg-amber-50 text-amber-800 ring-amber-600/25 dark:bg-amber-500/15 dark:text-amber-300',
    red: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300',
    violet: 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/15 dark:text-violet-300',
    cyan: 'bg-cyan-50 text-cyan-800 ring-cyan-600/20 dark:bg-cyan-500/15 dark:text-cyan-300',
    pink: 'bg-pink-50 text-pink-700 ring-pink-600/20 dark:bg-pink-500/15 dark:text-pink-300',
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                tones[tone],
                props.class,
            )
        "
    >
        <slot />
    </span>
</template>
