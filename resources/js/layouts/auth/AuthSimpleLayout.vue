<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { GraduationCap } from '@lucide/vue';
import { home } from '@/routes';

/**
 * Shell of every sign-in page (login, forgot password, two-factor…): a quiet monochrome brand panel on the left
 * (hidden on phones) and the form on the right. Pages pass `title` and `description` through defineOptions({ layout }).
 */
defineProps<{
    title?: string;
    description?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Brand panel -->
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-neutral-950 p-12 text-neutral-50 lg:flex">
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(white 1px, transparent 1px), linear-gradient(90deg, white 1px, transparent 1px); background-size: 48px 48px"
            ></div>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-neutral-950 via-transparent to-transparent"></div>

            <Link :href="home()" class="relative flex items-center gap-2.5 font-semibold">
                <span class="flex size-9 items-center justify-center rounded-lg bg-white text-neutral-950">
                    <GraduationCap class="size-5" />
                </span>
                <span class="text-lg tracking-tight">xcrm</span>
            </Link>

            <div class="relative max-w-md">
                <h2 class="text-4xl leading-[1.15] font-semibold tracking-tight xl:text-5xl">Quản lý tuyển sinh gọn gàng, rõ ràng.</h2>
                <p class="mt-5 text-base leading-relaxed text-neutral-400">
                    Từ thí sinh tiềm năng đến xác nhận nhập học, mọi thứ ở một nơi, dành cho đội ngũ tuyển sinh của nhà trường.
                </p>
            </div>

            <p class="relative text-xs text-neutral-500">© {{ year }} xcrm</p>
        </aside>

        <!-- Form panel -->
        <main class="flex flex-col items-center justify-center bg-background p-6 md:p-10">
            <div class="w-full max-w-sm">
                <Link :href="home()" class="mb-10 flex items-center gap-2.5 font-semibold lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-foreground text-background">
                        <GraduationCap class="size-5" />
                    </span>
                    <span class="text-lg tracking-tight">xcrm</span>
                </Link>

                <div class="mb-8 space-y-1.5">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
