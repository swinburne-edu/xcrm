<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Mail, MapPin, Pencil, Phone, Plus, School, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Timeline from '@/components/activity/Timeline.vue';
import ApplicantFormDialog from '@/components/applicants/ApplicantFormDialog.vue';
import CertificateFormDialog from '@/components/applicants/CertificateFormDialog.vue';
import GuardianFormDialog from '@/components/applicants/GuardianFormDialog.vue';
import ScoreGrid from '@/components/applicants/ScoreGrid.vue';
import ApplicationsTab from '@/components/applications/ApplicationsTab.vue';
import DocumentsTab from '@/components/applications/DocumentsTab.vue';
import type { ApplicationPanelProps, DocumentPanelProps } from '@/components/applications/types';
import type {
    ApplicantOptions,
    ApplicantRow,
    CertificateRow,
    GuardianRow,
    ScoreRow,
    SubjectRow,
} from '@/components/applicants/types';
import CustomFieldsCard from '@/components/custom/CustomFieldsCard.vue';
import type { CustomPanel } from '@/components/custom/types';
import StageBadge from '@/components/crm/StageBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    applicant: ApplicantRow;
    cases: { uuid: string; cycle: string | null; stage: string | null; stage_color: string | null; status: string; owner: string | null; source: string | null; next_action_at: string | null; last_activity_at: string | null }[];
    guardians: GuardianRow[];
    scores: ScoreRow[];
    certificates: CertificateRow[];
    subjects: SubjectRow[];
    application_panel: ApplicationPanelProps;
    document_panel: DocumentPanelProps;
    custom_fields: CustomPanel;
    options: ApplicantOptions;
    can: { update: boolean; delete: boolean };
}>();

type Tab = 'overview' | 'timeline' | 'scores' | 'applications' | 'documents' | 'appointments';
const validTabs: Tab[] = ['overview', 'timeline', 'scores', 'applications', 'documents', 'appointments'];
const requested = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
const tab = ref<Tab>(validTabs.includes(requested as Tab) ? (requested as Tab) : 'overview');
const tabs = computed<{ key: Tab; label: string; count?: number }[]>(() => [
    { key: 'overview', label: 'Tổng quan' },
    { key: 'timeline', label: 'Timeline' },
    { key: 'scores', label: 'Điểm', count: props.scores.length },
    { key: 'applications', label: 'Nguyện vọng', count: props.application_panel.items.length },
    { key: 'documents', label: 'Giấy tờ', count: props.document_panel.items.length },
    { key: 'appointments', label: 'Lịch hẹn' },
]);

/** The newest case (cases come newest cycle first): its stage is shown next to the name. */
const currentCase = computed(() => props.cases[0] ?? null);
const initials = computed(
    () =>
        props.applicant.full_name
            .split(/\s+/)
            .filter(Boolean)
            .slice(-2)
            .map((w) => w[0])
            .join('')
            .toUpperCase() || '?',
);
const later: Record<string, string> = {
    appointments: 'Lịch hẹn của thí sinh xem ở menu Lịch hẹn; đặt lịch mới từ thẻ hồ sơ.',
};

const editOpen = ref(typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('edit') === '1' && props.can.update);
const deleteOpen = ref(false);
const guardianOpen = ref(false);
const editingGuardian = ref<GuardianRow | null>(null);
const certificateOpen = ref(false);
const editingCertificate = ref<CertificateRow | null>(null);

type Removal = { kind: 'guardian' | 'certificate'; id: number; label: string };
const removal = ref<Removal | null>(null);

function openGuardian(g: GuardianRow | null): void {
    editingGuardian.value = g;
    guardianOpen.value = true;
}

function openCertificate(c: CertificateRow | null): void {
    editingCertificate.value = c;
    certificateOpen.value = true;
}

function confirmRemoval(): void {
    const r = removal.value;

    if (!r) {
        return;
    }

    router.delete(`/applicants/${props.applicant.uuid}/${r.kind === 'guardian' ? 'guardians' : 'certificates'}/${r.id}`, {
        preserveScroll: true,
        onFinish: () => (removal.value = null),
    });
}

