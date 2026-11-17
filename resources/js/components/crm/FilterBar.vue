<script setup lang="ts">
import { CirclePlus, Search, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import type { ListQuery } from '@/composables/useListQuery';
import { formatDate } from '@/lib/format';
import type { DateRangeValue, FilterDefinition, FilterValue } from '@/types';

/**
 * Filter controls bound to a useListQuery() instance.
 *
 *   <FilterBar :query="query" :filters="[
 *     { key: 'q', label: 'Tìm kiếm', type: 'text', placeholder: 'Tên, SĐT…' },
 *     { key: 'status', label: 'Trạng thái', type: 'select', multiple: true, options: [...] },
 *     { key: 'created_at', label: 'Ngày tạo', type: 'date' },
 *     { key: 'owner_id', label: 'Người phụ trách', type: 'entity', entity: 'users' },
 *   ]" />
 *
 * Text filters render as search boxes; the others as compact chips.
 * (Custom fields plug in here later — task 2.5.)
 */
const props = defineProps<{
    query: ListQuery;
    filters: FilterDefinition[];
}>();

const textFilters = computed(() =>
    props.filters.filter((f) => f.type === 'text'),
);
const chipFilters = computed(() =>
    props.filters.filter((f) => f.type !== 'text'),
);

function current(key: string): FilterValue | undefined {
    return props.query.filters.value[key];
}

// --- Text: local input, debounced to the server ---
const textValues = ref<Record<string, string>>({});

watch(
    () => props.query.filters.value,
    () => {
        for (const f of textFilters.value) {
            const value = current(f.key);
            textValues.value[f.key] = typeof value === 'string' ? value : '';
        }
    },
    { immediate: true, deep: true },
);

const pushText = useDebounceFn((key: string) => {
    const value = (textValues.value[key] ?? '').trim();

    if (value !== (current(key) ?? '')) {
        props.query.setFilter(key, value || null);
    }
}, 350);

// --- Select / boolean ---
function selectedValues(key: string): string[] {
    const value = current(key);

    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'boolean') {
        return [value ? '1' : '0'];
    }

    return typeof value === 'string' && value !== '' ? value.split(',') : [];
}

function toggleOption(filter: FilterDefinition, optionValue: string): void {
    const selected = selectedValues(filter.key);
    const multiple = filter.type === 'select' && filter.multiple;

    if (selected.includes(optionValue)) {
        const rest = selected.filter((v) => v !== optionValue);
        props.query.setFilter(filter.key, multiple ? rest : null);

        return;
    }

    props.query.setFilter(
        filter.key,
        multiple ? [...selected, optionValue] : optionValue,
    );
}

function optionsFor(
    filter: FilterDefinition,
): { value: string; label: string }[] {
    if (filter.type === 'select') {
        return filter.options;
    }

    if (filter.type === 'boolean') {
        return [
            { value: '1', label: filter.trueLabel ?? 'Có' },
            { value: '0', label: filter.falseLabel ?? 'Không' },
        ];
    }

    return [];
}

// --- Date range: edited locally, applied on "Áp dụng" ---
const dateDrafts = ref<Record<string, DateRangeValue>>({});

function openDate(key: string, isOpen: boolean): void {
    if (isOpen) {
        const value = current(key);
        dateDrafts.value[key] =
            value && typeof value === 'object' && !Array.isArray(value)
                ? { ...(value as DateRangeValue) }
                : {};
    }
}

function applyDate(key: string): void {
    const draft = dateDrafts.value[key] ?? {};
    props.query.setFilter(
        key,
        draft.from || draft.to
            ? {
                  ...(draft.from ? { from: draft.from } : {}),
                  ...(draft.to ? { to: draft.to } : {}),
              }
            : null,
    );
}

