<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from './types';
import type { Option, ScoreRow, SubjectRow } from './types';

const props = defineProps<{
    applicantUuid: string;
    subjects: SubjectRow[];
    scores: ScoreRow[];
    scoreTypes: Option[];
    graduationYear: number | null;
    canEdit: boolean;
}>();

/** Quick-entry grid: transcript (subject × term), THPT exam (subject), and named exam totals. */
const TERMS = ['10-1', '10-2', '11-1', '11-2', '12-1', '12-2'];
const EXAM_TYPES = ['competency_exam', 'thinking_exam', 'talent'];

type ExamRow = { score_type: string; exam_name: string; score: string; max_score: string; year: string; removed: boolean; existing: boolean };

const transcript = ref<Record<string, string>>({});
const thpt = ref<Record<number, string>>({});
const thptYear = ref<string>('');
const exams = ref<ExamRow[]>([]);
const processing = ref(false);
const error = ref<string | null>(null);

const examTypeOptions = computed(() => props.scoreTypes.filter((t) => EXAM_TYPES.includes(t.value)));

function load(): void {
    const t: Record<string, string> = {};
    const h: Record<number, string> = {};
    const e: ExamRow[] = [];
    let year = '';

    for (const s of props.scores) {
        const value = s.score === null ? '' : String(s.score);

        if (s.score_type === 'transcript' && s.subject_id !== null && s.term) {
            t[`${s.subject_id}|${s.term}`] = value;
        } else if (s.score_type === 'thpt_exam' && s.subject_id !== null) {
            h[s.subject_id] = value;
            year = s.year ? String(s.year) : year;
        } else if (EXAM_TYPES.includes(s.score_type)) {
            e.push({ score_type: s.score_type, exam_name: s.exam_name ?? '', score: value, max_score: String(s.max_score), year: s.year ? String(s.year) : '', removed: false, existing: true });
        }
    }

    transcript.value = t;
    thpt.value = h;
    thptYear.value = year || (props.graduationYear ? String(props.graduationYear) : '');
    exams.value = e;
    error.value = null;
}

watch(() => props.scores, load, { immediate: true });

function addExam(): void {
    exams.value.push({ score_type: 'competency_exam', exam_name: '', score: '', max_score: '150', year: thptYear.value, removed: false, existing: false });
}

function removeExam(row: ExamRow, index: number): void {
    if (row.existing) {
        row.removed = true;
    } else {
        exams.value.splice(index, 1);
    }
}

function average(term: string): string {
    const values = props.subjects.map((s) => Number(transcript.value[`${s.id}|${term}`])).filter((v) => !Number.isNaN(v) && v > 0);

    return values.length ? (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2) : '—';
}

