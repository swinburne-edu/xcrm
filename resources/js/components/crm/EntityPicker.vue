<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle, Search, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { LookupItem } from '@/types';

type Id = number | string;

/**
 * Async searchable picker backed by GET /lookups/{entity} (config/lookups.php).
 *
 *   <EntityPicker v-model="form.owner_id" entity="users" placeholder="Chọn người phụ trách" />
 *   <EntityPicker v-model="form.tag_ids" entity="tags" multiple />
 *   <EntityPicker v-model="form.ward_id" entity="wards" :params="{ province_id: form.province_id }" />
 */
const props = withDefaults(
    defineProps<{
        entity: string;
        multiple?: boolean;
        placeholder?: string;
        searchPlaceholder?: string;
        /** Extra query params sent to the lookup (e.g. province_id). */
        params?: Record<string, string | number | null | undefined>;
        disabled?: boolean;
        clearable?: boolean;
        /** Labels for the initial value, to avoid an extra request. */
        initialItems?: LookupItem[];
        triggerClass?: string;
        id?: string;
    }>(),
    {
        multiple: false,
        placeholder: 'Chọn…',
        searchPlaceholder: 'Tìm kiếm…',
        params: () => ({}),
        disabled: false,
        clearable: true,
        initialItems: () => [],
        triggerClass: undefined,
        id: undefined,
    },
);

const model = defineModel<Id | Id[] | null>({ default: null });

const emit = defineEmits<{
    select: [item: LookupItem | null];
}>();