function confirmDelete(): void {
    router.delete(`/applicants/${props.applicant.uuid}`, { onFinish: () => (deleteOpen.value = false) });
}

const tabClass = (key: string): string => (tab.value === key ? 'border-primary font-medium' : 'border-transparent text-muted-foreground');
</script>

<template>
    <Head :title="applicant.full_name" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <Link href="/applicants" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:underline"><ArrowLeft class="size-3.5" />Thí sinh</Link>

        <header class="rounded-xl border bg-card p-4 md:p-5" data-test="applicant-header">
            <div class="flex flex-wrap items-start gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary" aria-hidden="true">{{ initials }}</div>

                <div class="grid min-w-0 flex-1 gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold" data-test="applicant-name">{{ applicant.full_name }}</h1>
                        <StageBadge v-if="currentCase?.stage" :name="currentCase.stage" :color="currentCase.stage_color" data-test="header-stage" />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        <span class="font-mono text-xs">{{ applicant.code }}</span>
                        <template v-if="applicant.gender_label"> · {{ applicant.gender_label }}</template>
                        <template v-if="applicant.current_grade"> · khối {{ applicant.current_grade === 13 ? 'đã tốt nghiệp' : applicant.current_grade }}</template>
                        <template v-if="applicant.graduation_year"> · tốt nghiệp {{ applicant.graduation_year }}</template>
                    </p>
                    <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                        <li v-if="applicant.phone" class="inline-flex items-center gap-1.5" data-test="phone">
                            <Phone class="size-4 text-muted-foreground" />
                            <a v-if="!applicant.sensitive_masked" :href="`tel:${applicant.phone}`" class="hover:underline">{{ applicant.phone }}</a>
                            <span v-else>{{ applicant.phone }}</span>
                        </li>
                        <li v-if="applicant.email" class="inline-flex items-center gap-1.5"><Mail class="size-4 text-muted-foreground" /><a :href="`mailto:${applicant.email}`" class="break-all hover:underline">{{ applicant.email }}</a></li>
                        <li v-if="applicant.high_school_name" class="inline-flex items-center gap-1.5"><School class="size-4 text-muted-foreground" />{{ applicant.high_school_name }}</li>
                        <li v-if="applicant.province_name" class="inline-flex items-center gap-1.5"><MapPin class="size-4 text-muted-foreground" />{{ applicant.province_name }}</li>
                    </ul>
                </div>

                <div class="flex gap-2">
                    <Button v-if="can.update" variant="outline" data-test="edit-applicant" @click="editOpen = true"><Pencil class="size-4" />Sửa</Button>
                    <Button v-if="can.delete" variant="outline" class="text-destructive" data-test="delete-applicant" @click="deleteOpen = true"><Trash2 class="size-4" />Xoá</Button>
                </div>
            </div>
        </header>

        <div class="grid min-w-0 content-start gap-4">
            <main class="grid min-w-0 content-start gap-4">
                <div class="flex flex-wrap gap-1 border-b" role="tablist">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.key"
                        class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm"
                        :class="tabClass(t.key)"
                        :data-test="`tab-${t.key}`"
                        @click="tab = t.key"
                    >
                        {{ t.label }}<span v-if="t.count" class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground">{{ t.count }}</span>
                    </button>
                </div>

                <div v-if="tab === 'overview'" class="grid items-start gap-4 text-sm lg:grid-cols-2" data-test="info">
                    <div class="grid content-start gap-4">
                <section class="overflow-hidden rounded-lg border bg-card" data-test="case-card">
                    <h2 class="border-b bg-muted/40 px-4 py-2.5 font-semibold">Hồ sơ tuyển sinh</h2>
                    <p v-if="cases.length === 0" class="p-4 text-muted-foreground" data-test="no-case">Thí sinh chưa có hồ sơ tuyển sinh.</p>
                    <div v-for="c in cases" :key="c.uuid" class="grid gap-2 border-b p-4 last:border-b-0" data-test="case-item">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-muted-foreground">{{ c.cycle }}</span>
                            <StageBadge v-if="c.stage" :name="c.stage" :color="c.stage_color" />
                        </div>
                        <dl class="grid gap-1.5">
                            <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Phụ trách</dt><dd>{{ c.owner || 'Kho chung' }}</dd></div>
                            <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Nguồn</dt><dd>{{ c.source || '—' }}</dd></div>
                            <div v-if="c.next_action_at" class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Việc tiếp theo</dt><dd>{{ formatDateTime(c.next_action_at) }}</dd></div>
                        </dl>
                        <Link :href="`/cases/table?filter[q]=${applicant.code}&filter[owner]=all`" class="text-xs text-primary hover:underline">Mở trong danh sách hồ sơ</Link>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border bg-card">
                    <h2 class="border-b bg-muted/40 px-4 py-2.5 font-semibold">Liên hệ</h2>
                    <dl class="grid gap-2.5 p-4">
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Điện thoại</dt><dd>{{ applicant.phone || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Email</dt><dd class="break-all">{{ applicant.email || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Zalo</dt><dd>{{ applicant.zalo_id || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Facebook</dt><dd>{{ applicant.facebook_id || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Địa chỉ</dt><dd>{{ [applicant.address, applicant.ward_name, applicant.province_name].filter(Boolean).join(', ') || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2">
                            <dt class="text-muted-foreground">Nhận thông tin</dt>
                            <dd><ToneBadge :tone="applicant.consent_marketing ? 'green' : 'gray'">{{ applicant.consent_marketing ? 'Đồng ý' : 'Chưa đồng ý' }}</ToneBadge></dd>
                        </div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-lg border bg-card">
                    <h2 class="border-b bg-muted/40 px-4 py-2.5 font-semibold">Nguồn tiếp cận</h2>
                    <dl class="grid gap-2.5 p-4">
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Nguồn</dt><dd>{{ applicant.first_source_name || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Chiến dịch</dt><dd>{{ applicant.first_campaign_name || '—' }}</dd></div>
                        <div v-if="applicant.referrer_name" class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Người giới thiệu</dt><dd>{{ applicant.referrer_name }}</dd></div>
                    </dl>
                </section>
                    </div>
                    <div class="grid content-start gap-4">
                <section class="overflow-hidden rounded-lg border bg-card">
                    <h2 class="border-b bg-muted/40 px-4 py-2.5 font-semibold">Cá nhân và học tập</h2>
                    <dl class="grid gap-2.5 p-4">
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Ngày sinh</dt><dd>{{ applicant.dob || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">CCCD</dt><dd data-test="citizen-id">{{ applicant.citizen_id || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Trường THPT</dt><dd>{{ applicant.high_school_name || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Lớp</dt><dd>{{ applicant.class_name || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Khu vực ƯT</dt><dd>{{ applicant.priority_area_code || '—' }}</dd></div>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2"><dt class="text-muted-foreground">Đối tượng ƯT</dt><dd>{{ applicant.priority_group_code || '—' }}</dd></div>
                    </dl>
                </section>

                <CustomFieldsCard :panel="custom_fields" :can-edit="can.update" />

                <section class="overflow-hidden rounded-lg border bg-card" data-test="guardians">
                    <div class="flex items-center justify-between border-b bg-muted/40 px-4 py-1.5">
                        <h2 class="font-semibold">Phụ huynh<span v-if="guardians.length" class="ml-1 font-normal text-muted-foreground">({{ guardians.length }})</span></h2>
                        <Button v-if="can.update" size="sm" variant="ghost" data-test="add-guardian" @click="openGuardian(null)"><Plus class="size-4" />Thêm</Button>
                    </div>
                    <p v-if="guardians.length === 0" class="p-4 text-muted-foreground">Chưa có phụ huynh.</p>
                    <div v-for="g in guardians" :key="g.id" class="border-b p-3 last:border-b-0" data-test="guardian-row">
                        <div class="flex items-start justify-between gap-1">
                            <div class="min-w-0">
                                <span class="font-medium">{{ g.full_name }}</span>
                                <Star v-if="g.is_primary_contact" class="ml-1 inline size-3.5 fill-amber-400 text-amber-500" aria-label="Đầu mối chính" />
                                <div class="text-xs text-muted-foreground">{{ g.relationship_label || '—' }}<template v-if="g.is_decision_maker"> · người quyết định</template></div>
                                <div class="text-xs">{{ g.phone || '—' }}<template v-if="g.occupation"> · {{ g.occupation }}</template></div>
                            </div>
                            <div v-if="can.update" class="flex shrink-0">
                                <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" @click="openGuardian(g)"><Pencil class="size-3.5" /></Button>
                                <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Gỡ" @click="removal = { kind: 'guardian', id: g.id, label: g.full_name }"><Trash2 class="size-3.5" /></Button>
                            </div>
                        </div>
                    </div>
                </section>
                    </div>
                </div>

                <Timeline v-else-if="tab === 'timeline'" subject-type="applicant" :subject="applicant.uuid" />

                <template v-else-if="tab === 'scores'">
                    <ScoreGrid :applicant-uuid="applicant.uuid" :subjects="subjects" :scores="scores" :score-types="options.scoreTypes" :graduation-year="applicant.graduation_year" :can-edit="can.update" />

                    <section class="grid gap-2" data-test="certificates">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium">Chứng chỉ</h3>
                            <Button v-if="can.update" size="sm" variant="outline" data-test="add-certificate" @click="openCertificate(null)"><Plus class="size-4" />Thêm</Button>
                        </div>
                        <p v-if="certificates.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">Chưa có chứng chỉ.</p>
                        <ul class="grid gap-1 text-sm">
                            <li v-for="c in certificates" :key="c.id" class="flex items-center justify-between rounded-md border px-3 py-1.5" data-test="certificate-row">
                                <span>
                                    <span class="font-medium">{{ c.type_label }}</span>
                                    <template v-if="c.score"> {{ c.score }}</template>
                                    <span class="text-muted-foreground">
                                        <template v-if="c.issued_at"> · cấp {{ c.issued_at }}</template>
                                        <template v-if="c.expires_at"> · hết hạn {{ c.expires_at }}</template>
                                    </span>
                                </span>
                                <span v-if="can.update">
                                    <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" @click="openCertificate(c)"><Pencil class="size-3.5" /></Button>
                                    <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" @click="removal = { kind: 'certificate', id: c.id, label: c.type_label }"><Trash2 class="size-3.5" /></Button>
                                </span>
                            </li>
                        </ul>
                    </section>
                </template>

                <ApplicationsTab v-else-if="tab === 'applications'" :panel="application_panel" />

                <DocumentsTab v-else-if="tab === 'documents'" :applicant-uuid="applicant.uuid" :case-uuid="application_panel.case_uuid" :panel="document_panel" />

                <section v-else class="rounded-md border p-6 text-sm text-muted-foreground" :data-test="`${tab}-later`">{{ later[tab] }}</section>
            </main>

        </div>
    </div>

    <ApplicantFormDialog v-model:open="editOpen" :record="applicant" :options="options" />
    <GuardianFormDialog v-model:open="guardianOpen" :applicant-uuid="applicant.uuid" :record="editingGuardian" :relationships="options.relationships" />
    <CertificateFormDialog v-model:open="certificateOpen" :applicant-uuid="applicant.uuid" :record="editingCertificate" :types="options.certificateTypes" />

    <Dialog :open="removal !== null" @update:open="(v) => !v && (removal = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ removal?.kind === 'guardian' ? 'Gỡ phụ huynh' : 'Xoá chứng chỉ' }} {{ removal?.label }}?</DialogTitle>
                <DialogDescription>Thao tác này không thể hoàn tác.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="removal = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-remove" @click="confirmRemoval">Xác nhận</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá thí sinh {{ applicant.full_name }}?</DialogTitle>
                <DialogDescription>Thí sinh được ẩn khỏi danh sách (xoá mềm), không mất dữ liệu lịch sử.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
