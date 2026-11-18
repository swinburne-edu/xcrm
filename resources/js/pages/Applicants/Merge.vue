<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';

type Summary = {
    uuid: string;
    code: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    guardians: number;
    scores: number;
    cases: { cycle: string | null; stage: string | null }[];
};
type Field = { key: string; label: string; keep: string | null; merge: string | null; differs: boolean };

const props = defineProps<{ keep: Summary; merge: Summary; fields: Field[] }>();

const choices = ref<Record<string, 'keep' | 'merge'>>(Object.fromEntries(props.fields.filter((f) => f.differs).map((f) => [f.key, f.keep ? 'keep' : 'merge'])));
const processing = ref(false);
const errors = ref<Record<string, string>>({});

function submit(): void {
    if (!window.confirm(`Gộp ${props.merge.full_name} (${props.merge.code}) vào ${props.keep.full_name} (${props.keep.code})? Bản ${props.merge.code} sẽ bị ẩn khỏi danh sách.`)) {
        return;
    }

    router.post('/applicants/merge', { keep: props.keep.uuid, merge: props.merge.uuid, choices: choices.value }, {
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Head title="Gộp thí sinh" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Gộp thí sinh trùng</h1>
                <p class="text-sm text-muted-foreground">
                    Mọi hồ sơ, điểm, phụ huynh, lịch sử và việc của bản bị gộp sẽ chuyển sang bản giữ lại. Ô trống của bản giữ lại tự lấy từ bản kia.
                </p>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="`/applicants/merge/${merge.uuid}/${keep.uuid}`" data-test="swap"><ArrowLeftRight class="size-4" />Đổi chiều</Link>
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <section v-for="(p, side) in { keep, merge }" :key="side" class="rounded-md border p-3 text-sm" :data-test="`card-${side}`">
                <div class="text-xs font-medium uppercase" :class="side === 'keep' ? 'text-green-700' : 'text-amber-700'">{{ side === 'keep' ? 'Bản giữ lại' : 'Bản bị gộp' }}</div>
                <Link :href="`/applicants/${p.uuid}`" class="font-medium hover:underline">{{ p.full_name }}</Link>
                <span class="ml-2 text-xs text-muted-foreground">{{ p.code }}</span>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ p.cases.length }} hồ sơ<template v-if="p.cases.length"> ({{ p.cases.map((c) => `${c.cycle}: ${c.stage}`).join('; ') }})</template>
                    · {{ p.guardians }} phụ huynh · {{ p.scores }} điểm
                </div>
            </section>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2">Trường thông tin</th>
                        <th class="px-3 py-2">Giữ lại ({{ keep.code }})</th>
                        <th class="px-3 py-2">Bản bị gộp ({{ merge.code }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in fields" :key="f.key" class="border-t" :class="{ 'bg-amber-50/40': f.differs }" data-test="field-row">
                        <td class="px-3 py-1.5 text-muted-foreground">{{ f.label }}</td>
                        <template v-if="f.differs">
                            <td class="px-3 py-1.5">
                                <label class="flex items-center gap-2" :class="{ 'opacity-50': !f.keep }">
                                    <input v-model="choices[f.key]" type="radio" value="keep" :name="`c-${f.key}`" :data-test="`keep-${f.key}`" />
                                    {{ f.keep || '—' }}
                                </label>
                            </td>
                            <td class="px-3 py-1.5">
                                <label class="flex items-center gap-2" :class="{ 'opacity-50': !f.merge }">
                                    <input v-model="choices[f.key]" type="radio" value="merge" :name="`c-${f.key}`" :disabled="!f.merge" :data-test="`merge-${f.key}`" />
                                    {{ f.merge || '—' }}
                                </label>
                            </td>
                        </template>
                        <td v-else colspan="2" class="px-3 py-1.5">{{ f.keep || '—' }} <span class="text-xs text-muted-foreground">(giống nhau)</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <InputError :message="errors.merge || errors.keep" />

        <div class="flex justify-end gap-2">
            <Button as-child variant="outline"><Link href="/applicants/duplicates">Quay lại</Link></Button>
            <Button :disabled="processing" data-test="do-merge" @click="submit">
                <Spinner v-if="processing" />
                Gộp thí sinh
            </Button>
        </div>
    </div>
</template>
