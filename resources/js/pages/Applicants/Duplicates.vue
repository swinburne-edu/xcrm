<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { GitMerge } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';

type Member = {
    uuid: string;
    code: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    high_school_name: string | null;
    created_at: string | null;
    guardians: number;
    scores: number;
    cases: { cycle: string | null; stage: string | null }[];
};
type Group = { key: string; reason: string; reason_label: string; applicants: Member[] };

defineProps<{ groups: Group[] }>();
</script>

<template>
    <Head title="Có thể trùng" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Thí sinh có thể trùng</h1>
            <p class="text-sm text-muted-foreground">
                Các hồ sơ cùng họ tên và cùng trường THPT, hoặc dùng chung số điện thoại phụ huynh. Chọn hồ sơ cần giữ rồi gộp các bản còn lại vào đó.
            </p>
        </div>

        <p v-if="groups.length === 0" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="no-duplicates">Không có thí sinh nào có thể trùng.</p>

        <section v-for="g in groups" :key="g.key" class="grid gap-2 rounded-md border p-3" data-test="duplicate-group">
            <h2 class="text-sm font-medium text-muted-foreground">{{ g.reason_label }}</h2>
            <ul class="grid gap-2">
                <li v-for="(a, i) in g.applicants" :key="a.uuid" class="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                    <div>
                        <Link :href="`/applicants/${a.uuid}`" class="font-medium hover:underline">{{ a.full_name }}</Link>
                        <span class="ml-2 text-xs text-muted-foreground">{{ a.code }}</span>
                        <div class="text-xs text-muted-foreground">
                            {{ a.phone || 'Chưa có SĐT' }} · {{ a.email || 'Chưa có email' }} · {{ a.high_school_name || 'Chưa có trường' }}
                            · tạo {{ formatDate(a.created_at, '—') }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ a.cases.length }} hồ sơ<template v-if="a.cases.length"> ({{ a.cases.map((c) => `${c.cycle}: ${c.stage}`).join('; ') }})</template>
                            · {{ a.guardians }} phụ huynh · {{ a.scores }} điểm
                        </div>
                    </div>
                    <Button v-if="i > 0" as-child size="sm" variant="outline" data-test="merge-link">
                        <Link :href="`/applicants/merge/${g.applicants[0].uuid}/${a.uuid}`"><GitMerge class="size-4" />Gộp vào {{ g.applicants[0].code }}</Link>
                    </Button>
                    <span v-else class="text-xs text-muted-foreground">Bản cũ nhất</span>
                </li>
            </ul>
        </section>
    </div>
</template>
