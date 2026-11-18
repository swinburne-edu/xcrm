<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, TrendingDown, TrendingUp } from '@lucide/vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Tiến độ chỉ tiêu (task 2.3): mỗi ngành một dòng, thanh tiến độ trúng tuyển / xác nhận / nhập học so với chỉ tiêu,
 * kèm cảnh báo thiếu hoặc thừa. Số liệu tính trên toàn tổ chức, cập nhật mỗi giờ bởi `crm:aggregate-quotas`.
 */
type Row = {
    major_id: number;
    major: string;
    faculty: string | null;
    quota: number;
    direct: number;
    target: number;
    interested: number;
    submitted: number;
    eligible: number;
    admitted: number;
    confirmed: number;
    enrolled: number;
    secured: number;
    fill: number | null;
    level: 'ok' | 'short' | 'over';
    gap: number;
    change_7d: number | null;
};

const props = defineProps<{
    cycle: { id: number; name: string; status: string; progress: number | null } | null;
    rows: Row[];
    summary: Record<string, number | null>;
    level: 'all' | 'alert' | 'short' | 'over';
    last_aggregated_at: string | null;
    rules: { short_factor: number; min_progress: number };
}>();

const tabs = [
    { key: 'all', label: 'Tất cả' },
    { key: 'alert', label: 'Cần chú ý' },
    { key: 'short', label: 'Thiếu' },
    { key: 'over', label: 'Vượt chỉ tiêu' },
] as const;

const tone = { ok: 'green', short: 'amber', over: 'red' } as const;
const label = { ok: 'Đúng tiến độ', short: 'Thiếu', over: 'Vượt chỉ tiêu' } as const;

/** Segments of the bar are exclusive: enrolled, confirmed beyond enrolled, admitted beyond confirmed. */
function bar(r: Row): { max: number; segments: { cls: string; width: number; title: string }[]; marker: number } {
    const max = Math.max(r.quota, r.target, r.secured, 1);
    const enrolled = r.enrolled;
    const confirmed = Math.max(0, r.confirmed - r.enrolled);
    const admitted = Math.max(0, r.secured - enrolled - confirmed);
    const pct = (n: number): number => Math.min(100, (n / max) * 100);

    return {
        max,
        marker: pct(r.quota),
        segments: [
            { cls: 'bg-emerald-600', width: pct(enrolled), title: `Nhập học ${enrolled}` },
            { cls: 'bg-emerald-400', width: pct(confirmed), title: `Xác nhận nhập học ${confirmed}` },
            { cls: 'bg-blue-400', width: pct(admitted), title: `Trúng tuyển ${admitted}` },
        ],
    };
}

const count = (key: string): number => Number(props.summary[key] ?? 0);
const tabCount = (key: string): number | null => (key === 'alert' ? count('short') + count('over') : key === 'short' ? count('short') : key === 'over' ? count('over') : null);
</script>