// --- Chip summary text ---
function summary(filter: FilterDefinition): string | null {
    const value = current(filter.key);

    if (value === undefined || value === null || value === '') {
        return null;
    }

    if (filter.type === 'date') {
        const range = value as DateRangeValue;

        if (range.from && range.to) {
            return `${formatDate(range.from)} – ${formatDate(range.to)}`;
        }

        return range.from
            ? `từ ${formatDate(range.from)}`
            : `đến ${formatDate(range.to)}`;
    }

    if (filter.type === 'select' || filter.type === 'boolean') {
        const options = optionsFor(filter);
        const labels = selectedValues(filter.key).map(
            (v) => options.find((o) => o.value === v)?.label ?? v,
        );

        return labels.length <= 2
            ? labels.join(', ')
            : `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
    }

    return null;
}

function entityValue(
    key: string,
): string | number | (string | number)[] | null {
    const value = current(key);

    if (Array.isArray(value)) {
        return value;
    }

    return typeof value === 'string' && value !== '' ? value.split(',') : null;
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2" data-test="filter-bar">
        <div
            v-for="filter in textFilters"
            :key="filter.key"
            class="relative w-full sm:w-64"
        >
            <Search
                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="textValues[filter.key]"
                class="h-8 pl-8"
                :placeholder="
                    filter.type === 'text'
                        ? (filter.placeholder ?? filter.label)
                        : filter.label
                "
                :aria-label="filter.label"
                @update:model-value="pushText(filter.key)"
                @keydown.enter="pushText(filter.key)"
            />
        </div>

        <template v-for="filter in chipFilters" :key="filter.key">
            <!-- Entity: inline picker -->
            <div v-if="filter.type === 'entity'" class="w-full sm:w-56">
                <EntityPicker
                    :model-value="entityValue(filter.key)"
                    :entity="filter.entity"
                    :params="filter.params"
                    multiple
                    :placeholder="filter.label"
                    trigger-class="h-8 border-dashed"
                    @update:model-value="
                        (value) =>
                            query.setFilter(
                                filter.key,
                                Array.isArray(value)
                                    ? value.map(String)
                                    : value === null
                                      ? null
                                      : String(value),
                            )
                    "
                />
            </div>

            <!-- Select / boolean / date: chip with popover -->
            <Popover
                v-else
                @update:open="
                    (isOpen) =>
                        filter.type === 'date' && openDate(filter.key, isOpen)
                "
            >
                <PopoverTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5 border-dashed font-normal"
                        :data-test="`filter-${filter.key}`"
                    >
                        <CirclePlus class="size-4" />
                        {{ filter.label }}
                        <template v-if="summary(filter)">
                            <Separator
                                orientation="vertical"
                                class="mx-0.5 h-4"
                            />
                            <Badge
                                variant="secondary"
                                class="max-w-48 truncate rounded-sm px-1.5 font-normal"
                                >{{ summary(filter) }}</Badge
                            >
                        </template>
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    v-if="filter.type === 'date'"
                    class="w-64 p-3"
                    align="start"
                >
                    <div class="grid gap-3">
                        <div class="grid gap-1.5">
                            <Label :for="`${filter.key}-from`">Từ ngày</Label>
                            <Input
                                :id="`${filter.key}-from`"
                                v-model="dateDrafts[filter.key]!.from"
                                type="date"
                                class="h-8"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`${filter.key}-to`">Đến ngày</Label>
                            <Input
                                :id="`${filter.key}-to`"
                                v-model="dateDrafts[filter.key]!.to"
                                type="date"
                                class="h-8"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="
                                    dateDrafts[filter.key] = {};
                                    query.setFilter(filter.key, null);
                                "
                                >Xoá</Button
                            >
                            <Button size="sm" @click="applyDate(filter.key)"
                                >Áp dụng</Button
                            >
                        </div>
                    </div>
                </PopoverContent>

                <PopoverContent v-else class="w-56 p-1" align="start">
                    <button
                        v-for="option in optionsFor(filter)"
                        :key="option.value"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="toggleOption(filter, option.value)"
                    >
                        <Checkbox
                            :model-value="
                                selectedValues(filter.key).includes(
                                    option.value,
                                )
                            "
                            class="pointer-events-none"
                            tabindex="-1"
                        />
                        <span class="truncate">{{ option.label }}</span>
                    </button>
                    <template v-if="selectedValues(filter.key).length > 0">
                        <Separator class="my-1" />
                        <button
                            type="button"
                            class="w-full rounded-sm px-2 py-1.5 text-center text-sm hover:bg-accent"
                            @click="query.setFilter(filter.key, null)"
                        >
                            Bỏ lọc
                        </button>
                    </template>
                </PopoverContent>
            </Popover>
        </template>

        <Button
            v-if="query.activeFilterCount.value > 0"
            variant="ghost"
            size="sm"
            class="h-8 px-2"
            data-test="reset-filters"
            @click="query.resetFilters()"
        >
            <X class="size-4" />
            Xoá lọc
        </Button>
    </div>
</template>
