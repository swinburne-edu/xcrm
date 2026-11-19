<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import PortalShell from '@/components/portal/PortalShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Subject = { id: number; name: string; transcript: number | null; thpt: number | null };
type Application = { id: number; preference_order: number; round: string | null; major: string | null; method: string | null; combination: string | null; final_score: number | null; missing: string[]; status: string; status_label: string; actions: { action: string; label: string }[]; can_delete: boolean };
type Method = { id: number; label: string; direct: boolean; needs_combination: boolean };
type Major = { id: number; name: string; methods: Method[]; combinations: { id: number; code: string }[] };

const props = defineProps<{
    applicant: { full_name: string; phone: string | null; email: string | null; code: string | null };
    cycle: { name: string; closed: boolean } | null;
    stage: string | null;
    subjects: Subject[];
    applications: Application[];
    options: { majors: Major[]; rounds: { id: number; name: string }[] };
    errors: Record<string, string>;
}>();

const grid = ref(props.subjects.map((s) => ({ subject_id: s.id, name: s.name, transcript: s.transcript ?? '', thpt: s.thpt ?? '' })));
const form = ref({ major_id: '' as number | '', cycle_method_id: '' as number | '', combination_id: '' as number | '', round_id: (props.options.rounds[0]?.id ?? '') as number | '' });
const major = computed(() => props.options.majors.find((m) => m.id === form.value.major_id));
const method = computed(() => major.value?.methods.find((m) => m.id === form.value.cycle_method_id));
const tones: Record<string, BadgeTone> = { draft: 'gray', submitted: 'blue', eligible: 'blue', ineligible: 'red', conditional_admit: 'amber', admitted: 'green', rejected: 'red', confirmed: 'green', withdrawn: 'gray' };
const editable = computed(() => props.cycle !== null && !props.cycle.closed);

function saveScores(): void {
    router.put('/cong-thi-sinh/diem', { scores: grid.value.map((r) => ({ subject_id: r.subject_id, transcript: r.transcript === '' ? null : r.transcript, thpt: r.thpt === '' ? null : r.thpt })) }, { preserveScroll: true });
}

function add(): void {
    router.post('/cong-thi-sinh/nguyen-vong', { ...form.value, combination_id: form.value.combination_id === '' ? null : form.value.combination_id }, { preserveScroll: true, onSuccess: () => (form.value = { ...form.value, major_id: '', cycle_method_id: '', combination_id: '' }) });
}

function act(a: Application, action: string): void {
    if (action === 'confirm' && !window.confirm('Xác nhận nhập học vào ngành ' + a.major + '? Hành động này không hoàn tác được.')) {
        return;
    }

    router.post(`/cong-thi-sinh/nguyen-vong/${a.id}/${action}`, {}, { preserveScroll: true });
}

function remove(a: Application): void {
    router.delete(`/cong-thi-sinh/nguyen-vong/${a.id}`, { preserveScroll: true });
}

function logout(): void {
    router.post('/cong-thi-sinh/dang-xuat');
}

function resetMethod(): void {
    form.value.cycle_method_id = '';
    form.value.combination_id = '';
}
</script>

