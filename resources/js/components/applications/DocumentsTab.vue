<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ExternalLink, Trash2, Upload, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from '@/components/campaigns/types';
import { formatDateTime } from '@/lib/format';
import type { DocumentItem, DocumentPanelProps } from './types';

/**
 * "Giấy tờ" tab: upload (PDF / JPG / PNG / WEBP, ≤ 10 MB), the registrar's approve / reject, and removal of
 * documents that are not approved yet. Files are private: they open through /documents/{id}/file.
 */
const props = defineProps<{ applicantUuid: string; caseUuid: string | null; panel: DocumentPanelProps }>();

const type = ref('');
const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const errors = ref<Record<string, string>>({});
const rejecting = ref<DocumentItem | null>(null);
const reason = ref('');
const reasonError = ref<string | undefined>();
const removing = ref<DocumentItem | null>(null);
const busy = ref(false);

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    errors.value = {};

    if (!type.value || !file.value) {
        errors.value = { file: !type.value ? 'Chọn loại giấy tờ.' : 'Chọn tệp để tải lên.' };

        return;
    }

    router.post(
        `/applicants/${props.applicantUuid}/documents`,
        { doc_type: type.value, file: file.value, case_uuid: props.caseUuid } as never,
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => (uploading.value = true),
            onError: (e) => (errors.value = e),
            onSuccess: () => {
                file.value = null;
                type.value = '';

                if (fileInput.value) {
                    fileInput.value.value = '';
                }
            },
            onFinish: () => (uploading.value = false),
        },
    );
}

function approve(d: DocumentItem): void {
    router.post(`/documents/${d.id}/review`, { approve: true }, { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) });
}

function openReject(d: DocumentItem): void {
    rejecting.value = d;
    reason.value = '';
    reasonError.value = undefined;
}

function reject(): void {
    if (!rejecting.value) {
        return;
    }

    router.post(`/documents/${rejecting.value.id}/review`, { approve: false, note: reason.value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onError: (e) => (reasonError.value = e.note),
        onSuccess: () => (rejecting.value = null),
        onFinish: () => (busy.value = false),
    });
}

function remove(): void {
    if (!removing.value) {
        return;
    }

    router.delete(`/documents/${removing.value.id}`, { preserveScroll: true, preserveState: true, onFinish: () => (removing.value = null) });
}

const size = (bytes: number | null): string => (bytes === null ? '' : bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
</script>

<template>
    <section class="grid gap-3" data-test="documents-tab">
        <h3 class="font-medium">Giấy tờ minh chứng</h3>

        <form v-if="panel.can.upload" class="grid items-end gap-2 rounded-md border bg-muted/30 p-3 sm:grid-cols-[12rem_1fr_auto]" @submit.prevent="upload">
            <div class="grid gap-1">
                <Label for="doc-type">Loại giấy tờ</Label>
                <select id="doc-type" v-model="type" :class="selectClass" data-test="doc-type">
                    <option value="" disabled>Chọn loại</option>
                    <option v-for="t in panel.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="doc-file">Tệp (PDF, JPG, PNG, WEBP · tối đa 10 MB)</Label>
                <Input id="doc-file" ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" data-test="doc-file" @change="pick" />
            </div>
            <Button type="submit" :disabled="uploading" data-test="doc-upload">
                <Spinner v-if="uploading" />
                <Upload v-else class="size-4" />
                Tải lên
            </Button>
            <InputError class="sm:col-span-3" :message="errors.file ?? errors.doc_type" />
        </form>

        <p v-if="panel.items.length === 0" class="rounded-md border p-4 text-sm text-muted-foreground" data-test="documents-empty">Chưa có giấy tờ nào.</p>

        <ul class="grid gap-2">
            <li v-for="d in panel.items" :key="d.id" class="grid gap-1 rounded-md border p-3 text-sm" data-test="document-row">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ d.type_label }}</span>
                        <ToneBadge :tone="d.status_tone">{{ d.status_label }}</ToneBadge>
                        <a v-if="d.file_name" :href="`/documents/${d.id}/file`" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-primary hover:underline" data-test="open-document">
                            {{ d.file_name }}<ExternalLink class="size-3" />
                        </a>
                        <span class="text-xs text-muted-foreground">{{ size(d.size) }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-if="panel.can.review">
                            <Button v-if="d.status !== 'approved'" size="sm" variant="secondary" :disabled="busy" data-test="approve-document" @click="approve(d)"><Check class="size-4" />Duyệt</Button>
                            <Button v-if="d.status !== 'rejected'" size="sm" variant="outline" :disabled="busy" data-test="reject-document" @click="openReject(d)"><X class="size-4" />Từ chối</Button>
                        </template>
                        <Button v-if="d.can_delete" variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" data-test="delete-document" @click="removing = d"><Trash2 class="size-3.5" /></Button>
                    </div>
                </div>
                <div class="text-xs text-muted-foreground">
                    Tải lên {{ formatDateTime(d.uploaded_at) }}<template v-if="d.reviewer"> · {{ d.status === 'approved' ? 'duyệt' : 'từ chối' }} bởi {{ d.reviewer }} {{ formatDateTime(d.reviewed_at) }}</template>
                </div>
                <div v-if="d.note" class="text-xs text-destructive">Lý do: {{ d.note }}</div>
            </li>
        </ul>
    </section>

    <Dialog :open="rejecting !== null" @update:open="(v) => !v && (rejecting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Từ chối {{ rejecting?.type_label }}</DialogTitle>
                <DialogDescription>Ghi lý do để cán bộ tư vấn biết cần bổ sung gì.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-1.5">
                <Label for="doc-reason">Lý do <span class="text-destructive">*</span></Label>
                <Input id="doc-reason" v-model="reason" maxlength="500" placeholder="Ví dụ: ảnh bị mờ, thiếu dấu mộc" data-test="reject-reason" />
                <InputError :message="reasonError" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="rejecting = null">Huỷ</Button>
                <Button variant="destructive" :disabled="busy" data-test="confirm-reject-document" @click="reject">Từ chối</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="removing !== null" @update:open="(v) => !v && (removing = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ removing?.type_label }}?</DialogTitle>
                <DialogDescription>Tệp {{ removing?.file_name }} sẽ bị xoá. Thao tác này không thể hoàn tác.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="removing = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-document" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
