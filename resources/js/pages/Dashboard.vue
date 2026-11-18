<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DailyChart from '@/components/reports/DailyChart.vue';
import HBars from '@/components/reports/HBars.vue';
import { formatDateTime, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';

type Row = { key: number | null; label: string; total: number; enrolled: number; rate: number };
type Data = {
    kpis: { new_this_week: number; total: number; open: number; admitted: number; enrolled: number; target_enrolled: number | null; lost: number };
    funnel: { id: number; name: string; color: string | null; type: string; count: number }[];
    daily: { date: string; count: number }[];
    top_sources: Row[];
    top_schools: Row[];
    quotas: { major_id: number; major: string; quota: number; target: number | null; interested: number; admitted: number; enrolled: number }[];
    my_tasks: { count: number; items: { id: number; title: string; due_at: string | null }[] };
};

const props = defineProps<{ cycle: { id: number; name: string } | null; data: Data | null }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Tổng quan', href: dashboard() }] } });

const cards = computed(() => {
    const k = props.data?.kpis;

    if (!k) {
        return [];
    }

    return [
        { label: 'Thí sinh mới tuần này', value: formatNumber(k.new_this_week), test: 'kpi-week' },
        { label: 'Hồ sơ', value: formatNumber(k.total), hint: `${formatNumber(k.open)} đang xử lý`, test: 'kpi-total' },
        { label: 'Trúng tuyển trở lên', value: formatNumber(k.admitted), test: 'kpi-admitted' },
        {
            label: 'Nhập học',
            value: formatNumber(k.enrolled),
            hint: k.target_enrolled ? `/ ${formatNumber(k.target_enrolled)} mục tiêu (${Math.round((k.enrolled * 100) / k.target_enrolled)}%)` : undefined,
            test: 'kpi-enrolled',
        },
    ];
});

const funnelItems = computed(() => (props.data?.funnel ?? []).map((s) => ({ label: s.name, value: s.count, color: s.color })));
const bars = (rows: Row[]) => rows.map((r) => ({ label: r.label, value: r.total, hint: r.enrolled ? `${r.enrolled} nhập học` : undefined }));
</script>

<template>
    <Head title="Tổng quan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Tổng quan</h1>
            <p class="text-sm text-muted-foreground">
                <template v-if="cycle">Số liệu của {{ cycle.name }} (mùa chọn ở thanh trên cùng), theo phạm vi dữ liệu của bạn.</template>
                <template v-else>Chưa có mùa tuyển sinh. Tạo mùa ở Cài đặt để xem số liệu.</template>
            </p>
        </div>

        <template v-if="data">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="c in cards" :key="c.label" class="rounded-xl border p-4" :data-test="c.test">
                    <div class="text-sm text-muted-foreground">{{ c.label }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums">{{ c.value }}</div>
                    <div v-if="c.hint" class="text-xs text-muted-foreground">{{ c.hint }}</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border p-4" data-test="funnel">
                    <h2 class="mb-3 font-medium">Phễu theo giai đoạn</h2>
                    <HBars :items="funnelItems" empty="Chưa có pipeline." />
                </section>
                <section class="rounded-xl border p-4">
                    <h2 class="mb-3 font-medium">Thí sinh mới 30 ngày qua</h2>
                    <DailyChart :points="data.daily" />
                </section>
                <section class="rounded-xl border p-4" data-test="top-sources">
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-medium">Top nguồn</h2><Link href="/reports?dimension=source" class="text-xs text-muted-foreground hover:underline">Chi tiết</Link></div>
                    <HBars :items="bars(data.top_sources)" />
                </section>
                <section class="rounded-xl border p-4" data-test="top-schools">
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-medium">Top trường THPT</h2><Link href="/reports?dimension=high_school" class="text-xs text-muted-foreground hover:underline">Chi tiết</Link></div>
                    <HBars :items="bars(data.top_schools)" />
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border p-4 lg:col-span-2" data-test="quotas">
                    <h2 class="mb-3 font-medium">Tiến độ chỉ tiêu theo ngành</h2>
                    <p v-if="data.quotas.length === 0" class="text-sm text-muted-foreground">Mùa này chưa khai báo chỉ tiêu.</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-muted-foreground">
                                <tr><th class="py-1 pr-3">Ngành</th><th class="px-2 text-right">Chỉ tiêu</th><th class="px-2 text-right">Quan tâm</th><th class="px-2 text-right">Trúng tuyển</th><th class="px-2 text-right">Nhập học</th><th class="w-32 pl-2">Tiến độ</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="q in data.quotas" :key="q.major_id" class="border-t" data-test="quota-row">
                                    <td class="py-1.5 pr-3">{{ q.major }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ q.quota }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ q.interested }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ q.admitted }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ q.enrolled }}</td>
                                    <td class="pl-2">
                                        <span class="block h-2 overflow-hidden rounded-full bg-muted"><span class="block h-full rounded-full bg-green-500" :style="{ width: `${Math.min(100, q.quota ? (q.enrolled * 100) / q.quota : 0)}%` }" /></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border p-4" data-test="my-tasks">
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-medium">Việc quá hạn của tôi</h2><Link href="/tasks?view=overdue" class="text-xs text-muted-foreground hover:underline">{{ data.my_tasks.count }} việc</Link></div>
                    <p v-if="data.my_tasks.count === 0" class="text-sm text-muted-foreground">Không có việc quá hạn.</p>
                    <ul v-else class="grid gap-1.5 text-sm">
                        <li v-for="t in data.my_tasks.items" :key="t.id" class="flex justify-between gap-2"><span class="truncate">{{ t.title }}</span><span class="shrink-0 text-xs text-destructive">{{ formatDateTime(t.due_at) }}</span></li>
                    </ul>
                </section>
            </div>
        </template>
    </div>
</template>
