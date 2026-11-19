<script setup lang="ts">
import { GraduationCap } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';

/** Bare shell of the public pages and the applicant portal: school name on top, content centred, toasts. */
defineProps<{ school: string; wide?: boolean }>();
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="border-b">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <span class="flex items-center gap-2 font-semibold">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-foreground text-background"><GraduationCap class="size-4" /></span>
                    {{ school }}
                </span>
                <slot name="actions" />
            </div>
        </header>
        <main :class="['mx-auto px-4 py-6 md:py-10', wide ? 'max-w-5xl' : 'max-w-md']">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
