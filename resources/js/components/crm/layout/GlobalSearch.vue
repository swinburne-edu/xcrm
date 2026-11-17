<script setup lang="ts">
import { Search } from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/**
 * Global search (Ctrl+K / ⌘K). Results across applicants, cases, high schools…
 * come in a later task; for now the dialog only shows the entry point.
 */
const open = ref(false);
const query = ref('');

const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

useEventListener(
    typeof window !== 'undefined' ? window : undefined,
    'keydown',
    (event: KeyboardEvent) => {
        if (
            event.key.toLowerCase() === 'k' &&
            (event.ctrlKey || event.metaKey)
        ) {
            event.preventDefault();
            open.value = !open.value;
        }
    },
);
</script>

<template>
    <Button
        variant="outline"
        size="sm"
        class="h-8 gap-2 font-normal text-muted-foreground md:w-56 md:justify-start lg:w-72"
        data-test="global-search"
        @click="open = true"
    >
        <Search class="size-4 shrink-0" />
        <span class="hidden md:inline">Tìm thí sinh, hồ sơ, trường…</span>
        <kbd
            class="pointer-events-none ml-auto hidden h-5 items-center gap-0.5 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium md:inline-flex"
        >
            {{ isMac ? '⌘' : 'Ctrl' }} K
        </kbd>
    </Button>

    <Dialog v-model:open="open">
        <DialogContent class="gap-3 p-4 sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Tìm kiếm</DialogTitle>
                <DialogDescription>
                    Tìm nhanh thí sinh, hồ sơ tuyển sinh, trường THPT theo tên,
                    số điện thoại hoặc mã.
                </DialogDescription>
            </DialogHeader>
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    v-focus
                    class="pl-9"
                    placeholder="Nhập từ khoá…"
                />
            </div>
            <p class="py-6 text-center text-sm text-muted-foreground">
                Tính năng tìm kiếm toàn cục đang được phát triển.
            </p>
        </DialogContent>
    </Dialog>
</template>