<template>
    <Head title="Cổng thí sinh" />

    <PortalShell :school="cycle?.name ?? 'Cổng thí sinh'" wide>
        <template #actions><Button variant="outline" size="sm" data-test="portal-logout" @click="logout">Đăng xuất</Button></template>

        <div class="grid gap-8">
            <section class="grid gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ applicant.full_name }}</h1>
                <p class="text-sm text-muted-foreground">{{ applicant.code }} · {{ applicant.phone }}<template v-if="applicant.email"> · {{ applicant.email }}</template></p>
                <p v-if="stage" class="text-sm">Tình trạng hồ sơ: <strong data-test="stage">{{ stage }}</strong></p>
                <p v-if="!cycle" class="text-sm text-muted-foreground">Nhà trường chưa mở mùa tuyển sinh.</p>
                <p v-else-if="cycle.closed" class="text-sm text-muted-foreground">Mùa tuyển sinh đã đóng, hồ sơ chỉ để xem.</p>
            </section>

            <section class="grid gap-3" data-test="scores">
                <h2 class="text-lg font-semibold">Điểm của tôi</h2>
                <p class="text-sm text-muted-foreground">Nhập điểm trung bình lớp 12 (học bạ) và điểm thi tốt nghiệp THPT, thang 10. Nhà trường sẽ kiểm tra trước khi xét tuyển.</p>
                <table class="w-full max-w-lg text-sm">
                    <thead><tr class="border-b text-left text-muted-foreground"><th class="py-1 font-normal">Môn</th><th class="font-normal">TB lớp 12</th><th class="font-normal">Thi THPT</th></tr></thead>
                    <tbody>
                        <tr v-for="r in grid" :key="r.subject_id" class="border-b">
                            <td class="py-1.5">{{ r.name }}</td>
                            <td class="pr-2"><Input v-model="r.transcript" type="number" min="0" max="10" step="0.05" class="h-8" :disabled="!editable" /></td>
                            <td><Input v-model="r.thpt" type="number" min="0" max="10" step="0.05" class="h-8" :disabled="!editable" /></td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="editable"><Button size="sm" data-test="save-scores" @click="saveScores">Lưu điểm</Button></div>
            </section>

            <section class="grid gap-3" data-test="applications">
                <h2 class="text-lg font-semibold">Nguyện vọng</h2>
                <p v-if="!applications.length" class="text-sm text-muted-foreground">Chưa có nguyện vọng nào.</p>
                <ul v-else class="grid gap-2">
                    <li v-for="a in applications" :key="a.id" class="grid gap-1 rounded-md border p-3" data-test="application">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">NV{{ a.preference_order }}: {{ a.major }}</span>
                            <ToneBadge :tone="tones[a.status] ?? 'gray'" data-test="app-status">{{ a.status_label }}</ToneBadge>
                            <span class="ml-auto flex gap-1">
                                <Button v-for="x in a.actions" :key="x.action" size="sm" :variant="x.action === 'withdraw' ? 'outline' : 'default'" :data-test="`act-${x.action}`" @click="act(a, x.action)">{{ x.label }}</Button>
                                <Button v-if="a.can_delete" size="sm" variant="ghost" class="text-destructive" @click="remove(a)">Xoá</Button>
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ a.round }} · {{ a.method }}<template v-if="a.combination"> · {{ a.combination }}</template> · Điểm: {{ a.final_score ?? 'chưa đủ điểm' }}<template v-if="a.missing.length"> (thiếu: {{ a.missing.join(', ') }})</template></p>
                    </li>
                </ul>

                <form v-if="editable" class="grid max-w-xl gap-2 rounded-md border p-3 sm:grid-cols-2" @submit.prevent="add">
                    <select v-model="form.major_id" :class="selectClass" data-test="np-major" @change="resetMethod"><option value="">Chọn ngành…</option><option v-for="m in options.majors" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                    <select v-model="form.cycle_method_id" :class="selectClass" :disabled="!major" data-test="np-method"><option value="">Phương thức…</option><option v-for="m in major?.methods ?? []" :key="m.id" :value="m.id">{{ m.label }}</option></select>
                    <select v-if="method?.needs_combination" v-model="form.combination_id" :class="selectClass" data-test="np-combination"><option value="">Tổ hợp…</option><option v-for="c in major?.combinations ?? []" :key="c.id" :value="c.id">{{ c.code }}</option></select>
                    <select v-model="form.round_id" :class="selectClass"><option v-for="r in options.rounds" :key="r.id" :value="r.id">{{ r.name }}</option></select>
                    <InputError :message="errors.major_id ?? errors.cycle_method_id ?? errors.combination_id ?? errors.round_id ?? errors.preference_order" class="sm:col-span-2" />
                    <div class="sm:col-span-2"><Button type="submit" size="sm" :disabled="!form.major_id || !form.cycle_method_id" data-test="np-add">Thêm nguyện vọng</Button></div>
                </form>
            </section>
        </div>
    </PortalShell>
</template>
