<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GraduationCap, Search, User } from '@lucide/vue';
import { useEventListener, watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { requestJson } from '@/lib/http';

/**
 * Global search (Ctrl+K / ⌘K): applicants (name, code, e-mail, phone) and high schools (name, code).
 * Server: GET /search?q= (GlobalSearchController) — respects permissions and data scope.
 */
type Hit = { id: string | number; label: string; description: string; url: string };
type Results = { applicants: Hit[]; high_schools: Hit[] };

const open = ref(false);
const query = ref('');
const results = ref<Results>({ applicants: [], high_schools: [] });
const loading = ref(false);
const failed = ref(false);
let requestId = 0;

const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

const groups = computed(() => [
    { key: 'applicants', title: 'Thí sinh', icon: User, items: results.value.applicants },
    { key: 'high_schools', title: 'Trường THPT', icon: GraduationCap, items: results.value.high_schools },
].filter((g) => g.items.length > 0));

const searchable = computed(() => query.value.trim().length >= 2);
const empty = computed(() => searchable.value && !loading.value && !failed.value && groups.value.length === 0);

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

async function search(): Promise<void> {
    const term = query.value.trim();
    const id = ++requestId;

    if (term.length < 2) {
        results.value = { applicants: [], high_schools: [] };
        loading.value = false;
        failed.value = false;

        return;
    }

    loading.value = true;
    failed.value = false;

    try {
        const data = await requestJson<Results>('GET', `/search?q=${encodeURIComponent(term)}`);

        if (id === requestId) {
            results.value = data;
        }
    } catch {
        if (id === requestId) {
            failed.value = true;
        }
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
}

watchDebounced(query, search, { debounce: 250 });

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
        results.value = { applicants: [], high_schools: [] };
        failed.value = false;
        requestId++;
    }
});

function go(hit: Hit): void {
    open.value = false;
    router.visit(hit.url);
}

function goFirst(): void {
    const first = groups.value[0]?.items[0];

    if (first) {
        go(first);
    }
}
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
        <span class="hidden md:inline">Tìm thí sinh, trường…</span>
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
                    Tìm nhanh thí sinh theo tên, mã, email, số điện thoại và trường THPT theo tên hoặc mã.
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
                    data-test="global-search-input"
                    @keydown.enter.prevent="goFirst"
                />
            </div>

            <div class="max-h-80 overflow-y-auto" data-test="global-search-results">
                <p v-if="!searchable" class="py-6 text-center text-sm text-muted-foreground">
                    Nhập ít nhất 2 ký tự để tìm.
                </p>
                <p v-else-if="failed" class="py-6 text-center text-sm text-destructive">
                    Không tìm được lúc này. Thử lại sau.
                </p>
                <p v-else-if="empty" class="py-6 text-center text-sm text-muted-foreground" data-test="global-search-empty">
                    Không có kết quả cho “{{ query.trim() }}”.
                </p>
                <p v-else-if="loading && groups.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    Đang tìm…
                </p>

                <section v-for="group in groups" :key="group.key" class="mb-2">
                    <h3 class="px-2 py-1 text-xs font-medium text-muted-foreground uppercase">{{ group.title }}</h3>
                    <ul>
                        <li v-for="hit in group.items" :key="hit.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                data-test="global-search-hit"
                                @click="go(hit)"
                            >
                                <component :is="group.icon" class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ hit.label }}</span>
                                    <span v-if="hit.description" class="block truncate text-xs text-muted-foreground">{{ hit.description }}</span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
