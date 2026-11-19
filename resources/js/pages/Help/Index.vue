<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Info, Lightbulb, Printer, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { commonSections, guides } from './guides';

/**
 * Hướng dẫn sử dụng theo vai trò (/help). Nội dung nằm ở ./guides.ts; ở đây chỉ hiển thị.
 * Mở sẵn bản hướng dẫn của vai trò đầu tiên của người dùng (nếu có).
 */
const props = defineProps<{ myRoles: string[] }>();

const firstOwn = guides.find((g) => props.myRoles.includes(g.code))?.code;
const selected = ref<string>(firstOwn ?? guides[0].code);
const guide = computed(() => guides.find((g) => g.code === selected.value) ?? guides[0]);

function isMine(code: string): boolean {
    return props.myRoles.includes(code);
}

function printGuide(): void {
    window.print();
}

function scrollTo(id: string): void {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head title="Hướng dẫn sử dụng" />

    <div class="flex flex-col gap-4 p-4 md:p-6" data-test="help-page">
        <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-xl font-semibold">Hướng dẫn sử dụng</h1>
                <p class="text-sm text-muted-foreground">Chọn vai trò để xem hướng dẫn từng bước cho công việc của vai trò đó.</p>
            </div>
            <Button variant="outline" size="sm" @click="printGuide"><Printer class="size-4" />In hướng dẫn</Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <!-- role picker -->
            <nav class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible print:hidden" aria-label="Chọn vai trò" data-test="help-roles">
                <button
                    v-for="g in guides"
                    :key="g.code"
                    type="button"
                    class="flex shrink-0 items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm transition-colors"
                    :class="selected === g.code ? 'border-primary bg-primary/5 font-medium' : 'hover:bg-muted'"
                    :aria-current="selected === g.code ? 'true' : undefined"
                    :data-test="`help-role-${g.code}`"
                    @click="selected = g.code"
                >
                    <span>{{ g.label }}</span>
                    <span v-if="isMine(g.code)" class="rounded bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary">Của bạn</span>
                </button>
            </nav>

            <main class="min-w-0" :data-test="`help-guide-${guide.code}`">
                <header class="rounded-lg border p-4">
                    <h2 class="text-lg font-semibold">{{ guide.label }}</h2>
                    <p class="mt-1 text-sm">{{ guide.summary }}</p>

                    <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Bạn thấy dữ liệu nào</dt>
                            <dd class="mt-0.5">{{ guide.scope }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Menu dùng nhiều nhất</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                <span v-for="m in guide.menus" :key="m" class="rounded-full bg-muted px-2 py-0.5 text-xs">{{ m }}</span>
                            </dd>
                        </div>
                    </dl>

                    <div v-if="guide.limits.length" class="mt-3 flex gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p class="font-medium">Vai trò này không làm được</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                <li v-for="l in guide.limits" :key="l">{{ l }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 print:hidden">
                        <button
                            v-for="s in guide.sections"
                            :key="s.id"
                            type="button"
                            class="rounded-md border px-2.5 py-1 text-xs hover:bg-muted"
                            @click="scrollTo(s.id)"
                        >
                            {{ s.title }}
                        </button>
                    </div>
                </header>

                <section v-for="(s, i) in guide.sections" :id="s.id" :key="s.id" class="mt-4 scroll-mt-20 rounded-lg border p-4" data-test="help-section">
                    <h3 class="flex items-center gap-2 font-semibold">
                        <span class="flex size-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground">{{ i + 1 }}</span>
                        {{ s.title }}
                    </h3>
                    <ol class="mt-3 list-decimal space-y-1.5 pl-6 text-sm marker:text-muted-foreground">
                        <li v-for="step in s.steps" :key="step">{{ step }}</li>
                    </ol>
                    <div v-for="t in s.tips ?? []" :key="t" class="mt-3 flex gap-2 rounded-md bg-blue-50 p-2.5 text-sm text-blue-900 dark:bg-blue-500/10 dark:text-blue-200">
                        <Lightbulb class="mt-0.5 size-4 shrink-0" />
                        <p>{{ t }}</p>
                    </div>
                </section>

                <section class="mt-6">
                    <h2 class="flex items-center gap-2 text-lg font-semibold"><Info class="size-5" />Dành cho mọi vai trò</h2>
                    <div v-for="s in commonSections" :id="s.id" :key="s.id" class="mt-3 rounded-lg border p-4" data-test="help-common">
                        <h3 class="font-semibold">{{ s.title }}</h3>
                        <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm">
                            <li v-for="step in s.steps" :key="step">{{ step }}</li>
                        </ul>
                        <div v-for="t in s.tips ?? []" :key="t" class="mt-3 flex gap-2 rounded-md bg-blue-50 p-2.5 text-sm text-blue-900 dark:bg-blue-500/10 dark:text-blue-200">
                            <Lightbulb class="mt-0.5 size-4 shrink-0" />
                            <p>{{ t }}</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
