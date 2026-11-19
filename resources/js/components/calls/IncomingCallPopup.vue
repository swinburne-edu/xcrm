<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Phone, X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { requestJson } from '@/lib/http';

/**
 * Popup for inbound calls that are ringing for the current user (task 3.7). There is no push channel, so it asks
 * GET /calls/incoming every few seconds, only for people who may see calls, and only while the tab is visible.
 */
type Call = { id: number; number: string | null; applicant_uuid: string | null; applicant_name: string | null };

const page = usePage();
const calls = ref<Call[]>([]);
const dismissed = ref<Set<number>>(new Set());
let timer: ReturnType<typeof setInterval> | null = null;

async function poll(): Promise<void> {
    if (document.hidden) {
        return;
    }

    try {
        const result = await requestJson<{ calls: Call[] }>('GET', '/calls/incoming');
        calls.value = result.calls.filter((c) => !dismissed.value.has(c.id));
    } catch {
        // A failed poll is ignored; the next one tries again.
    }
}

function dismiss(id: number): void {
    dismissed.value.add(id);
    calls.value = calls.value.filter((c) => c.id !== id);
}

onMounted(() => {
    if ((page.props as Record<string, unknown>).telephony === true) {
        void poll();
        timer = setInterval(poll, 5000);
    }
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <div v-if="calls.length" class="fixed right-4 bottom-4 z-50 grid w-80 gap-2" data-test="incoming-calls" role="alert">
        <div v-for="c in calls" :key="c.id" class="flex items-start gap-3 rounded-lg border bg-background p-3 shadow-lg">
            <span class="mt-0.5 flex size-8 shrink-0 animate-pulse items-center justify-center rounded-full bg-green-600 text-white"><Phone class="size-4" /></span>
            <div class="min-w-0 flex-1 text-sm">
                <p class="font-semibold">Cuộc gọi đến</p>
                <p class="truncate">{{ c.applicant_name || 'Số lạ' }}</p>
                <p class="text-muted-foreground">{{ c.number }}</p>
                <Link v-if="c.applicant_uuid" :href="`/applicants/${c.applicant_uuid}`" class="mt-1 inline-block text-xs underline" @click="dismiss(c.id)">Mở hồ sơ</Link>
            </div>
            <button type="button" class="rounded p-1 text-muted-foreground hover:bg-muted" aria-label="Đóng" @click="dismiss(c.id)"><X class="size-4" /></button>
        </div>
    </div>
</template>
