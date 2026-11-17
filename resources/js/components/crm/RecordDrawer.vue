<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * Right-side panel for a quick look at a record (row / Kanban card click).
 *
 *   <RecordDrawer v-model:open="drawerOpen" :title="selected?.full_name" :href="`/applicants/${selected?.uuid}`">
 *     …details…
 *     <template #actions>…</template>
 *     <template #footer>…</template>
 *   </RecordDrawer>
 */
const props = withDefaults(
    defineProps<{
        title?: string | null;
        description?: string | null;
        /** Full page of the record ("Mở trang chi tiết"). */
        href?: string | null;
        size?: 'md' | 'lg' | 'xl';
        loading?: boolean;
    }>(),
    {
        title: null,
        description: null,
        href: null,
        size: 'lg',
        loading: false,
    },
);

const open = defineModel<boolean>('open', { default: false });

const widthClass = computed(
    () =>
        ({
            md: 'sm:max-w-md',
            lg: 'sm:max-w-xl',
            xl: 'sm:max-w-3xl',
        })[props.size],
);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            :class="cn('flex w-full flex-col gap-0 p-0', widthClass)"
            data-test="record-drawer"
        >
            <SheetHeader class="gap-1 border-b px-5 py-4 pr-12">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <SheetTitle class="truncate text-base">
                            <Skeleton v-if="loading" class="h-5 w-48" />
                            <template v-else>{{ title }}</template>
                        </SheetTitle>
                        <SheetDescription
                            v-if="description || loading"
                            class="truncate"
                        >
                            <Skeleton v-if="loading" class="mt-1 h-4 w-32" />
                            <template v-else>{{ description }}</template>
                        </SheetDescription>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <slot name="actions" />
                        <Button
                            v-if="href"
                            variant="outline"
                            size="sm"
                            class="h-8"
                            as-child
                        >
                            <Link :href="href">
                                <ExternalLink class="size-3.5" />
                                Mở trang chi tiết
                            </Link>
                        </Button>
                    </div>
                </div>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div v-if="loading" class="space-y-3">
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-4 w-1/2" />
                    <Skeleton class="h-24 w-full" />
                </div>
                <slot v-else />
            </div>

            <div
                v-if="$slots.footer"
                class="flex items-center justify-end gap-2 border-t px-5 py-3"
            >
                <slot name="footer" />
            </div>
        </SheetContent>
    </Sheet>
</template>
