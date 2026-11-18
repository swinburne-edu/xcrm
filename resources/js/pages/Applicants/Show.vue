<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ApplicantFormDialog from '@/components/applicants/ApplicantFormDialog.vue';
import CertificateFormDialog from '@/components/applicants/CertificateFormDialog.vue';
import GuardianFormDialog from '@/components/applicants/GuardianFormDialog.vue';
import ScoreGrid from '@/components/applicants/ScoreGrid.vue';
import type {
    ApplicantOptions,
    ApplicantRow,
    CertificateRow,
    GuardianRow,
    ScoreRow,
    SubjectRow,
} from '@/components/applicants/types';
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
    applicant: ApplicantRow;
    guardians: GuardianRow[];
    scores: ScoreRow[];
    certificates: CertificateRow[];
    subjects: SubjectRow[];
    options: ApplicantOptions;
    can: { update: boolean; delete: boolean };
}>();

type Tab = 'timeline' | 'scores' | 'applications' | 'documents' | 'appointments';
const tab = ref<Tab>('scores');
const tabs: { key: Tab; label: string }[] = [
    { key: 'timeline', label: 'Timeline' },
    { key: 'scores', label: 'Điểm' },
    { key: 'applications', label: 'Nguyện vọng' },
    { key: 'documents', label: 'Giấy tờ' },
    { key: 'appointments', label: 'Lịch hẹn' },
];
const later: Record<string, string> = {
    timeline: 'Timeline (ghi chú, cuộc gọi, thay đổi) sẽ hiển thị tại đây ở bước Hoạt động & Timeline.',
    applications: 'Nguyện vọng xét tuyển sẽ có khi module Hồ sơ tuyển sinh hoàn thiện.',
    documents: 'Giấy tờ minh chứng sẽ có ở bước quản lý giấy tờ.',
    appointments: 'Lịch hẹn sẽ có ở bước Hoạt động.',
};

