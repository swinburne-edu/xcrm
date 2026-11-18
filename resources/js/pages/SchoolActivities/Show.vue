<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Download, ExternalLink, Pencil, Printer, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import InputError from '@/components/InputError.vue';
import ActivityFormDialog from '@/components/schoolActivities/ActivityFormDialog.vue';
import type { ActivityDetail, ActivityOptions, AttendeeRow } from '@/components/schoolActivities/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';

/**
 * Chi tiết hoạt động (task 2.1): mã QR và link điểm danh, danh sách học sinh đã điểm danh, báo cáo sau hoạt động.
 */
const props = defineProps<{
    activity: ActivityDetail;
    attendees: AttendeeRow[];
    options: ActivityOptions;
    can: { update: boolean; delete: boolean };
}>();

const editOpen = ref(false);
const deleteOpen = ref(false);
const copied = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const report = ref({
    actual_attendees: props.activity.actual_attendees ?? (props.attendees.length || ''),
    cost: props.activity.cost ?? '',
    result_note: props.activity.result_note ?? '',
});

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.activity.checkin_url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // clipboard blocked: the link is still selectable in the field
    }
}

function saveReport(): void {
    router.post(`/school-activities/${props.activity.id}/complete`, report.value as never, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (saving.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (errors.value = {}),
        onFinish: () => (saving.value = false),
    });
}

function remove(): void {
    router.delete(`/school-activities/${props.activity.id}`, { onFinish: () => (deleteOpen.value = false) });
}

const print = (): void => window.print();
const money = (n: number | null): string => (n === null ? '—' : `${n.toLocaleString('vi-VN')} ₫`);
</script>

