<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PortalShell from '@/components/portal/PortalShell.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';

type Option = { combination: string; method: string; score: number; meets_minimum: boolean };
type Suggestion = { major_id: number; major_name: string; best: Option; options: Option[] };

defineProps<{ school: string; cycle: string | null; subjects: { code: string; name: string }[] }>();

const scores = ref<Record<string, string>>({});
const bonus = ref('');
const suggestions = ref<Suggestion[] | null>(null);
const message = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const busy = ref(false);
const lead = ref({ full_name: '', phone: '', email: '', consent: false, hp: '' });
const leadDone = ref<string | null>(null);

async function estimate(): Promise<void> {
    busy.value = true;
    errors.value = {};

    try {
        const filled = Object.fromEntries(Object.entries(scores.value).filter(([, v]) => v !== ''));
        const result = await requestJson<{ suggestions: Suggestion[]; message?: string }>('POST', '/tuyen-sinh/tinh-diem', { scores: filled, bonus: bonus.value === '' ? null : bonus.value });
        suggestions.value = result.suggestions;
        message.value = result.message ?? null;
    } catch (e) {
        errors.value = { form: (e as HttpError).errors.scores || (e as HttpError).message };
    } finally {
        busy.value = false;
    }
}

async function register(): Promise<void> {
    busy.value = true;
    errors.value = {};

    try {
        const result = await requestJson<{ message?: string }>('POST', '/tuyen-sinh/dang-ky', lead.value);
        leadDone.value = result.message ?? 'Đã gửi.';
    } catch (e) {
        const err = e as HttpError;
        errors.value = Object.keys(err.errors).length ? err.errors : { lead: err.message };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Tính điểm và gợi ý ngành" />

    <PortalShell :school="school" wide>
        <h1 class="text-2xl font-semibold tracking-tight">Tính điểm và gợi ý ngành</h1>
        <p class="mt-1 mb-6 text-sm text-muted-foreground">
            Nhập điểm các môn (thang 10) để xem điểm xét tuyển ước tính theo từng ngành<template v-if="cycle"> của {{ cycle }}</template>. Đây chỉ là ước tính, không phải kết quả xét tuyển.
        </p>

        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <form class="grid content-start gap-3" @submit.prevent="estimate">
                <div v-for="s in subjects" :key="s.code" class="grid grid-cols-[1fr_6rem] items-center gap-2">
                    <Label :for="`s-${s.code}`" class="font-normal">{{ s.name }}</Label>
                    <Input :id="`s-${s.code}`" v-model="scores[s.code]" type="number" min="0" max="10" step="0.05" inputmode="decimal" :data-test="`score-${s.code}`" />
                </div>
                <div class="grid grid-cols-[1fr_6rem] items-center gap-2">
                    <Label for="bonus" class="font-normal">Điểm cộng (ưu tiên)</Label>
                    <Input id="bonus" v-model="bonus" type="number" min="0" max="3" step="0.05" inputmode="decimal" data-test="bonus" />
                </div>
                <InputError :message="errors.form" />
                <Button type="submit" :disabled="busy" data-test="estimate">Xem gợi ý</Button>
            </form>

            <section>
                <p v-if="message" class="text-sm text-muted-foreground">{{ message }}</p>
                <p v-else-if="suggestions && !suggestions.length" class="text-sm text-muted-foreground" data-test="no-suggestion">Chưa có ngành nào đủ điểm môn để tính. Hãy nhập đủ 3 môn của một tổ hợp.</p>
                <ul v-else-if="suggestions" class="grid gap-3" data-test="suggestions">
                    <li v-for="s in suggestions" :key="s.major_id" class="rounded-md border p-3" data-test="suggestion">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="font-medium">{{ s.major_name }}</span>
                            <span class="text-lg font-semibold tabular-nums">{{ s.best.score }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Tổ hợp {{ s.best.combination }} · {{ s.best.method }}
                            <span v-if="!s.best.meets_minimum" class="text-destructive"> · chưa đạt điểm sàn của phương thức</span>
                        </p>
                        <details v-if="s.options.length > 1" class="mt-1 text-xs">
                            <summary class="cursor-pointer text-muted-foreground">Cách tính khác</summary>
                            <ul class="mt-1 grid gap-0.5"><li v-for="(o, i) in s.options.slice(1)" :key="i">{{ o.combination }} · {{ o.method }}: {{ o.score }}</li></ul>
                        </details>
                    </li>
                </ul>

                <form v-if="suggestions && suggestions.length" class="mt-8 grid max-w-md gap-3 rounded-md border p-4" @submit.prevent="register">
                    <h2 class="font-semibold">Muốn nhà trường tư vấn thêm?</h2>
                    <p v-if="leadDone" class="text-sm" data-test="lead-done">{{ leadDone }}</p>
                    <template v-else>
                        <Input v-model="lead.full_name" placeholder="Họ và tên" autocomplete="name" data-test="lead-name" />
                        <InputError :message="errors.full_name" />
                        <Input v-model="lead.phone" type="tel" placeholder="Số điện thoại" autocomplete="tel" data-test="lead-phone" />
                        <InputError :message="errors.phone" />
                        <Input v-model="lead.email" type="email" placeholder="Email (không bắt buộc)" autocomplete="email" />
                        <input v-model="lead.hp" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />
                        <Label class="flex items-start gap-2 font-normal"><Checkbox :model-value="lead.consent" @update:model-value="(v) => (lead.consent = v === true)" /><span>Tôi đồng ý để nhà trường liên hệ tư vấn tuyển sinh.</span></Label>
                        <InputError :message="errors.consent ?? errors.lead" />
                        <Button type="submit" :disabled="busy" data-test="lead-submit">Gửi đăng ký</Button>
                    </template>
                </form>
            </section>
        </div>
    </PortalShell>
</template>
