<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import { money } from '@/components/campaigns/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Metrics = {
    cost: number;
    leads: number;
    enrolled: number;
    cost_per_lead: number | null;
    cost_per_enrolled: number | null;
    conversion: number | null;
    roi: number | null;
};

const props = defineProps<{
    cycles: (Metrics & { id: number; name: string; campaigns: number })[];
    campaigns: (Metrics & { id: number; code: string; name: string; channel_label: string | null; budget: number | null })[];
    cycle: { id: number; name: string } | null;
    revenue: number | null;
}>();

const revenue = ref<string | number>(props.revenue ?? '');

function apply(): void {
    router.get('/campaigns/performance', revenue.value === '' ? {} : { revenue: revenue.value }, { preserveScroll: true });
}

const pct = (v: number | null) => (v === null ? '—' : `${v}%`);
</script>

<template>
    <Head title="Chi phí và hiệu quả" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="grid gap-1">
            <Link href="/campaigns" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"><ArrowLeft class="size-3.5" />Chiến dịch</Link>
            <h1 class="text-xl font-semibold">Chi phí và hiệu quả</h1>
            <p class="text-sm text-muted-foreground">
                Chi phí tính từ chi phí theo ngày của chiến dịch. Thí sinh là hồ sơ gắn với chiến dịch, nhập học là hồ sơ đã trúng tuyển nhập học.
            </p>
        </div>

        <form class="flex flex-wrap items-end gap-3" data-test="roi-form" @submit.prevent="apply">
            <div class="grid gap-1">
                <Label for="revenue">Doanh thu dự kiến mỗi sinh viên nhập học (₫), để tính ROI</Label>
                <Input id="revenue" v-model="revenue" type="number" min="0" step="1" class="w-64" placeholder="Bỏ trống nếu không tính ROI" />
            </div>
            <Button type="submit" variant="outline">Tính</Button>
        </form>

        <section class="grid gap-2" data-test="cycles">
            <h2 class="font-semibold">So sánh giữa các mùa</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th class="py-1 font-normal">Mùa</th><th class="font-normal">Chiến dịch</th><th class="text-right font-normal">Chi phí</th>
                            <th class="text-right font-normal">Thí sinh</th><th class="text-right font-normal">Nhập học</th>
                            <th class="text-right font-normal">Chi phí / thí sinh</th><th class="text-right font-normal">Chi phí / nhập học</th>
                            <th class="text-right font-normal">Chuyển đổi</th><th class="text-right font-normal">ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in cycles" :key="c.id" class="border-b" data-test="cycle-row">
                            <td class="py-1.5 font-medium">{{ c.name }}</td><td>{{ c.campaigns }}</td>
                            <td class="text-right tabular-nums">{{ money(c.cost) }}</td>
                            <td class="text-right tabular-nums">{{ c.leads }}</td><td class="text-right tabular-nums">{{ c.enrolled }}</td>
                            <td class="text-right tabular-nums">{{ money(c.cost_per_lead) }}</td><td class="text-right tabular-nums">{{ money(c.cost_per_enrolled) }}</td>
                            <td class="text-right tabular-nums">{{ pct(c.conversion) }}</td><td class="text-right tabular-nums">{{ pct(c.roi) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="cycle" class="grid gap-2" data-test="campaigns">
            <h2 class="font-semibold">Từng chiến dịch của {{ cycle.name }}</h2>
            <p v-if="!campaigns.length" class="text-sm text-muted-foreground">Mùa này chưa có chiến dịch.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th class="py-1 font-normal">Chiến dịch</th><th class="text-right font-normal">Ngân sách</th><th class="text-right font-normal">Chi phí</th>
                            <th class="text-right font-normal">Thí sinh</th><th class="text-right font-normal">Nhập học</th>
                            <th class="text-right font-normal">Chi phí / thí sinh</th><th class="text-right font-normal">Chi phí / nhập học</th>
                            <th class="text-right font-normal">Chuyển đổi</th><th class="text-right font-normal">ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in campaigns" :key="c.id" class="border-b" data-test="campaign-row">
                            <td class="py-1.5"><Link :href="`/campaigns/${c.id}`" class="underline">{{ c.name }}</Link> <code class="ml-1 text-xs">{{ c.code }}</code></td>
                            <td class="text-right tabular-nums">{{ money(c.budget) }}</td><td class="text-right tabular-nums">{{ money(c.cost) }}</td>
                            <td class="text-right tabular-nums">{{ c.leads }}</td><td class="text-right tabular-nums">{{ c.enrolled }}</td>
                            <td class="text-right tabular-nums">{{ money(c.cost_per_lead) }}</td><td class="text-right tabular-nums">{{ money(c.cost_per_enrolled) }}</td>
                            <td class="text-right tabular-nums">{{ pct(c.conversion) }}</td><td class="text-right tabular-nums">{{ pct(c.roi) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
