<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import HBars from '@/components/reports/HBars.vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';

type Row = { key: number | null; label: string; total: number; contacted: number; admitted: number; enrolled: number; lost: number; rate: number };

const props = defineProps<{
    cycle: { id: number; name: string } | null;
    dimension: string;
    dimensions: { value: string; label: string }[];
    rows: Row[];
    can: { export: boolean };
}>();

const current = computed(() => props.dimensions.find((d) => d.value === props.dimension)?.label ?? '');
const chart = computed(() => props.rows.slice(0, 10).map((r) => ({ label: r.label, value: r.total, hint: `${r.rate}% nhập học` })));
const totals = computed(() =>
    props.rows.reduce(
        (t, r) => ({ total: t.total + r.total, contacted: t.contacted + r.contacted, admitted: t.admitted + r.admitted, enrolled: t.enrolled + r.enrolled, lost: t.lost + r.lost }),
        { total: 0, contacted: 0, admitted: 0, enrolled: 0, lost: 0 },
    ),
);

function pick(value: string): void {
    router.get('/reports', { dimension: value }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Báo cáo" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Báo cáo phễu tuyển sinh</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="cycle">{{ cycle.name }} · số hồ sơ đi qua từng bước, theo {{ current.toLowerCase() }}.</template>
                    <template v-else>Chưa có mùa tuyển sinh.</template>
                </p>
            </div>
            <Button v-if="can.export && cycle" as="a" variant="outline" :href="`/reports/export?dimension=${dimension}`" data-test="export"><Download class="size-4" />Xuất Excel</Button>
        </div>

        <nav class="flex flex-wrap gap-1 border-b" data-test="dimensions">
            <button
                v-for="d in dimensions"
                :key="d.value"
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="dimension === d.value ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground'"
                :data-test="`dim-${d.value}`"
                @click="pick(d.value)"
            >
                {{ d.label }}
            </button>
        </nav>

        <section class="rounded-xl border p-4">
            <h2 class="mb-3 font-medium">Số hồ sơ theo {{ current.toLowerCase() }} (10 đầu)</h2>
            <HBars :items="chart" />
        </section>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm" data-test="report-table">
                <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2">{{ current }}</th>
                        <th class="px-3 py-2 text-right">Hồ sơ</th>
                        <th class="px-3 py-2 text-right">Đã liên hệ</th>
                        <th class="px-3 py-2 text-right">Trúng tuyển</th>
                        <th class="px-3 py-2 text-right">Nhập học</th>
                        <th class="px-3 py-2 text-right">Thất bại</th>
                        <th class="px-3 py-2 text-right">Tỷ lệ nhập học</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.key ?? 'none'" class="border-t" data-test="report-row">
                        <td class="px-3 py-1.5">{{ r.label }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ formatNumber(r.total) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ formatNumber(r.contacted) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ formatNumber(r.admitted) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ formatNumber(r.enrolled) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ formatNumber(r.lost) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ r.rate }}%</td>
                    </tr>
                    <tr v-if="rows.length === 0"><td colspan="7" class="px-3 py-6 text-center text-muted-foreground">Chưa có dữ liệu.</td></tr>
                </tbody>
                <tfoot v-if="rows.length" class="border-t bg-muted/30 font-medium">
                    <tr>
                        <td class="px-3 py-2">Tổng</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.total) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.contacted) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.admitted) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.enrolled) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(totals.lost) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ totals.total ? Math.round((totals.enrolled * 1000) / totals.total) / 10 : 0 }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
