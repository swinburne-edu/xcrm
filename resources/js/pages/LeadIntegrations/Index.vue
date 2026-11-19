<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, Copy, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import LeadIntegrationFormDialog from '@/components/integrations/LeadIntegrationFormDialog.vue';
import { statusTone } from '@/components/integrations/types';
import type { LeadIntegrationOptions, LeadIntegrationRow } from '@/components/integrations/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Tích hợp lead ads (task 2.7): Facebook Lead Ads and TikTok push every new lead to a webhook address of this system.
 * Each integration shows its address, the settings to paste into the platform and the latest leads it received.
 */
defineProps<{ integrations: LeadIntegrationRow[]; options: LeadIntegrationOptions; can: { manage: boolean } }>();

const formOpen = ref(false);
const editing = ref<LeadIntegrationRow | null>(null);
const deleting = ref<LeadIntegrationRow | null>(null);
const expanded = ref<number | null>(null);
const copied = ref<string | null>(null);

const countLabels: Record<string, string> = { received: 'đang xử lý', created: 'tạo mới', merged: 'trùng', failed: 'lỗi', rejected: 'bị từ chối' };

function openForm(row: LeadIntegrationRow | null): void {
    editing.value = row;
    formOpen.value = true;
}

async function copy(key: string, text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 1500);
    } catch {
        // Clipboard blocked: the value stays selectable in its box.
    }
}

function selectAll(event: Event): void {
    (event.target as HTMLInputElement).select();
}

function remove(): void {
    if (deleting.value) {
        router.delete(`/settings/lead-integrations/${deleting.value.id}`, { preserveScroll: true, onFinish: () => (deleting.value = null) });
    }
}

const steps: Record<string, string[]> = {
    facebook: [
        'Trong Meta for Developers, mở ứng dụng của trường → Webhooks → chọn đối tượng Page, đăng ký trường leadgen.',
        'Dán địa chỉ webhook ở trên vào ô Callback URL và verify token vào ô Verify token, rồi bấm Verify and save.',
        'Đăng ký (subscribe) Page vào ứng dụng với trường leadgen, và tạo Page access token có quyền leads_retrieval để dán vào tích hợp.',
        'Gửi thử bằng công cụ Lead Ads Testing của Meta, lead sẽ hiện ở danh sách bên dưới.',
    ],
    tiktok: [
        'Trong TikTok Ads Manager, vào phần cấu hình nhận lead qua webhook cho tài khoản quảng cáo.',
        'Dán địa chỉ webhook ở trên làm URL nhận lead và khai báo cùng khoá ký (signing secret) như đã nhập ở đây.',
        'Mỗi yêu cầu phải có header TikTok-Signature dạng t=thời_gian,s=chữ_ký (HMAC-SHA256 của "thời_gian.nội_dung" với khoá ký); yêu cầu lệch quá 5 phút bị từ chối.',
        'Gửi thử một lead; nếu bị từ chối, xem trạng thái "Bị từ chối" ở danh sách lead gần đây.',
    ],
};
</script>

