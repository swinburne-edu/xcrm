<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileSpreadsheet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { ImportResult } from './types';

const open = defineModel<boolean>('open', { default: false });

const COLUMNS = [
    'Mã trường',
    'Tên trường',
    'Loại hình',
    'Tỉnh/thành phố',
    'Địa chỉ',
    'Hạng',
    'Trạng thái hợp tác',
    'Số học sinh khối 12',
    'Số lớp khối 12',
    'Điện thoại',
    'Email',
    'Website',
    'Ghi chú',
];

const file = ref<File | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const result = ref<ImportResult | null>(null);

const fileSize = computed(() =>
    file.value ? `${Math.max(1, Math.round(file.value.size / 1024))} KB` : '',
);

watch(open, (isOpen) => {
    if (isOpen) {
        file.value = null;
        errors.value = {};
        result.value = null;
    }
});

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    errors.value = {};
    result.value = null;
}

/** dry run = check only; the second click imports for real. */
function run(dryRun: boolean): void {
    if (!file.value) {
        errors.value = { file: 'Chọn tệp Excel hoặc CSV.' };

        return;
    }

    router.post(
        '/high-schools/import',
        { file: file.value, dry_run: dryRun } as never,
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => (processing.value = true),
            onError: (e) => (errors.value = e),
            onSuccess: (page) => {
                errors.value = {};
                result.value =
                    (page as unknown as { flash?: { import?: ImportResult } })
                        .flash?.import ?? null;

                if (!dryRun) {
                    file.value = null;
                }
            },
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90vh] gap-5 overflow-y-auto sm:max-w-lg"
            data-test="import-form"
        >
            <DialogHeader>
                <DialogTitle>Nhập danh sách trường</DialogTitle>
                <DialogDescription>
                    Thêm mới hoặc cập nhật nhiều trường THPT cùng lúc từ file
                    Excel.
                </DialogDescription>
            </DialogHeader>

            <!-- 1. template -->
            <section class="flex items-center justify-between gap-3 rounded-md bg-muted/50 p-3">
                <div class="text-sm">
                    <p class="font-medium">1. Tải file mẫu</p>
                    <p class="text-muted-foreground">
                        Điền theo sheet "Dữ liệu"; sheet "Hướng dẫn" nói rõ từng
                        cột.
                    </p>
                </div>
                <Button
                    as="a"
                    href="/import-templates/high-schools"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    data-test="download-template"
                >
                    <Download class="size-4" />
                    File mẫu
                </Button>
            </section>

            <!-- 2. file -->
            <section class="grid gap-2">
                <p class="text-sm font-medium">2. Chọn file đã điền</p>
                <label
                    class="flex cursor-pointer flex-col items-center gap-1.5 rounded-md border-2 border-dashed px-4 py-6 text-center text-sm text-muted-foreground transition-colors hover:bg-muted/40"
                    :class="file ? 'border-primary/50 bg-muted/30' : ''"
                >
                    <FileSpreadsheet class="size-7" />
                    <template v-if="file">
                        <span class="font-medium break-all text-foreground">{{
                            file.name
                        }}</span>
                        <span class="text-xs">{{ fileSize }} · bấm để đổi file</span>
                    </template>
                    <template v-else>
                        <span>Bấm để chọn file .xlsx, .xls hoặc .csv</span>
                        <span class="text-xs"
                            >Tối đa 5 MB và 5.000 dòng</span
                        >
                    </template>
                    <input
                        id="import-file"
                        type="file"
                        accept=".xlsx,.xls,.csv,.txt"
                        class="sr-only"
                        data-test="import-file"
                        @change="pick"
                    />
                </label>
                <InputError :message="errors.file" />

                <details class="text-sm">
                    <summary
                        class="cursor-pointer text-muted-foreground hover:text-foreground"
                    >
                        Các cột được nhận dạng
                    </summary>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="c in COLUMNS"
                            :key="c"
                            class="rounded-full border px-2 py-0.5 text-xs"
                            :class="c === 'Tên trường' ? 'border-primary/50 font-medium' : ''"
                            >{{ c }}<template v-if="c === 'Tên trường'"> *</template></span
                        >
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Trường trùng mã (hoặc trùng tên + tỉnh khi không có mã)
                        được cập nhật bằng các ô có dữ liệu.
                    </p>
                </details>
            </section>

            <!-- result -->
            <section
                v-if="result"
                class="grid gap-3 rounded-md border p-3 text-sm"
                data-test="import-result"
            >
                <p class="font-medium">
                    {{
                        result.dry_run
                            ? 'Kết quả kiểm tra — chưa ghi dữ liệu'
                            : 'Đã nhập xong'
                    }}
                </p>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-md bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <div class="text-lg font-semibold">{{ result.created }}</div>
                        <div class="text-xs">Thêm mới</div>
                    </div>
                    <div class="rounded-md bg-blue-50 p-2 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                        <div class="text-lg font-semibold">{{ result.updated }}</div>
                        <div class="text-xs">Cập nhật</div>
                    </div>
                    <div
                        class="rounded-md p-2"
                        :class="result.skipped ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-muted text-muted-foreground'"
                    >
                        <div class="text-lg font-semibold">{{ result.skipped }}</div>
                        <div class="text-xs">Dòng lỗi</div>
                    </div>
                </div>
                <ul
                    v-if="result.errors.length"
                    class="max-h-36 list-disc overflow-y-auto pl-5 text-destructive"
                >
                    <li v-for="e in result.errors" :key="e.row">
                        Dòng {{ e.row }}: {{ e.message }}
                    </li>
                </ul>
                <p v-if="result.dry_run && !result.skipped" class="text-muted-foreground">
                    Dữ liệu hợp lệ — bấm "Nhập dữ liệu" để ghi vào hệ thống.
                </p>
            </section>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="ghost" @click="open = false"
                    >Đóng</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing || !file"
                    data-test="import-check"
                    @click="run(true)"
                >
                    <Spinner v-if="processing" />
                    Kiểm tra trước
                </Button>
                <Button
                    type="button"
                    :disabled="processing || !file"
                    data-test="import-run"
                    @click="run(false)"
                    >Nhập dữ liệu</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
