<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Download, FileSpreadsheet } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';

/**
 * Excel / CSV import of applicants in three steps:
 *  1. choose the file → 2. match columns to fields and check the preview (new / duplicate / error rows) →
 *  3. the import runs in the background (queue); the wizard follows its progress and lists the rows that failed.
 */
type Preview = {
    total: number;
    new: number;
    duplicate: number;
    error: number;
    rows: { row: number; status: 'new' | 'duplicate' | 'error'; message: string | null; data: Record<string, unknown> }[];
};
type PreviewResponse = {
    headings: string[];
    mapping: Record<string, string>;
    fields: { key: string; label: string }[];
    preview: Preview;
    max_rows: number;
    campaigns: { id: number; name: string }[];
};
type ImportState = { id: number; status: string; file: string; total: number; success: number; duplicate: number; error: number; errors: { row: number; message: string }[] };

const open = defineModel<boolean>('open', { default: false });

const step = ref<'upload' | 'map' | 'run'>('upload');
const file = ref<File | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const info = ref<PreviewResponse | null>(null);
const mapping = ref<Record<string, string | undefined>>({});
const campaignId = ref<number | ''>('');
const job = ref<ImportState | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

const STEPS = [
    { key: 'upload', label: 'Chọn file' },
    { key: 'map', label: 'Ghép cột & xem trước' },
    { key: 'run', label: 'Kết quả' },
] as const;
const stepIndex = computed(() => STEPS.findIndex((s) => s.key === step.value));
const fileSize = computed(() => (file.value ? `${Math.max(1, Math.round(file.value.size / 1024))} KB` : ''));
const mappedCount = computed(() => Object.values(mapping.value).filter(Boolean).length);

const tones: Record<string, BadgeTone> = { new: 'green', duplicate: 'amber', error: 'red' };
const labels: Record<string, string> = { new: 'Mới', duplicate: 'Trùng', error: 'Lỗi' };
const finished = computed(() => job.value !== null && ['done', 'failed'].includes(job.value.status));
const canImport = computed(() => !!mapping.value.full_name && (!!mapping.value.phone || !!mapping.value.email));
const importable = computed(() => (info.value?.preview.new ?? 0) + (info.value?.preview.duplicate ?? 0));

function cleanMapping(): Record<string, string> {
    return Object.fromEntries(Object.entries(mapping.value).filter(([, v]) => !!v)) as Record<string, string>;
}

function reset(): void {
    step.value = 'upload';
    file.value = null;
    error.value = null;
    info.value = null;
    mapping.value = {};
    campaignId.value = '';
    job.value = null;
    loading.value = false;

    if (timer) {
        clearTimeout(timer);
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    } else if (finished.value) {
        router.reload();
    }
});

onBeforeUnmount(() => timer && clearTimeout(timer));

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    error.value = null;
}

