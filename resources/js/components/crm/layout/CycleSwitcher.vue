<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarRange, Check, ChevronsUpDown, Settings2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Admission cycle selector. Every CRM screen filters by the selected cycle.
 * The server shares `cycles` / `currentCycle` from task 1.5; until then the list is empty.
 */
const page = usePage();
const cycles = computed(() => page.props.cycles ?? []);
const current = computed(() => page.props.currentCycle ?? null);
const label = computed(() => current.value?.name ?? 'Chưa có mùa tuyển sinh');
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="h-8 max-w-56 gap-2 font-normal"
                data-test="cycle-switcher"
            >
                <CalendarRange class="size-4 shrink-0 text-muted-foreground" />
                <span
                    class="hidden truncate sm:inline"
                    :class="{ 'text-muted-foreground': !current }"
                    >{{ label }}</span
                >
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-64">
            <DropdownMenuLabel>Mùa tuyển sinh</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <template v-if="cycles.length > 0">
                <DropdownMenuItem
                    v-for="cycle in cycles"
                    :key="cycle.id"
                    disabled
                >
                    <span class="truncate">{{ cycle.name }}</span>
                    <Check
                        v-if="current?.id === cycle.id"
                        class="ml-auto size-4"
                    />
                </DropdownMenuItem>
            </template>
            <p v-else class="px-2 py-3 text-sm text-muted-foreground">
                Chưa có mùa tuyển sinh nào. Tạo mùa đầu tiên trong phần Cài đặt.
            </p>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link href="/settings/cycles" class="w-full cursor-pointer">
                    <Settings2 class="size-4" />
                    Quản lý mùa tuyển sinh
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
