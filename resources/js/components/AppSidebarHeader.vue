<script setup lang="ts">
import { Bell } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CycleSwitcher from '@/components/crm/layout/CycleSwitcher.vue';
import GlobalSearch from '@/components/crm/layout/GlobalSearch.vue';
import HeaderUserMenu from '@/components/crm/layout/HeaderUserMenu.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumbs
                v-if="breadcrumbs.length > 0"
                class="hidden min-w-0 sm:flex"
                :breadcrumbs="breadcrumbs"
            />
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <GlobalSearch />
            <CycleSwitcher />
            <TooltipProvider :delay-duration="300">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="Thông báo"
                        >
                            <Bell class="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Thông báo (đang phát triển)</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <HeaderUserMenu />
        </div>
    </header>
</template>