async function preview(): Promise<void> {
    if (!file.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    const form = new FormData();
    form.append('file', file.value);

    if (step.value === 'map') {
        form.append('mapping', JSON.stringify(cleanMapping()));
    }

    try {
        info.value = await requestJson<PreviewResponse>('POST', '/applicants/import/preview', form);

        if (step.value === 'upload') {
            mapping.value = { ...info.value.mapping };
        }

        step.value = 'map';
    } catch (e) {
        const err = e as HttpError;
        error.value = err.errors?.file ?? err.message;
    } finally {
        loading.value = false;
    }
}

async function start(): Promise<void> {
    if (!file.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    const form = new FormData();
    form.append('file', file.value);
    form.append('mapping', JSON.stringify(cleanMapping()));

    if (campaignId.value !== '') {
        form.append('campaign_id', String(campaignId.value));
    }

    try {
        const data = await requestJson<{ import: ImportState }>('POST', '/applicants/import', form);
        job.value = data.import;
        step.value = 'run';
        poll();
    } catch (e) {
        error.value = (e as HttpError).errors?.mapping ?? (e as HttpError).message;
    } finally {
        loading.value = false;
    }
}

function poll(): void {
    timer = setTimeout(async () => {
        try {
            job.value = (await requestJson<{ import: ImportState }>('GET', `/imports/${job.value!.id}`)).import;
        } catch {
            // keep trying: the worker may simply be busy
        }

        if (!finished.value) {
            poll();
        }
    }, 1500);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] gap-5 overflow-y-auto sm:max-w-3xl" data-test="import-wizard">
            <DialogHeader>
                <DialogTitle>Nhập thí sinh từ Excel</DialogTitle>
                <DialogDescription>
                    Thí sinh trùng (SĐT, email, CCCD) không bị tạo lại, chỉ được bổ sung thông tin còn thiếu.
                </DialogDescription>
            </DialogHeader>

            <ol class="flex items-center gap-2 text-xs" aria-label="Các bước">
                <template v-for="(s, i) in STEPS" :key="s.key">
                    <li class="flex items-center gap-1.5" :class="i === stepIndex ? 'font-medium text-foreground' : 'text-muted-foreground'" :aria-current="i === stepIndex ? 'step' : undefined">
                        <span
                            class="flex size-5 items-center justify-center rounded-full border text-[11px]"
                            :class="i < stepIndex ? 'border-primary bg-primary text-primary-foreground' : i === stepIndex ? 'border-primary text-primary' : ''"
                        >{{ i + 1 }}</span>
                        {{ s.label }}
                    </li>
                    <li v-if="i < STEPS.length - 1" class="h-px flex-1 bg-border" aria-hidden="true" />
                </template>
            </ol>

            <!-- 1. choose the file -->
            <section v-if="step === 'upload'" class="grid gap-4">
                <div class="flex items-center justify-between gap-3 rounded-md bg-muted/50 p-3">
                    <div class="text-sm">
                        <p class="font-medium">Chưa có file? Tải file mẫu</p>
                        <p class="text-muted-foreground">Điền theo sheet "Dữ liệu"; sheet "Hướng dẫn" nói rõ cột bắt buộc và giá trị hợp lệ.</p>
                    </div>
                    <Button as="a" href="/import-templates/applicants" variant="outline" size="sm" class="shrink-0" data-test="download-template">
                        <Download class="size-4" />
                        File mẫu
                    </Button>
                </div>

                <label
                    class="flex cursor-pointer flex-col items-center gap-1.5 rounded-md border-2 border-dashed px-4 py-8 text-center text-sm text-muted-foreground transition-colors hover:bg-muted/40"
                    :class="file ? 'border-primary/50 bg-muted/30' : ''"
                >
                    <FileSpreadsheet class="size-7" />
                    <template v-if="file">
                        <span class="font-medium break-all text-foreground">{{ file.name }}</span>
                        <span class="text-xs">{{ fileSize }} · bấm để đổi file</span>
                    </template>
                    <template v-else>
                        <span>Bấm để chọn file .xlsx, .xls hoặc .csv</span>
                        <span class="text-xs">Dòng đầu là tiêu đề cột · tối đa 5.000 dòng</span>
                    </template>
                    <input type="file" class="sr-only" accept=".xlsx,.xls,.csv" data-test="import-file" @change="pick" />
                </label>
                <InputError :message="error ?? undefined" />
            </section>

            <!-- 2. match columns + preview -->
            <section v-else-if="step === 'map' && info" class="grid gap-4">
                <div class="grid gap-2" data-test="import-summary">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-md bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <div class="text-lg font-semibold">{{ info.preview.new }}</div>
                            <div class="text-xs">Thí sinh mới</div>
                        </div>
                        <div class="rounded-md bg-amber-50 p-2 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            <div class="text-lg font-semibold">{{ info.preview.duplicate }}</div>
                            <div class="text-xs">Trùng</div>
                        </div>
                        <div class="rounded-md p-2" :class="info.preview.error ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-muted text-muted-foreground'">
                            <div class="text-lg font-semibold">{{ info.preview.error }}</div>
                            <div class="text-xs">Lỗi</div>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">Tổng {{ info.preview.total }} dòng trong file.</p>
                </div>

                <details class="rounded-md border" :open="!canImport">
                    <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm font-medium">
                        <span>Ghép cột <span class="font-normal text-muted-foreground">— đã nhận dạng {{ mappedCount }}/{{ info.fields.length }} cột</span></span>
                        <span v-if="!canImport" class="text-xs font-normal text-destructive">Cần Họ tên và SĐT hoặc Email</span>
                    </summary>
                    <div class="grid gap-3 border-t p-3">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div v-for="f in info.fields" :key="f.key" class="grid gap-1">
                                <Label :for="`map-${f.key}`">
                                    {{ f.label }}
                                    <span v-if="f.key === 'full_name' || f.key === 'phone'" class="text-destructive">*</span>
                                </Label>
                                <select
                                    :id="`map-${f.key}`"
                                    v-model="mapping[f.key]"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                    :data-test="`map-${f.key}`"
                                >
                                    <option :value="undefined">— Không nhập —</option>
                                    <option v-for="h in info.headings" :key="h" :value="h">{{ h }}</option>
                                </select>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" class="justify-self-start" :disabled="loading" @click="preview">
                            <Spinner v-if="loading" />
                            Cập nhật xem trước
                        </Button>
                    </div>
                </details>

                <div class="grid gap-1 sm:max-w-xs">
                    <Label for="map-campaign">Gắn vào chiến dịch (tuỳ chọn)</Label>
                    <select id="map-campaign" v-model="campaignId" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                        <option value="">— Không —</option>
                        <option v-for="c in info.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <p class="text-sm font-medium">Xem trước {{ info.preview.rows.length }} dòng đầu</p>
                    <div class="max-h-64 overflow-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2">Dòng</th>
                                    <th class="px-3 py-2">Họ tên</th>
                                    <th class="px-3 py-2">SĐT</th>
                                    <th class="px-3 py-2">Kết quả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in info.preview.rows" :key="r.row" class="border-t" data-test="preview-row">
                                    <td class="px-3 py-1.5">{{ r.row }}</td>
                                    <td class="px-3 py-1.5">{{ r.data.full_name || '—' }}</td>
                                    <td class="px-3 py-1.5">{{ r.data.phone || '—' }}</td>
                                    <td class="px-3 py-1.5">
                                        <ToneBadge :tone="tones[r.status]">{{ labels[r.status] }}</ToneBadge>
                                        <span v-if="r.message" class="ml-2 text-xs text-muted-foreground">{{ r.message }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <InputError :message="error ?? undefined" />
            </section>

            <!-- 3. result -->
            <section v-else-if="step === 'run' && job" class="grid gap-3" data-test="import-result">
                <div v-if="!finished" class="flex items-center gap-2 rounded-md bg-muted/50 p-4 text-sm"><Spinner /> Đang nhập… đã xử lý {{ job.success + job.duplicate + job.error }} dòng. Có thể đóng hộp thoại, việc nhập vẫn chạy nền.</div>
                <div v-else-if="job.status === 'failed'" class="rounded-md bg-red-50 p-4 text-sm text-destructive dark:bg-red-950/40">Nhập thất bại: {{ job.errors[0]?.message }}</div>
                <div v-else class="grid gap-3">
                    <div class="flex items-center gap-2 font-medium"><CheckCircle2 class="size-5 text-green-600" /> Đã nhập xong {{ job.total }} dòng</div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-md bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <div class="text-lg font-semibold">{{ job.success }}</div>
                            <div class="text-xs">Thí sinh mới</div>
                        </div>
                        <div class="rounded-md bg-amber-50 p-2 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            <div class="text-lg font-semibold">{{ job.duplicate }}</div>
                            <div class="text-xs">Trùng · đã ghi nhận lần tiếp xúc</div>
                        </div>
                        <div class="rounded-md p-2" :class="job.error ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-muted text-muted-foreground'">
                            <div class="text-lg font-semibold">{{ job.error }}</div>
                            <div class="text-xs">Lỗi</div>
                        </div>
                    </div>
                    <ul v-if="job.errors.length" class="max-h-40 list-disc overflow-y-auto rounded-md border p-2 pl-6 text-xs text-destructive">
                        <li v-for="e in job.errors" :key="e.row">Dòng {{ e.row }}: {{ e.message }}</li>
                    </ul>
                </div>
            </section>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" :variant="finished ? 'outline' : 'ghost'" @click="open = false">{{ finished ? 'Đóng' : 'Huỷ' }}</Button>
                <Button v-if="step === 'map'" type="button" variant="outline" :disabled="loading" @click="reset">Chọn file khác</Button>
                <Button v-if="step === 'upload'" type="button" :disabled="!file || loading" data-test="import-next" @click="preview">
                    <Spinner v-if="loading" />
                    Tiếp tục
                </Button>
                <Button v-else-if="step === 'map'" type="button" :disabled="!canImport || loading || importable === 0" data-test="import-start" @click="start">
                    <Spinner v-if="loading" />
                    Nhập {{ importable }} dòng
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