const open = ref(false);
const term = ref('');
const items = ref<LookupItem[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const activeIndex = ref(0);
const known = ref(new Map<string, LookupItem>());
const searchInput = ref<HTMLInputElement | null>(null);

let controller: AbortController | null = null;

for (const item of props.initialItems) {
    known.value.set(String(item.id), item);
}

const selectedIds = computed<string[]>(() => {
    if (
        model.value === null ||
        model.value === undefined ||
        model.value === ''
    ) {
        return [];
    }

    return (Array.isArray(model.value) ? model.value : [model.value]).map(
        String,
    );
});

const selectedItems = computed(() =>
    selectedIds.value
        .map((id) => known.value.get(id))
        .filter((item): item is LookupItem => item !== undefined),
);

const triggerLabel = computed(() => {
    if (selectedIds.value.length === 0) {
        return '';
    }

    if (!props.multiple) {
        return selectedItems.value[0]?.label ?? 'Đang tải…';
    }

    const labels = selectedItems.value.map((item) => item.label);

    return labels.length <= 2
        ? labels.join(', ')
        : `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
});

function buildUrl(query: Record<string, string>): string {
    const url = new URL(`/lookups/${props.entity}`, window.location.origin);

    for (const [key, value] of Object.entries(props.params)) {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    for (const [key, value] of Object.entries(query)) {
        url.searchParams.set(key, value);
    }

    return url.toString();
}

async function request(query: Record<string, string>): Promise<LookupItem[]> {
    controller?.abort();
    controller = new AbortController();

    const response = await fetch(buildUrl(query), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        signal: controller.signal,
    });

    if (!response.ok) {
        throw new Error(
            response.status === 404
                ? 'Danh mục này chưa có dữ liệu.'
                : 'Không tải được dữ liệu. Vui lòng thử lại.',
        );
    }

    const json = (await response.json()) as { data: LookupItem[] };

    for (const item of json.data) {
        known.value.set(String(item.id), item);
    }

    return json.data;
}

/** Term the current `items` were fetched for (null = none yet). */
const resultsTerm = ref<string | null>(null);
/** Enter was pressed before results for the typed term arrived: pick the first one when they do. */
const enterPending = ref(false);
let searchSeq = 0;

async function search(): Promise<void> {
    const seq = ++searchSeq;
    const searched = term.value;
    loading.value = true;
    error.value = null;

    try {
        const results = await request({ q: searched });

        if (seq !== searchSeq) {
            return;
        }

        items.value = results;
        resultsTerm.value = searched;
        activeIndex.value = 0;

        if (enterPending.value && searched === term.value) {
            enterPending.value = false;

            if (results[0]) {
                choose(results[0]);
            }
        }
    } catch (e) {
        if ((e as Error).name !== 'AbortError' && seq === searchSeq) {
            error.value = (e as Error).message;
            items.value = [];
            enterPending.value = false;
        }
    } finally {
        // Only the latest request decides the spinner (aborted ones finish late).
        if (seq === searchSeq) {
            loading.value = false;
        }
    }
}

const debouncedSearch = useDebounceFn(search, 250);

watch(term, () => {
    if (open.value) {
        void debouncedSearch();
    }
});

watch(open, async (isOpen) => {
    if (isOpen) {
        enterPending.value = false;
        resultsTerm.value = null;
        term.value = '';
        void search();
        await nextTick();
        searchInput.value?.focus();
    }
});

// Different params (e.g. another province) = a different result set.
watch(
    () => JSON.stringify(props.params),
    () => {
        items.value = [];
    },
);

// Fetch labels for selected ids we have not seen yet.
watch(
    selectedIds,
    async (ids) => {
        const missing = ids.filter((id) => !known.value.has(id));

        if (missing.length === 0) {
            return;
        }

        try {
            await request({ ids: missing.join(',') });
        } catch {
            // Label stays "Đang tải…"; the value itself is unaffected.
        }
    },
    { immediate: true },
);

function isSelected(item: LookupItem): boolean {
    return selectedIds.value.includes(String(item.id));
}

function choose(item: LookupItem): void {
    if (props.multiple) {
        const current = Array.isArray(model.value) ? [...model.value] : [];
        const index = current.findIndex((id) => String(id) === String(item.id));

        if (index >= 0) {
            current.splice(index, 1);
        } else {
            current.push(item.id);
        }

        model.value = current;
    } else {
        model.value = item.id;
        open.value = false;
    }

    emit('select', item);
}

function clear(): void {
    model.value = props.multiple ? [] : null;
    emit('select', null);
}

function onKeydown(event: KeyboardEvent): void {
    // Results on screen are for an older term: wait for the fresh ones instead of picking a stale item.
    if (
        event.key === 'Enter' &&
        (loading.value || resultsTerm.value !== term.value)
    ) {
        event.preventDefault();
        enterPending.value = true;

        return;
    }

    if (items.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % items.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + items.value.length) % items.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const item = items.value[activeIndex.value];

        if (item) {
            choose(item);
        }
    }
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child :disabled="disabled">
            <Button
                :id="id"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                :class="
                    cn(
                        'w-full justify-between gap-2 px-3 font-normal',
                        triggerClass,
                    )
                "
                data-test="entity-picker"
            >
                <span
                    class="truncate"
                    :class="{ 'text-muted-foreground': !triggerLabel }"
                    >{{ triggerLabel || placeholder }}</span
                >
                <span class="flex shrink-0 items-center gap-1">
                    <span
                        v-if="clearable && selectedIds.length > 0 && !disabled"
                        role="button"
                        tabindex="-1"
                        aria-label="Xoá lựa chọn"
                        class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                        @click.stop.prevent="clear"
                        @pointerdown.stop
                    >
                        <X class="size-3.5" />
                    </span>
                    <ChevronsUpDown class="size-4 opacity-50" />
                </span>
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-(--reka-popover-trigger-width) min-w-64 p-0"
            align="start"
        >
            <div class="flex items-center gap-2 border-b px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="searchInput"
                    v-model="term"
                    class="h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    :placeholder="searchPlaceholder"
                    @keydown="onKeydown"
                />
                <LoaderCircle
                    v-if="loading"
                    class="size-4 shrink-0 animate-spin text-muted-foreground"
                />
            </div>
            <div class="max-h-64 overflow-y-auto p-1" role="listbox">
                <p
                    v-if="error"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ error }}
                </p>
                <p
                    v-else-if="!loading && items.length === 0"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    Không tìm thấy kết quả.
                </p>
                <button
                    v-for="(item, index) in items"
                    :key="item.id"
                    type="button"
                    role="option"
                    :aria-selected="isSelected(item)"
                    :class="
                        cn(
                            'flex w-full items-start gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none',
                            index === activeIndex &&
                                'bg-accent text-accent-foreground',
                        )
                    "
                    @mouseenter="activeIndex = index"
                    @click="choose(item)"
                >
                    <Check
                        class="mt-0.5 size-4 shrink-0"
                        :class="isSelected(item) ? 'opacity-100' : 'opacity-0'"
                    />
                    <span class="min-w-0">
                        <span class="block truncate">{{ item.label }}</span>
                        <span
                            v-if="item.description"
                            class="block truncate text-xs text-muted-foreground"
                            >{{ item.description }}</span
                        >
                    </span>
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
