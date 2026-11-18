<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import MajorFormDialog from '@/components/majors/MajorFormDialog.vue';
import type { MajorRow, Option } from '@/components/majors/MajorFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Cutoff = { year: number | string; method: string; score: number | string };
type Setup = {
    cycle_id: number;
    configured: boolean;
    quota_total: number | null;
    quota_direct: number;
    target_enrolled: number | null;
    tuition_fee: number | null;
    min_threshold: number | null;
    previous_cutoffs: Cutoff[];
    scholarship_info: string | null;
    combination_ids: number[];
    cycle_method_ids: number[];
};

const props = defineProps<{
    major: MajorRow;
    cycles: { id: number; name: string; status: string }[];
    selectedCycleId: number | null;
    setup: Setup | null;
    combinationOptions: { id: number; label: string }[];
    methodOptions: { id: number; label: string; is_direct: boolean }[];
    levelOptions: Option[];
    programTypeOptions: Option[];
    can: { update: boolean; delete: boolean; setup: boolean };
}>();

const tab = ref<'info' | 'cycle'>('cycle');
const editOpen = ref(false);
const deleteOpen = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const inputClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const form = ref({
    quota_total: '' as number | string,
    quota_direct: 0 as number | string,
    target_enrolled: '' as number | string,
    tuition_fee: '' as number | string,
    min_threshold: '' as number | string,
    previous_cutoffs: [] as Cutoff[],
    scholarship_info: '',
    combination_ids: [] as number[],
    cycle_method_ids: [] as number[],
});

function load(): void {
    const s = props.setup;
    errors.value = {};
    form.value = {
        quota_total: s?.quota_total ?? '',
        quota_direct: s?.quota_direct ?? 0,
        target_enrolled: s?.target_enrolled ?? '',
        tuition_fee: s?.tuition_fee ?? '',
        min_threshold: s?.min_threshold ?? '',
        previous_cutoffs: (s?.previous_cutoffs ?? []).map((c) => ({ ...c })),
        scholarship_info: s?.scholarship_info ?? '',
        combination_ids: [...(s?.combination_ids ?? [])],
        cycle_method_ids: [...(s?.cycle_method_ids ?? [])],
    };
}

watch(() => props.setup, load, { immediate: true });

function selectCycle(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    router.get(`/majors/${props.major.id}`, { cycle_id: id }, { preserveScroll: true, preserveState: false });
}

function toggle(list: number[], id: number): void {
    const i = list.indexOf(id);

    if (i === -1) {
        list.push(id);
    } else {
        list.splice(i, 1);
    }
}

function nullable(value: number | string): number | string | null {
    return value === '' ? null : value;
}

function save(): void {
    if (!props.selectedCycleId) {
        return;
    }

    const f = form.value;
    const data = {
        quota_total: f.quota_total,
        quota_direct: nullable(f.quota_direct),
        target_enrolled: nullable(f.target_enrolled),
        tuition_fee: nullable(f.tuition_fee),
        min_threshold: nullable(f.min_threshold),
        previous_cutoffs: f.previous_cutoffs.length > 0 ? f.previous_cutoffs : null,
        scholarship_info: f.scholarship_info || null,
        combination_ids: f.combination_ids,
        cycle_method_ids: f.cycle_method_ids,
    };

    router.put(`/majors/${props.major.id}/cycles/${props.selectedCycleId}`, data as never, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (errors.value = {}),
        onFinish: () => (processing.value = false),
    });
}

function confirmDelete(): void {
    router.delete(`/majors/${props.major.id}`, {
        onFinish: () => (deleteOpen.value = false),
    });
}
</script>

