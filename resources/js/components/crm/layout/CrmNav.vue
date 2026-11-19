<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { isActivePath, navIcon } from '@/lib/navigation';
import type { CrmNavItem } from '@/types';

const page = usePage();
const items = computed<CrmNavItem[]>(() => page.props.navigation ?? []);

const { currentUrl } = useCurrentUrl();
const { state, isMobile } = useSidebar();

/** Icon-only sidebar: groups open a flyout menu instead of an inline sub-list. */
const showFlyout = computed(
    () => state.value === 'collapsed' && !isMobile.value,
);

function isGroupActive(item: CrmNavItem): boolean {
    return item.children.some((child) =>
        isActivePath(child.url, currentUrl.value),
    );
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Single page -->
                <SidebarMenuItem v-if="item.children.length === 0">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActivePath(item.url, currentUrl)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.url ?? '#'">
                            <component :is="navIcon(item.icon)" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!-- Group, icon-only sidebar -->
                <SidebarMenuItem v-else-if="showFlyout">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="isGroupActive(item)"
                            >
                                <component :is="navIcon(item.icon)" />
                                <span>{{ item.title }}</span>
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="right"
                            align="start"
                            class="min-w-48"
                        >
                            <DropdownMenuLabel>{{
                                item.title
                            }}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="child in item.children"
                                :key="child.url"
                                as-child
                            >
                                <Link
                                    :href="child.url"
                                    class="w-full cursor-pointer"
                                    :class="{
                                        'font-medium text-foreground':
                                            isActivePath(child.url, currentUrl),
                                    }"
                                >
                                    {{ child.title }}
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>

                <!-- Group, expanded sidebar -->
                <Collapsible
                    v-else
                    as-child
                    :default-open="isGroupActive(item)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="item.title">
                                <component :is="navIcon(item.icon)" />
                                <span
                                    :class="{
                                        'font-medium': isGroupActive(item),
                                    }"
                                    >{{ item.title }}</span
                                >
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.url"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="
                                            isActivePath(child.url, currentUrl)
                                        "
                                    >
                                        <Link :href="child.url">
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