const editOpen = ref(false);
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

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link href="/applicants" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    ><ArrowLeft class="size-3.5" />Thí sinh</Link
                >
                <h1 class="text-xl font-semibold">{{ applicant.full_name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ applicant.code }}
                    <template v-if="applicant.gender_label"> · {{ applicant.gender_label }}</template>
                    <template v-if="applicant.graduation_year"> · tốt nghiệp {{ applicant.graduation_year }}</template>
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" data-test="edit-applicant" @click="editOpen = true"><Pencil class="size-4" />Sửa</Button>
                <Button v-if="can.delete" variant="outline" class="text-destructive" data-test="delete-applicant" @click="deleteOpen = true"
                    ><Trash2 class="size-4" />Xoá</Button
                >
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr_16rem]">
            <aside class="grid content-start gap-5 text-sm" data-test="info">
                <dl class="grid gap-2">
                    <div><dt class="text-muted-foreground">Điện thoại</dt><dd data-test="phone">{{ applicant.phone || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Email</dt><dd>{{ applicant.email || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">CCCD</dt><dd data-test="citizen-id">{{ applicant.citizen_id || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Ngày sinh</dt><dd>{{ applicant.dob || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Zalo / Facebook</dt><dd>{{ [applicant.zalo_id, applicant.facebook_id].filter(Boolean).join(' · ') || '—' }}</dd></div>
                    <div>
                        <dt class="text-muted-foreground">Địa chỉ</dt>
                        <dd>{{ [applicant.address, applicant.ward_name, applicant.province_name].filter(Boolean).join(', ') || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Trường THPT</dt>
                        <dd>
                            {{ applicant.high_school_name || '—' }}
                            <template v-if="applicant.class_name"> · {{ applicant.class_name }}</template>
                            <template v-if="applicant.current_grade"> · khối {{ applicant.current_grade === 13 ? 'đã tốt nghiệp' : applicant.current_grade }}</template>
                        </dd>
                    </div>
                    <div><dt class="text-muted-foreground">KV / đối tượng ưu tiên</dt><dd>{{ applicant.priority_area_code || '—' }} / {{ applicant.priority_group_code || '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Nguồn / chiến dịch</dt><dd>{{ applicant.first_source_name || '—' }} / {{ applicant.first_campaign_name || '—' }}</dd></div>
                    <div v-if="applicant.referrer_name"><dt class="text-muted-foreground">Người giới thiệu</dt><dd>{{ applicant.referrer_name }}</dd></div>
                    <div><dt class="text-muted-foreground">Đồng ý nhận tin</dt><dd>{{ applicant.consent_marketing ? 'Có' : 'Không' }}</dd></div>
                </dl>

                <section class="grid gap-2" data-test="guardians">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Phụ huynh</h2>
                        <Button v-if="can.update" size="sm" variant="outline" data-test="add-guardian" @click="openGuardian(null)"><Plus class="size-4" />Thêm</Button>
                    </div>
                    <p v-if="guardians.length === 0" class="text-muted-foreground">Chưa có phụ huynh.</p>
                    <div v-for="g in guardians" :key="g.id" class="rounded-md border p-2" data-test="guardian-row">
                        <div class="flex items-start justify-between gap-1">
                            <div>
                                <span class="font-medium">{{ g.full_name }}</span>
                                <Star v-if="g.is_primary_contact" class="ml-1 inline size-3.5 fill-amber-400 text-amber-500" aria-label="Đầu mối chính" />
                                <div class="text-xs text-muted-foreground">
                                    {{ g.relationship_label || '—' }}<template v-if="g.is_decision_maker"> · người quyết định</template>
                                </div>
                                <div class="text-xs">{{ g.phone || '—' }}<template v-if="g.occupation"> · {{ g.occupation }}</template></div>
                            </div>
                            <div v-if="can.update" class="flex">
                                <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" @click="openGuardian(g)"><Pencil class="size-3.5" /></Button>
                                <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Gỡ" @click="removal = { kind: 'guardian', id: g.id, label: g.full_name }"
                                    ><Trash2 class="size-3.5"
                                /></Button>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>

            <main class="grid content-start gap-4">
                <div class="flex flex-wrap gap-1 border-b">
                    <button v-for="t in tabs" :key="t.key" type="button" class="-mb-px border-b-2 px-3 py-2 text-sm" :class="tabClass(t.key)" :data-test="`tab-${t.key}`" @click="tab = t.key">
                        {{ t.label }}
                    </button>
                </div>

                <template v-if="tab === 'scores'">
                    <ScoreGrid
                        :applicant-uuid="applicant.uuid"
                        :subjects="subjects"
                        :scores="scores"
                        :score-types="options.scoreTypes"
                        :graduation-year="applicant.graduation_year"
                        :can-edit="can.update"
                    />

                    <section class="grid gap-2" data-test="certificates">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium">Chứng chỉ</h3>
                            <Button v-if="can.update" size="sm" variant="outline" data-test="add-certificate" @click="openCertificate(null)"><Plus class="size-4" />Thêm</Button>
                        </div>
                        <p v-if="certificates.length === 0" class="text-sm text-muted-foreground">Chưa có chứng chỉ.</p>
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
                                    <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" @click="removal = { kind: 'certificate', id: c.id, label: c.type_label }"
                                        ><Trash2 class="size-3.5"
                                    /></Button>
                                </span>
                            </li>
                        </ul>
                    </section>
                </template>

                <section v-else class="rounded-md border p-6 text-sm text-muted-foreground" :data-test="`${tab}-later`">{{ later[tab] }}</section>
            </main>

            <aside class="grid content-start gap-2 text-sm" data-test="case-card">
                <h2 class="font-semibold">Hồ sơ mùa hiện tại</h2>
                <p class="text-muted-foreground">Giai đoạn, người phụ trách, nguồn và task của hồ sơ sẽ hiển thị tại đây khi module Hồ sơ tuyển sinh hoàn thiện.</p>
            </aside>
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