<template>
    <Head :title="major.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link
                    href="/majors"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    ><ArrowLeft class="size-3.5" />Ngành</Link
                >
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ major.name }}</h1>
                    <ToneBadge :tone="major.is_active ? 'green' : 'gray'">{{
                        major.is_active ? 'Đang tuyển' : 'Ngừng'
                    }}</ToneBadge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ major.admission_code }}
                    <template v-if="major.moet_code"> · Mã Bộ {{ major.moet_code }}</template>
                    · {{ major.program_type_label }} · {{ major.level_label }}
                    <template v-if="major.faculty_name"> · {{ major.faculty_name }}</template>
                    <template v-if="major.campus_name"> · {{ major.campus_name }}</template>
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" data-test="edit-major" @click="editOpen = true"
                    ><Pencil class="size-4" />Sửa</Button
                >
                <Button
                    v-if="can.delete"
                    variant="outline"
                    class="text-destructive"
                    data-test="delete-major"
                    @click="deleteOpen = true"
                    ><Trash2 class="size-4" />Xoá</Button
                >
            </div>
        </div>

        <div class="flex gap-1 border-b">
            <button
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="tab === 'cycle' ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'"
                data-test="tab-cycle"
                @click="tab = 'cycle'"
            >
                Theo mùa
            </button>
            <button
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="tab === 'info' ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'"
                data-test="tab-info"
                @click="tab = 'info'"
            >
                Thông tin
            </button>
        </div>

        <section v-if="tab === 'info'" class="grid max-w-3xl gap-4 text-sm" data-test="info">
            <div>
                <h2 class="font-medium">Giới thiệu</h2>
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ major.description || 'Chưa có.' }}
                </p>
            </div>
            <div>
                <h2 class="font-medium">Cơ hội nghề nghiệp</h2>
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ major.career_info || 'Chưa có.' }}
                </p>
            </div>
            <p v-if="major.duration_years" class="text-muted-foreground">
                Thời gian đào tạo: {{ major.duration_years }} năm.
            </p>
        </section>

        <section v-else class="grid max-w-3xl gap-5" data-test="cycle-setup">
            <div v-if="cycles.length === 0" class="text-sm text-muted-foreground">
                Chưa có mùa tuyển sinh nào.
                <Link href="/settings/cycles" class="underline">Tạo mùa</Link> trước khi cấu hình ngành.
            </div>
            <template v-else>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1.5">
                        <Label for="cycle-select">Mùa tuyển sinh</Label>
                        <select
                            id="cycle-select"
                            :value="selectedCycleId ?? ''"
                            :class="inputClass"
                            data-test="cycle-select"
                            @change="selectCycle"
                        >
                            <option v-for="c in cycles" :key="c.id" :value="c.id">
                                {{ c.name }}{{ c.status === 'closed' ? ' (đã đóng)' : '' }}
                            </option>
                        </select>
                    </div>
                    <p v-if="setup && !setup.configured" class="pb-2 text-sm text-muted-foreground">
                        Ngành chưa được cấu hình cho mùa này.
                    </p>
                    <p v-else-if="!can.setup" class="pb-2 text-sm text-amber-700 dark:text-amber-400" data-test="readonly-note">
                        Chỉ đọc (mùa đã đóng hoặc không đủ quyền).
                    </p>
                </div>

                <form v-if="setup" class="grid gap-5" @submit.prevent="save">
                    <fieldset :disabled="!can.setup" class="grid gap-5">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1.5">
                                <Label for="q-total">Tổng chỉ tiêu <span class="text-destructive">*</span></Label>
                                <Input id="q-total" v-model="form.quota_total" type="number" min="0" />
                                <InputError :message="errors.quota_total" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="q-direct">Trong đó xét tuyển thẳng</Label>
                                <Input id="q-direct" v-model="form.quota_direct" type="number" min="0" />
                                <InputError :message="errors.quota_direct" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="q-target">Mục tiêu nhập học</Label>
                                <Input id="q-target" v-model="form.target_enrolled" type="number" min="0" />
                                <InputError :message="errors.target_enrolled" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="q-fee">Học phí (VNĐ)</Label>
                                <Input id="q-fee" v-model="form.tuition_fee" type="number" min="0" />
                                <InputError :message="errors.tuition_fee" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="q-min">Điểm sàn</Label>
                                <Input id="q-min" v-model="form.min_threshold" type="number" step="0.01" min="0" />
                                <InputError :message="errors.min_threshold" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>Tổ hợp xét tuyển</Label>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                <label v-for="c in combinationOptions" :key="c.id" class="flex items-center gap-1.5 text-sm">
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="form.combination_ids.includes(c.id)"
                                        @change="toggle(form.combination_ids, c.id)"
                                    />
                                    {{ c.label }}
                                </label>
                                <span v-if="combinationOptions.length === 0" class="text-sm text-muted-foreground">
                                    Chưa có tổ hợp nào đang sử dụng.
                                </span>
                            </div>
                            <InputError :message="errors.combination_ids" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Phương thức xét tuyển của mùa</Label>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                <label v-for="m in methodOptions" :key="m.id" class="flex items-center gap-1.5 text-sm">
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="form.cycle_method_ids.includes(m.id)"
                                        @change="toggle(form.cycle_method_ids, m.id)"
                                    />
                                    {{ m.label }}
                                    <ToneBadge v-if="m.is_direct" tone="violet">Xét thẳng</ToneBadge>
                                </label>
                                <span v-if="methodOptions.length === 0" class="text-sm text-muted-foreground">
                                    Mùa này chưa có phương thức. Thêm ở trang mùa tuyển sinh.
                                </span>
                            </div>
                            <InputError :message="errors.cycle_method_ids" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label>Điểm chuẩn các năm trước</Label>
                                <Button
                                    v-if="can.setup"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="form.previous_cutoffs.push({ year: '', method: '', score: '' })"
                                    ><Plus class="size-4" />Thêm</Button
                                >
                            </div>
                            <div v-for="(row, i) in form.previous_cutoffs" :key="i" class="grid grid-cols-[6rem_1fr_6rem_auto] items-center gap-2">
                                <Input v-model="row.year" type="number" placeholder="Năm" />
                                <Input v-model="row.method" placeholder="Phương thức (VD: 100)" />
                                <Input v-model="row.score" type="number" step="0.01" placeholder="Điểm" />
                                <Button
                                    v-if="can.setup"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-destructive"
                                    aria-label="Xoá dòng"
                                    @click="form.previous_cutoffs.splice(i, 1)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                            <InputError :message="errors['previous_cutoffs'] ?? Object.entries(errors).find(([k]) => k.startsWith('previous_cutoffs.'))?.[1]" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="q-schol">Học bổng / ưu đãi</Label>
                            <textarea
                                id="q-schol"
                                v-model="form.scholarship_info"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.scholarship_info" />
                        </div>
                    </fieldset>

                    <div v-if="can.setup">
                        <Button type="submit" :disabled="processing" data-test="save-cycle">
                            <Spinner v-if="processing" />
                            Lưu cấu hình mùa
                        </Button>
                    </div>
                </form>
            </template>
        </section>
    </div>

    <MajorFormDialog
        v-model:open="editOpen"
        :record="major"
        :level-options="levelOptions"
        :program-type-options="programTypeOptions"
    />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá ngành {{ major.name }}?</DialogTitle>
                <DialogDescription>
                    Chỉ xoá được ngành chưa cấu hình cho mùa nào. Ngành đã dùng hãy chuyển sang "Ngừng sử dụng".
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
