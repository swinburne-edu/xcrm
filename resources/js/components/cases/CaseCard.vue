<script setup lang="ts">
import { CalendarClock, Inbox } from '@lucide/vue';
import TemperatureBadge from '@/components/crm/TemperatureBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { getInitials } from '@/composables/useInitials';
import { formatDate } from './types';
import type { CaseCardData } from './types';

/** Kanban card of a case: applicant, school, major, owner, temperature, next action (red when overdue). */
defineProps<{ item: CaseCardData }>();
</script>

<template>
    <article class="grid gap-1.5 rounded-md border bg-background p-2.5 text-sm shadow-xs hover:border-primary/40" data-test="case-card">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="truncate font-medium">{{ item.applicant_name }}</div>
                <div class="truncate text-xs text-muted-foreground">{{ item.applicant_code }}</div>
            </div>
            <TemperatureBadge :value="item.temperature" />
        </div>

        <div v-if="item.high_school_name" class="truncate text-xs text-muted-foreground">{{ item.high_school_name }}</div>
        <div v-if="item.major_name" class="truncate text-xs">{{ item.major_name }}</div>

        <div v-if="item.tags.length" class="flex flex-wrap gap-1">
            <ToneBadge v-for="t in item.tags" :key="t.id" tone="gray">{{ t.name }}</ToneBadge>
        </div>

        <div class="flex items-center justify-between gap-2 text-xs">
            <span
                v-if="item.next_action_at"
                class="inline-flex items-center gap-1"
                :class="item.overdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-muted-foreground'"
                :data-test="item.overdue ? 'overdue' : 'next-action'"
            >
                <CalendarClock class="size-3.5" />{{ formatDate(item.next_action_at) }}
            </span>
            <span v-else />
            <span v-if="item.in_pool" class="inline-flex items-center gap-1 text-muted-foreground" data-test="pool-badge"><Inbox class="size-3.5" />Kho chung</span>
            <span
                v-else
                class="grid size-6 place-items-center rounded-full bg-muted text-[10px] font-medium"
                :title="item.owner_name ?? ''"
                >{{ getInitials(item.owner_name ?? '') }}</span
            >
        </div>
    </article>
</template>