<template>
    <Head :title="activity.title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link href="/school-activities" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"><ArrowLeft class="size-3.5" />Hoạt động tại trường</Link>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ activity.title }}</h1>
                    <ToneBadge :tone="activity.type_tone">{{ activity.type_label }}</ToneBadge>
                    <ToneBadge :tone="activity.status_tone" data-test="activity-status">{{ activity.status_label }}</ToneBadge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ activity.school ?? 'Tại campus' }}
                    <template v-if="activity.start_at"> · {{ formatDateTime(activity.start_at) }}</template>
                    <template v-if="activity.end_at"> – {{ formatDateTime(activity.end_at) }}</template>
                    <template v-if="activity.location"> · {{ activity.location }}</template>
                </p>
            </div>
            <div v-if="can.update || can.delete" class="flex gap-2">
                <Button v-if="can.update" variant="outline" data-test="edit-activity" @click="editOpen = true"><Pencil class="size-4" />Sửa</Button>
                <Button v-if="can.delete" variant="outline" class="text-destructive" data-test="delete-activity" @click="deleteOpen = true"><Trash2 class="size-4" />Xoá</Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <section class="grid content-start gap-3 rounded-lg border p-4" data-test="qr-card">
                <h2 class="font-medium">Điểm danh bằng QR</h2>
                <img :src="`/school-activities/${activity.id}/qr.svg`" alt="Mã QR điểm danh" class="mx-auto aspect-square w-full max-w-60 rounded bg-white p-2" data-test="qr-image" />
                <div class="flex gap-1.5">
                    <Input :model-value="activity.checkin_url" readonly class="h-8 text-xs" data-test="checkin-url" @focus="($event.target as HTMLInputElement).select()" />
                    <Button variant="outline" size="icon" class="size-8 shrink-0" aria-label="Sao chép link" data-test="copy-link" @click="copyLink"><Check v-if="copied" class="size-4 text-green-600" /><Copy v-else class="size-4" /></Button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as="a" :href="`/school-activities/${activity.id}/qr.svg?download=1`" variant="outline" size="sm" data-test="download-qr"><Download class="size-4" />Tải QR (SVG)</Button>
                    <Button variant="outline" size="sm" @click="print"><Printer class="size-4" />In</Button>
                    <Button as="a" :href="activity.checkin_url" target="_blank" rel="noopener" variant="outline" size="sm" data-test="open-checkin"><ExternalLink class="size-4" />Mở trang</Button>
                </div>
                <p class="text-xs text-muted-foreground">Mở từ 2 giờ trước giờ bắt đầu đến 12 giờ sau khi kết thúc; đóng khi hoạt động đã diễn ra hoặc bị huỷ.</p>
            </section>

            <div class="grid content-start gap-6">
                <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 xl:grid-cols-3" data-test="facts">
                    <div><dt class="text-muted-foreground">Chiến dịch</dt><dd>{{ activity.campaign_name ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Nhân sự</dt><dd>{{ activity.staff.join(', ') || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Dự kiến tham dự</dt><dd>{{ activity.expected_attendees ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Đã điểm danh</dt><dd data-test="checkin-count">{{ activity.checkins }}</dd></div>
                    <div><dt class="text-muted-foreground">Tham dự thực tế</dt><dd>{{ activity.actual_attendees ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Chi phí</dt><dd>{{ money(activity.cost) }}</dd></div>
                </dl>

                <section v-if="can.update" class="grid gap-3 rounded-lg border p-4" data-test="report">
                    <div>
                        <h2 class="font-medium">Báo cáo sau hoạt động</h2>
                        <p class="text-xs text-muted-foreground">Lưu báo cáo sẽ chuyển hoạt động sang "Đã diễn ra" và đóng link điểm danh. Bỏ trống số tham dự thì lấy số học sinh đã điểm danh.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="r-actual">Tham dự thực tế</Label>
                            <Input id="r-actual" v-model="report.actual_attendees" type="number" min="0" data-test="report-actual" />
                            <InputError :message="errors.actual_attendees" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="r-cost">Chi phí (₫)</Label>
                            <Input id="r-cost" v-model="report.cost" type="number" min="0" data-test="report-cost" />
                            <InputError :message="errors.cost" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-note">Kết quả, nhận xét</Label>
                        <textarea id="r-note" v-model="report.result_note" rows="3" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" data-test="report-note" />
                        <InputError :message="errors.result_note" />
                    </div>
                    <InputError :message="errors.status" />
                    <Button class="justify-self-start" :disabled="saving || activity.status === 'cancelled'" data-test="save-report" @click="saveReport">
                        <Spinner v-if="saving" />
                        {{ activity.status === 'done' ? 'Cập nhật báo cáo' : 'Lưu báo cáo' }}
                    </Button>
                </section>
                <p v-else-if="activity.result_note" class="whitespace-pre-line text-sm text-muted-foreground">{{ activity.result_note }}</p>
            </div>
        </div>

        <section class="grid gap-2" data-test="attendees">
            <h2 class="font-medium">Học sinh đã điểm danh ({{ attendees.length }})</h2>
            <p v-if="attendees.length === 0" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="attendees-empty">Chưa có học sinh nào điểm danh.</p>
            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Học sinh</th>
                            <th class="px-3 py-2 font-medium">Lớp</th>
                            <th class="px-3 py-2 font-medium">Điện thoại</th>
                            <th class="px-3 py-2 font-medium">Giờ điểm danh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in attendees" :key="t.id" class="border-t" data-test="attendee-row">
                            <td class="px-3 py-2">
                                <Link v-if="t.uuid" :href="`/applicants/${t.uuid}`" class="font-medium hover:underline">{{ t.full_name }}</Link>
                                <span v-else>{{ t.full_name }}</span>
                                <div class="text-xs text-muted-foreground">{{ t.code }}</div>
                            </td>
                            <td class="px-3 py-2">{{ t.class_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ t.phone ?? '—' }}</td>
                            <td class="px-3 py-2">{{ formatDateTime(t.checked_in_at, '—') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <ActivityFormDialog v-model:open="editOpen" :record="activity" :options="options" />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá hoạt động {{ activity.title }}?</DialogTitle>
                <DialogDescription>Link và mã QR điểm danh ngừng hoạt động. Thí sinh đã điểm danh vẫn được giữ trong CRM.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-activity" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
