<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Construction } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const props = defineProps<{
    title: string;
    phase: string | null;
}>();

const phaseLabel = computed(() => {
    switch (props.phase) {
        case 'P1':
            return 'Dự kiến ở giai đoạn 2';
        case 'P2':
            return 'Dự kiến ở giai đoạn 3';
        default:
            return 'Thuộc bản MVP';
    }
});
</script>

<template>
    <Head :title="title" />

    <div
        class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <Construction class="size-7" />
        </div>
        <div class="space-y-1.5">
            <h1 class="text-xl font-semibold" data-test="page-title">
                {{ title }}
            </h1>
            <p class="max-w-md text-sm text-muted-foreground">
                Màn hình này đang được phát triển và sẽ sớm có mặt.
            </p>
        </div>
        <Badge variant="secondary">{{ phaseLabel }}</Badge>
        <Button variant="outline" size="sm" as-child>
            <Link :href="dashboard()">Về trang Tổng quan</Link>
        </Button>
    </div>
</template>
