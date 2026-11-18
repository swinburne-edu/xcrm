<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { ImportResult } from './types';

const open = defineModel<boolean>('open', { default: false });

const file = ref<File | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const result = ref<ImportResult | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        file.value = null;
        errors.value = {};
        result.value = null;
    }
});

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
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
                result.value = ((page as unknown as { flash?: { import?: ImportResult } }).flash?.import ?? null);

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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl" data-test="import-form">
            <DialogHeader>
                <DialogTitle>Nhập danh sách trường từ Excel</DialogTitle>
                <DialogDescription>
                    Dòng đầu là tiêu đề. Cột nhận dạng: Mã trường, Tên trường, Loại hình, Tỉnh (tên hoặc mã), Địa chỉ,
                    Hạng (A/B/C), Trạng thái hợp tác, Số học sinh khối 12, Số lớp khối 12, Điện thoại, Email, Website, Ghi chú.
                    Trường trùng mã (hoặc trùng tên + tỉnh khi không có mã) được cập nhật bằng các ô có dữ liệu.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-1.5">
                <Label for="import-file">Tệp (.xlsx, .xls, .csv — tối đa 5 MB, 5000 dòng)</Label>
                <input
                    id="import-file"
                    type="file"
                    accept=".xlsx,.xls,.csv,.txt"
                    class="text-sm"
                    data-test="import-file"
                    @change="pick"
                />
                <InputError :message="errors.file" />
            </div>

            <div v-if="result" class="grid gap-2 rounded-md border p-3 text-sm" data-test="import-result">
                <p class="font-medium">
                    {{ result.dry_run ? 'Kết quả kiểm tra (chưa ghi dữ liệu)' : 'Đã nhập' }}:
                    {{ result.created }} trường mới, {{ result.updated }} cập nhật, {{ result.skipped }} dòng lỗi.
                </p>
                <ul v-if="result.errors.length" class="max-h-40 list-disc overflow-y-auto pl-5 text-destructive">
                    <li v-for="e in result.errors" :key="e.row">Dòng {{ e.row }}: {{ e.message }}</li>
                </ul>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">Đóng</Button>
                <Button type="button" variant="secondary" :disabled="processing" data-test="import-check" @click="run(true)">
                    <Spinner v-if="processing" />
                    Kiểm tra trước
                </Button>
                <Button type="button" :disabled="processing" data-test="import-run" @click="run(false)">Nhập dữ liệu</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