<template>
    <Head title="Tiến độ chỉ tiêu" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Tiến độ chỉ tiêu</h1>
            <p class="text-sm text-muted-foreground">
                <template v-if="cycle">
                    {{ cycle.name }}<template v-if="cycle.progress !== null"> · đã qua {{ cycle.progress }}% thời gian</template>
                    · cập nhật mỗi giờ<template v-if="last_aggregated_at">, lần gần nhất {{ formatDateTime(last_aggregated_at) }}</template>
                </template>
                <template v-else>Chưa có mùa tuyển sinh.</template>
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5" data-test="quota-summary">
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Chỉ tiêu</div>
                <div class="text-2xl font-semibold">{{ count('quota') }}</div>
                <div class="text-xs text-muted-foreground">mục tiêu nhập học {{ count('target') }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Trúng tuyển / nhập học</div>
                <div class="text-2xl font-semibold">{{ count('secured') }}</div>
                <div class="text-xs text-muted-foreground">{{ summary.fill === null ? '—' : `${summary.fill}% mục tiêu` }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Xác nhận nhập học</div>
                <div class="text-2xl font-semibold">{{ count('confirmed') }}</div>
                <div class="text-xs text-muted-foreground">đã nộp NV: {{ count('submitted') }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Đã nhập học</div>
                <div class="text-2xl font-semibold">{{ count('enrolled') }}</div>
                <div class="text-xs text-muted-foreground">quan tâm: {{ count('interested') }}</div>
            </div>
            <div class="rounded-lg border p-3" :class="count('short') + count('over') > 0 ? 'border-amber-500/60' : ''">
                <div class="text-xs text-muted-foreground">Cảnh báo</div>
                <div class="text-2xl font-semibold">{{ count('short') + count('over') }}</div>
                <div class="text-xs text-muted-foreground">{{ count('short') }} thiếu · {{ count('over') }} vượt</div>
            </div>
        </div>

        <nav class="flex flex-wrap gap-1 border-b" aria-label="Lọc cảnh báo">
            <Link
                v-for="t in tabs"
                :key="t.key"
                :href="t.key === 'all' ? '/quotas' : `/quotas?level=${t.key}`"
                preserve-scroll
                :class="cn('-mb-px border-b-2 px-3 py-2 text-sm', level === t.key ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground')"
                :data-test="`quota-tab-${t.key}`"
            >
                {{ t.label }}<span v-if="tabCount(t.key) !== null" class="ml-1 text-xs text-muted-foreground">({{ tabCount(t.key) }})</span>
            </Link>
        </nav>

        <p v-if="rows.length === 0" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="quota-empty">
            {{ level === 'all' ? 'Mùa này chưa khai báo chỉ tiêu cho ngành nào (Đào tạo & chỉ tiêu → Ngành).' : 'Không có ngành nào ở mức này.' }}
        </p>

        <ul class="grid gap-2">
            <li v-for="r in rows" :key="r.major_id" class="grid gap-2 rounded-lg border p-3 text-sm" :class="r.level !== 'ok' ? 'border-amber-500/50' : ''" data-test="quota-row">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Link :href="`/applications?filter[major_id]=${r.major_id}`" class="font-medium hover:underline">{{ r.major }}</Link>
                        <div v-if="r.faculty" class="text-xs text-muted-foreground">{{ r.faculty }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="r.change_7d !== null && r.change_7d !== 0" class="inline-flex items-center gap-0.5 text-xs" :class="r.change_7d > 0 ? 'text-emerald-600' : 'text-red-600'">
                            <TrendingUp v-if="r.change_7d > 0" class="size-3.5" /><TrendingDown v-else class="size-3.5" />{{ r.change_7d > 0 ? '+' : '' }}{{ r.change_7d }} / 7 ngày
                        </span>
                        <ToneBadge :tone="tone[r.level]" data-test="quota-level">
                            <AlertTriangle v-if="r.level !== 'ok'" class="mr-1 size-3" />{{ label[r.level] }}<template v-if="r.level === 'short'"> {{ r.gap }}</template><template v-if="r.level === 'over'"> +{{ r.gap }}</template>
                        </ToneBadge>
                    </div>
                </div>

                <div class="relative h-3 overflow-hidden rounded-full bg-muted" role="img" :aria-label="`${r.secured} trên chỉ tiêu ${r.quota}`">
                    <div class="flex h-full">
                        <div v-for="s in bar(r).segments" :key="s.title" :class="s.cls" :style="{ width: `${s.width}%` }" :title="s.title" />
                    </div>
                    <div class="absolute inset-y-0 w-0.5 bg-foreground/70" :style="{ left: `${bar(r).marker}%` }" title="Chỉ tiêu" />
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                    <span>Chỉ tiêu <b class="text-foreground">{{ r.quota }}</b> (xét thẳng {{ r.direct }})</span>
                    <span>Mục tiêu nhập học <b class="text-foreground">{{ r.target }}</b></span>
                    <span>Quan tâm {{ r.interested }}</span>
                    <span>Đã nộp {{ r.submitted }}</span>
                    <span>Đủ ĐK {{ r.eligible }}</span>
                    <span>Trúng tuyển <b class="text-foreground">{{ r.admitted }}</b></span>
                    <span>Xác nhận <b class="text-foreground">{{ r.confirmed }}</b></span>
                    <span>Nhập học <b class="text-foreground">{{ r.enrolled }}</b></span>
                    <span v-if="r.fill !== null">Đạt {{ r.fill }}% mục tiêu</span>
                </div>
            </li>
        </ul>

        <details class="text-xs text-muted-foreground">
            <summary class="cursor-pointer hover:text-foreground">Cách tính cảnh báo</summary>
            <ul class="mt-1 list-disc pl-5">
                <li><b>Vượt chỉ tiêu</b>: số thí sinh trúng tuyển hoặc đã nhập học (mỗi hồ sơ tính một lần) lớn hơn chỉ tiêu của ngành.</li>
                <li>
                    <b>Thiếu</b>: số đó nhỏ hơn mục tiêu nhập học × phần thời gian đã qua của mùa × {{ rules.short_factor }}; chỉ xét khi mùa đã qua ít nhất {{ rules.min_progress }}% thời gian
                    (cần khai báo ngày bắt đầu và kết thúc của mùa). Không có mục tiêu riêng thì dùng chỉ tiêu.
                </li>
                <li>Khi một ngành chuyển sang Thiếu hoặc Vượt, trưởng phòng và quản trị nhận một thông báo ở biểu tượng chuông.</li>
            </ul>
        </details>
    </div>
</template>
