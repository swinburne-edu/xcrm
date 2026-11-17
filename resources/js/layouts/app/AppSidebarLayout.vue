<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();

// Pages declare breadcrumbs via defineOptions; pages rendered straight from a route send them as a prop.
const resolvedBreadcrumbs = computed<BreadcrumbItem[]>(() =>
    props.breadcrumbs.length > 0
        ? props.breadcrumbs
        : (page.props.breadcrumbs ?? []),
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="resolvedBreadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