function save(): void {
    error.value = null;
    const rows: Record<string, unknown>[] = [];

    for (const s of props.subjects) {
        for (const term of TERMS) {
            rows.push({ score_type: 'transcript', subject_id: s.id, term, score: transcript.value[`${s.id}|${term}`] ?? '', max_score: 10 });
        }
    }

    const anyThpt = props.subjects.some((s) => (thpt.value[s.id] ?? '') !== '');

    if (anyThpt && !thptYear.value) {
        error.value = 'Nhập năm thi tốt nghiệp THPT.';

        return;
    }

    if (thptYear.value) {
        for (const s of props.subjects) {
            rows.push({ score_type: 'thpt_exam', subject_id: s.id, year: thptYear.value, score: thpt.value[s.id] ?? '', max_score: 10 });
        }
    }

    for (const e of exams.value) {
        if (!e.exam_name.trim()) {
            if (e.existing) {
                continue;
            }

            if (e.score !== '') {
                error.value = 'Nhập tên kỳ thi (HSA, V-ACT, TSA…).';

                return;
            }

            continue;
        }

        rows.push({ score_type: e.score_type, exam_name: e.exam_name.trim(), score: e.removed ? '' : e.score, max_score: e.max_score || 150, year: e.year || null });
    }

    router.put(`/applicants/${props.applicantUuid}/scores`, { scores: rows } as never, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onError: (errs) => (error.value = Object.values(errs)[0] ?? 'Không lưu được điểm.'),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <div class="grid gap-6" data-test="score-grid">
        <section class="grid gap-2">
            <h3 class="font-medium">Học bạ THPT</h3>
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Môn</th>
                            <th v-for="t in TERMS" :key="t" class="w-24 px-2 py-2 text-center font-medium">{{ t }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in subjects" :key="s.id" class="border-t">
                            <td class="px-3 py-1.5">{{ s.name }}</td>
                            <td v-for="t in TERMS" :key="t" class="px-1 py-1">
                                <Input
                                    v-model="transcript[`${s.id}|${t}`]"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="10"
                                    class="h-8 px-2 text-center"
                                    :disabled="!canEdit"
                                    :data-test="`cell-${s.code}-${t}`"
                                />
                            </td>
                        </tr>
                        <tr class="border-t bg-muted/30 text-muted-foreground">
                            <td class="px-3 py-1.5">Trung bình các môn nhập</td>
                            <td v-for="t in TERMS" :key="t" class="px-2 py-1.5 text-center">{{ average(t) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h3 class="font-medium">Thi tốt nghiệp THPT</h3>
                <label class="flex items-center gap-2 text-sm text-muted-foreground">
                    Năm thi
                    <Input v-model="thptYear" type="number" min="2000" max="2100" class="h-8 w-24" :disabled="!canEdit" data-test="thpt-year" />
                </label>
            </div>
            <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                <label v-for="s in subjects" :key="s.id" class="grid gap-1 text-sm">
                    <span class="text-muted-foreground">{{ s.name }}</span>
                    <Input v-model="thpt[s.id]" type="number" step="0.05" min="0" max="10" class="h-8" :disabled="!canEdit" :data-test="`thpt-${s.code}`" />
                </label>
            </div>
        </section>

        <section class="grid gap-2">
            <div class="flex items-center justify-between">
                <h3 class="font-medium">Đánh giá năng lực / tư duy / năng khiếu</h3>
                <Button v-if="canEdit" type="button" size="sm" variant="outline" data-test="add-exam" @click="addExam"><Plus class="size-4" />Thêm kỳ thi</Button>
            </div>
            <p v-if="!exams.some((e) => !e.removed)" class="text-sm text-muted-foreground">Chưa có kết quả.</p>
            <template v-for="(e, i) in exams" :key="i">
                <div v-if="!e.removed" class="grid grid-cols-[9rem_1fr_6rem_6rem_5rem_auto] items-center gap-2" data-test="exam-row">
                    <select v-model="e.score_type" :class="selectClass" :disabled="!canEdit || e.existing">
                        <option v-for="o in examTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <Input v-model="e.exam_name" placeholder="Tên kỳ thi (HSA, V-ACT, TSA…)" :disabled="!canEdit || e.existing" />
                    <Input v-model="e.score" type="number" step="0.01" min="0" placeholder="Điểm" :disabled="!canEdit" />
                    <Input v-model="e.max_score" type="number" step="0.01" min="0" placeholder="Thang" :disabled="!canEdit" />
                    <Input v-model="e.year" type="number" placeholder="Năm" :disabled="!canEdit" />
                    <Button v-if="canEdit" type="button" size="icon" variant="ghost" class="size-8 text-destructive" aria-label="Xoá" @click="removeExam(e, i)"><Trash2 class="size-4" /></Button>
                </div>
            </template>
        </section>

        <div v-if="canEdit" class="flex items-center gap-3">
            <Button type="button" :disabled="processing" data-test="save-scores" @click="save">
                <Spinner v-if="processing" />
                Lưu điểm
            </Button>
            <InputError :message="error ?? undefined" />
        </div>
    </div>
</template>
