<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CampaignCosts from '@/components/campaigns/CampaignCosts.vue';
import type { CostRow } from '@/components/campaigns/CampaignCosts.vue';
import CampaignFormDialog from '@/components/campaigns/CampaignFormDialog.vue';
import { money } from '@/components/campaigns/types';
import type { CampaignOptions, CampaignRow, Kpi, Transition } from '@/components/campaigns/types';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    campaign: CampaignRow;
    children: { id: number; code: string; name: string; status: string; status_label: string }[];
    kpi: Kpi;
    costs: CostRow[];
    transitions: Transition[];
    options: CampaignOptions;
    can: { update: boolean; delete: boolean; costs: boolean };
}>();

const tones: Record<string, BadgeTone> = {
    draft: 'gray',
    pending_approval: 'amber',
    running: 'green',
    paused: 'violet',
    ended: 'blue',
};

const editOpen = ref(false);
const deleteOpen = ref(false);
const processing = ref(false);

function run(action: string): void {
    router.post(`/campaigns/${props.campaign.id}/transition`, { action }, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function confirmDelete(): void {
    router.delete(`/campaigns/${props.campaign.id}`, { onFinish: () => (deleteOpen.value = false) });
}

function progress(actual: number | null, target: number | null | undefined): string {
    if (target === null || target === undefined) {
        return actual === null ? '—' : String(actual);
    }

    return `${actual ?? '—'} / ${target}`;
}
</script>

<template>
    <Head :title="campaign.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link href="/campaigns" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    ><ArrowLeft class="size-3.5" />Chiến dịch</Link
                >
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ campaign.name }}</h1>
                    <ToneBadge :tone="tones[campaign.status] ?? 'gray'" data-test="status">{{ campaign.status_label }}</ToneBadge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <code>{{ campaign.code }}</code>
                    · {{ campaign.cycle_name }}
                    <template v-if="campaign.type_label"> · {{ campaign.type_label }}</template>
                    <template v-if="campaign.channel_label"> · {{ campaign.channel_label }}</template>
                    <template v-if="campaign.parent_name"> · thuộc <Link :href="`/campaigns/${campaign.parent_id}`" class="underline">{{ campaign.parent_name }}</Link></template>
                </p>
                <p v-if="campaign.approved_by_name" class="text-xs text-muted-foreground">
                    Duyệt bởi {{ campaign.approved_by_name }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="t in transitions"
                    :key="t.action"
                    :variant="t.action === 'approve' || t.action === 'submit' || t.action === 'resume' ? 'default' : 'outline'"
                    :disabled="processing"
                    :data-test="`action-${t.action}`"
                    @click="run(t.action)"
                    >{{ t.label }}</Button
                >
                <Button v-if="can.update" variant="outline" data-test="edit-campaign" @click="editOpen = true"><Pencil class="size-4" />Sửa</Button>
                <Button v-if="can.delete" variant="outline" class="text-destructive" data-test="delete-campaign" @click="deleteOpen = true"
                    ><Trash2 class="size-4" />Xoá</Button
                >
            </div>
        </div>

        <section class="grid gap-3" data-test="kpi">
            <h2 class="font-semibold">Hiệu quả</h2>
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-muted-foreground">Thí sinh</dt><dd>{{ progress(kpi.leads, campaign.target_leads) }}</dd></div>
                <div><dt class="text-muted-foreground">Hồ sơ</dt><dd>{{ progress(kpi.applications, campaign.target_applications) }}</dd></div>
                <div><dt class="text-muted-foreground">Nhập học</dt><dd>{{ progress(kpi.enrolled, campaign.target_enrolled) }}</dd></div>
                <div><dt class="text-muted-foreground">Ngân sách / chi phí thực</dt><dd>{{ money(campaign.budget) }} / {{ money(campaign.actual_cost) }}</dd></div>
                <div><dt class="text-muted-foreground">Chi phí / thí sinh</dt><dd>{{ money(kpi.cost_per_lead) }}</dd></div>
                <div><dt class="text-muted-foreground">Chi phí / nhập học</dt><dd>{{ money(kpi.cost_per_enrolled) }}</dd></div>
            </dl>
            <p v-if="!kpi.available" class="text-xs text-muted-foreground" data-test="kpi-note">
                Số liệu thí sinh và nhập học tính từ hồ sơ tuyển sinh gắn với chiến dịch (kể cả chiến dịch con).
            </p>
        </section>

        <CampaignCosts :campaign-id="campaign.id" :costs="costs" :can-edit="can.costs" />

        <section class="grid max-w-3xl gap-3 text-sm" data-test="details">
            <p v-if="campaign.start_date || campaign.end_date">
                <span class="text-muted-foreground">Thời gian:</span> {{ campaign.start_date || '…' }} → {{ campaign.end_date || '…' }}
            </p>
            <p><span class="text-muted-foreground">Người phụ trách:</span> {{ campaign.owner_name || '—' }}</p>
            <p v-if="campaign.landing_url">
                <span class="text-muted-foreground">Trang đích:</span> <a :href="campaign.landing_url" target="_blank" rel="noopener noreferrer" class="underline">{{ campaign.landing_url }}</a>
            </p>
            <p v-if="campaign.description" class="whitespace-pre-line">{{ campaign.description }}</p>

            <div v-for="[label, items] in ([['Ngành mục tiêu', campaign.targets?.majors], ['Tỉnh/thành phố mục tiêu', campaign.targets?.provinces], ['Trường THPT mục tiêu', campaign.targets?.high_schools]] as const)" :key="label">
                <template v-if="items && items.length">
                    <span class="text-muted-foreground">{{ label }}:</span>
                    {{ items.map((i) => i.label).join(', ') }}
                </template>
            </div>
        </section>

        <section v-if="children.length" class="grid gap-2" data-test="children">
            <h2 class="font-semibold">Chiến dịch con</h2>
            <ul class="grid gap-1 text-sm">
                <li v-for="c in children" :key="c.id">
                    <Link :href="`/campaigns/${c.id}`" class="underline">{{ c.name }}</Link>
                    <code class="ml-2 text-xs">{{ c.code }}</code>
                    <ToneBadge :tone="tones[c.status] ?? 'gray'" class="ml-2">{{ c.status_label }}</ToneBadge>
                </li>
            </ul>
        </section>
    </div>

    <CampaignFormDialog v-model:open="editOpen" :record="campaign" :cycle-id="campaign.cycle_id ?? null" :options="options" />

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá chiến dịch {{ campaign.name }}?</DialogTitle>
                <DialogDescription>Chỉ xoá được chiến dịch nháp. Chiến dịch đã chạy hãy chọn "Kết thúc".</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