<template>
    <Head title="Tích hợp lead ads" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Tích hợp lead ads</h1>
                <p class="text-sm text-muted-foreground">Nhận thí sinh từ Facebook Lead Ads và TikTok: lead mới tự tạo thí sinh, hồ sơ và việc “Gọi lần đầu”.</p>
            </div>
            <Button v-if="can.manage" data-test="add-integration" @click="openForm(null)"><Plus class="size-4" />Thêm tích hợp</Button>
        </div>

        <p v-if="integrations.length === 0" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="integrations-empty">
            Chưa có tích hợp nào. Bấm “Thêm tích hợp” để tạo địa chỉ webhook cho Facebook hoặc TikTok.
        </p>

        <section v-for="i in integrations" :key="i.id" class="rounded-lg border" data-test="integration-card">
            <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">{{ i.name }}</h2>
                        <ToneBadge tone="blue">{{ i.provider_label }}</ToneBadge>
                        <ToneBadge :tone="i.is_active ? 'green' : 'gray'">{{ i.is_active ? 'Đang nhận lead' : 'Tạm dừng' }}</ToneBadge>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Nguồn: {{ i.source_name ?? 'mặc định theo nền tảng' }} · Chiến dịch: {{ i.campaign_name ?? 'không gắn' }} · Lead gần nhất:
                        {{ i.last_received_at ?? 'chưa có' }}
                    </p>
                </div>
                <div v-if="can.manage" class="flex gap-1">
                    <Button variant="ghost" size="icon" class="size-8" aria-label="Sửa" data-test="edit-integration" @click="openForm(i)"><Pencil class="size-4" /></Button>
                    <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá" data-test="delete-integration" @click="deleting = i"><Trash2 class="size-4" /></Button>
                </div>
            </div>

            <div class="grid gap-3 border-t p-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Địa chỉ webhook</p>
                    <div class="mt-1 flex gap-2">
                        <input readonly :value="i.webhook_url" class="h-9 min-w-0 flex-1 rounded-md border bg-muted/40 px-2 font-mono text-xs" aria-label="Địa chỉ webhook" data-test="webhook-url" @focus="selectAll" />
                        <Button type="button" variant="outline" size="sm" class="h-9" @click="copy(`u${i.id}`, i.webhook_url)">
                            <component :is="copied === `u${i.id}` ? Check : Copy" class="size-4" />{{ copied === `u${i.id}` ? 'Đã chép' : 'Chép' }}
                        </Button>
                    </div>
                </div>
                <div v-if="i.provider === 'facebook' && i.verify_token">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Verify token</p>
                    <div class="mt-1 flex gap-2">
                        <input readonly :value="i.verify_token" class="h-9 min-w-0 flex-1 rounded-md border bg-muted/40 px-2 font-mono text-xs" aria-label="Verify token" data-test="verify-token" @focus="selectAll" />
                        <Button type="button" variant="outline" size="sm" class="h-9" @click="copy(`v${i.id}`, i.verify_token ?? '')">
                            <component :is="copied === `v${i.id}` ? Check : Copy" class="size-4" />{{ copied === `v${i.id}` ? 'Đã chép' : 'Chép' }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t px-4 py-3 text-xs">
                <span class="text-muted-foreground">Lead đã nhận:</span>
                <span v-for="(n, status) in i.counts" :key="status" class="flex items-center gap-1">
                    <ToneBadge :tone="statusTone[status]">{{ n }}</ToneBadge>
                    <span class="text-muted-foreground">{{ countLabels[status] }}</span>
                </span>
                <button type="button" class="ml-auto flex items-center gap-1 text-primary hover:underline" :data-test="`toggle-events-${i.id}`" @click="expanded = expanded === i.id ? null : i.id">
                    Lead gần đây<ChevronDown class="size-4 transition-transform" :class="expanded === i.id ? 'rotate-180' : ''" />
                </button>
            </div>

            <div v-if="expanded === i.id" class="border-t" data-test="events-panel">
                <p v-if="i.events.length === 0" class="p-4 text-sm text-muted-foreground">Chưa nhận lead nào.</p>
                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Thời gian</th>
                            <th class="px-3 py-2 font-medium">Mã lead</th>
                            <th class="px-3 py-2 font-medium">Kết quả</th>
                            <th class="px-3 py-2 font-medium">Thí sinh / lỗi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in i.events" :key="e.id" class="border-t" data-test="event-row">
                            <td class="px-3 py-2">{{ e.at }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ e.external_id ?? '—' }}</td>
                            <td class="px-3 py-2"><ToneBadge :tone="statusTone[e.status]">{{ e.status_label }}</ToneBadge></td>
                            <td class="px-3 py-2">
                                <Link v-if="e.applicant" :href="`/applicants/${e.applicant.uuid}`" class="text-primary hover:underline">{{ e.applicant.name }}</Link>
                                <span v-else-if="e.error" class="text-destructive">{{ e.error }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <details class="border-t px-4 py-3 text-sm">
                <summary class="cursor-pointer font-medium">Cách kết nối với {{ i.provider_label }}</summary>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground">
                    <li v-for="s in steps[i.provider]" :key="s">{{ s }}</li>
                </ol>
            </details>
        </section>
    </div>

    <LeadIntegrationFormDialog v-model:open="formOpen" :record="editing" :options="options" />

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá tích hợp {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>Địa chỉ webhook ngừng hoạt động ngay. Thí sinh và hồ sơ đã tạo không bị ảnh hưởng.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-integration" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
