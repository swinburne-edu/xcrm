<script setup lang="ts" generic="T extends { id: string | number }">
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';

export type KanbanColumn<I> = {
    id: string | number;
    title: string;
    color?: string | null;
    /** Cards matching the filters in this column (may exceed items.length). */
    total: number;
    items: I[];
};

/**
 * Columns of cards that can be dragged between columns (docs/06-giao-dien.md "KanbanBoard").
 * The board only reports the move; the parent saves it and reloads (which resets the columns,
 * so a refused move jumps back).
 *
 *   <KanbanBoard :columns="board.columns" @move="onMove" @load-more="loadMore" @open="open">
 *     <template #card="{ item }">…</template>
 *   </KanbanBoard>
 */
const props = defineProps<{
    columns: KanbanColumn<T>[];
    /** Disable dragging (read-only users). */
    readonly?: boolean;
}>();

const emit = defineEmits<{
    move: [payload: { item: T; from: string | number; to: string | number }];
    'load-more': [columnId: string | number];
    open: [item: T];
}>();

const TONES: BadgeTone[] = ['gray', 'blue', 'green', 'amber', 'red', 'violet', 'cyan', 'pink'];
const local = ref<KanbanColumn<T>[]>([]);

watch(
    () => props.columns,
    (columns) => {
        local.value = columns.map((c) => ({ ...c, items: [...c.items] })) as KanbanColumn<T>[];
    },
    { immediate: true, deep: true },
);

function tone(color?: string | null): BadgeTone {
    return TONES.includes(color as BadgeTone) ? (color as BadgeTone) : 'gray';
}

function onChange(column: { id: string | number }, event: { added?: { element: T } }): void {
    if (!event.added) {
        return;
    }

    const item = event.added.element;
    const from = props.columns.find((c) => c.items.some((i) => i.id === item.id));

    emit('move', { item, from: from?.id ?? column.id, to: column.id });
}
</script>

<template>
    <div class="flex gap-3 overflow-x-auto pb-3" data-test="kanban">
        <section
            v-for="column in local"
            :key="column.id"
            class="flex w-72 shrink-0 flex-col rounded-lg border bg-muted/30"
            :data-test="`column-${column.id}`"
        >
            <header class="flex items-center justify-between gap-2 border-b px-3 py-2">
                <ToneBadge :tone="tone(column.color)">{{ column.title }}</ToneBadge>
                <span class="text-xs text-muted-foreground" data-test="column-count">{{ column.total }}</span>
            </header>

            <draggable
                v-model="column.items"
                group="kanban"
                item-key="id"
                :animation="150"
                :disabled="readonly"
                class="flex min-h-24 flex-1 flex-col gap-2 p-2"
                ghost-class="opacity-40"
                @change="(e: { added?: { element: T } }) => onChange(column, e)"
            >
                <template #item="{ element }">
                    <div class="cursor-grab active:cursor-grabbing" @click="emit('open', element as T)">
                        <slot name="card" :item="element as T" :column="column" />
                    </div>
                </template>
            </draggable>

            <footer v-if="column.items.length < column.total" class="border-t p-2">
                <Button variant="ghost" size="sm" class="w-full" data-test="load-more" @click="emit('load-more', column.id)">
                    Tải thêm ({{ column.total - column.items.length }})
                </Button>
            </footer>
        </section>
    </div>
</template>
