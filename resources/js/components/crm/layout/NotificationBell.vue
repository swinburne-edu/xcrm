<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Bell, Clock, Gauge } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDateTime } from '@/lib/format';
import { requestJson } from '@/lib/http';

/**
 * Bell of the header: unread count + the latest notifications of the logged-in user (database notifications:
 * quota warnings, SLA alerts, recalled cases). Polls once a minute while the tab is visible.
 */
type Item = { id: string; kind: string; level: string | null; title: string; body: string; url: string | null; read: boolean; created_at: string | null };

const unread = ref(0);
const items = ref<Item[]>([]);
const open = ref(false);
const failed = ref(false);

async function load(): Promise<void> {
    try {
        const data = await requestJson<{ unread: number; items: Item[] }>('GET', '/notifications');
        unread.value = data.unread;
        items.value = data.items;
        failed.value = false;
    } catch {
        failed.value = true;
    }
}

async function openItem(item: Item): Promise<void> {
    open.value = false;

    if (!item.read) {
        item.read = true;
        unread.value = Math.max(0, unread.value - 1);
        void requestJson('POST', `/notifications/${item.id}/read`).catch(() => undefined);
    }

    if (item.url) {
        router.visit(item.url);
    }
}

async function readAll(): Promise<void> {
    items.value.forEach((i) => (i.read = true));
    unread.value = 0;
    await requestJson('POST', '/notifications/read-all').catch(() => undefined);
}

onMounted(load);
useIntervalFn(() => {
    if (document.visibilityState === 'visible') {
        void load();
    }
}, 60_000);
</script>

<template>
    <Popover v-model:open="open" @update:open="(v: boolean) => v && load()">
        <PopoverTrigger as-child>
            <Button variant="ghost" size="icon" class="relative size-8" aria-label="Thông báo" data-test="notification-bell">
                <Bell class="size-4" />
                <span
                    v-if="unread > 0"
                    class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-4 font-medium text-white"
                    data-test="notification-count"
                >{{ unread > 99 ? '99+' : unread }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent align="end" class="w-80 p-0" data-test="notification-panel">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <span class="text-sm font-medium">Thông báo</span>
                <Button v-if="unread > 0" variant="link" size="sm" class="h-auto p-0 text-xs" data-test="notification-read-all" @click="readAll">Đánh dấu đã đọc hết</Button>
            </div>
            <p v-if="failed" class="p-4 text-center text-sm text-muted-foreground">Không tải được thông báo.</p>
            <p v-else-if="items.length === 0" class="p-6 text-center text-sm text-muted-foreground" data-test="notification-empty">Chưa có thông báo nào.</p>
            <ul v-else class="max-h-96 divide-y overflow-y-auto">
                <li v-for="n in items" :key="n.id">
                    <button type="button" class="flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-accent" :class="n.read ? '' : 'bg-accent/40'" data-test="notification-item" @click="openItem(n)">
                        <Gauge v-if="n.kind === 'quota'" class="mt-0.5 size-4 shrink-0" :class="n.level === 'over' ? 'text-red-600' : 'text-amber-600'" />
                        <Clock v-else-if="n.kind === 'sla'" class="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <AlertTriangle v-else class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span class="min-w-0">
                            <span class="block text-sm" :class="n.read ? '' : 'font-medium'">{{ n.title }}</span>
                            <span class="line-clamp-2 block text-xs text-muted-foreground">{{ n.body }}</span>
                            <span class="block text-[11px] text-muted-foreground">{{ formatDateTime(n.created_at) }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>
