<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import TierBadge from '@/components/crm/TierBadge.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import ContactFormDialog from '@/components/highschools/ContactFormDialog.vue';
import SchoolFormDialog from '@/components/highschools/SchoolFormDialog.vue';
import type { ContactRow, SchoolOptions, SchoolRow } from '@/components/highschools/types';
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
    school: SchoolRow;
    contacts: ContactRow[];
    options: SchoolOptions;
    can: {
        update: boolean;
        delete: boolean;
        contacts: { create: boolean; update: boolean; delete: boolean };
    };
}>();

const tones: Record<string, BadgeTone> = {
    not_contacted: 'gray',
    contacting: 'amber',
    partner: 'blue',
    mou_signed: 'green',
};

const tab = ref<'contacts' | 'applicants' | 'timeline'>('contacts');
const editOpen = ref(false);
const deleteOpen = ref(false);
const contactOpen = ref(false);
const editingContact = ref<ContactRow | null>(null);
const removing = ref<ContactRow | null>(null);

function openContact(contact: ContactRow | null): void {
    editingContact.value = contact;
    contactOpen.value = true;
}

function removeContact(): void {
    if (!removing.value) {
        return;
    }

    router.delete(`/high-schools/${props.school.id}/contacts/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => (removing.value = null),
    });
}

function confirmDelete(): void {
    router.delete(`/high-schools/${props.school.id}`, {
        onFinish: () => (deleteOpen.value = false),
    });
}

const tabClass = (name: string): string =>
    tab.value === name ? 'border-primary font-medium' : 'border-transparent text-muted-foreground';
</script>

<template>
    <Head :title="school.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link href="/high-schools" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    ><ArrowLeft class="size-3.5" />Trường THPT</Link
                >
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ school.name }}</h1>
                    <TierBadge v-if="school.tier" :tier="school.tier" />
                    <ToneBadge :tone="tones[school.partnership_status] ?? 'gray'">{{ school.partnership_label }}</ToneBadge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="school.code">{{ school.code }} · </template>
                    <template v-if="school.type_label">{{ school.type_label }} · </template>
                    {{ [school.address, school.ward_name, school.province_name].filter(Boolean).join(', ') || 'Chưa có địa chỉ' }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" data-test="edit-school" @click="editOpen = true"
                    ><Pencil class="size-4" />Sửa</Button
                >
                <Button v-if="can.delete" variant="outline" class="text-destructive" data-test="delete-school" @click="deleteOpen = true"
                    ><Trash2 class="size-4" />Xoá</Button
                >
            </div>
        </div>

        <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4" data-test="facts">
            <div><dt class="text-muted-foreground">Người phụ trách</dt><dd>{{ school.owner_name || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Học sinh khối 12</dt><dd>{{ school.grade12_students ?? '—' }}<template v-if="school.grade12_classes"> ({{ school.grade12_classes }} lớp)</template></dd></div>
            <div><dt class="text-muted-foreground">Khu vực ưu tiên</dt><dd>{{ school.priority_area_code || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Tổ hợp mạnh</dt><dd>{{ school.strong_combinations?.join(', ') || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Ngày ký MOU</dt><dd>{{ school.mou_signed_at || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Điện thoại</dt><dd>{{ school.phone || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Email</dt><dd>{{ school.email || '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Website</dt><dd>{{ school.website || '—' }}</dd></div>
        </dl>
        <p v-if="school.note" class="whitespace-pre-line text-sm text-muted-foreground">{{ school.note }}</p>

        <div class="flex gap-1 border-b">
            <button type="button" class="-mb-px border-b-2 px-3 py-2 text-sm" :class="tabClass('contacts')" data-test="tab-contacts" @click="tab = 'contacts'">
                Người liên hệ ({{ contacts.length }})
            </button>
            <button type="button" class="-mb-px border-b-2 px-3 py-2 text-sm" :class="tabClass('applicants')" data-test="tab-applicants" @click="tab = 'applicants'">
                Thí sinh
            </button>
            <button type="button" class="-mb-px border-b-2 px-3 py-2 text-sm" :class="tabClass('timeline')" data-test="tab-timeline" @click="tab = 'timeline'">
                Timeline
            </button>
        </div>

        <section v-if="tab === 'contacts'" class="grid gap-3" data-test="contacts">
            <div v-if="can.contacts.create" class="flex justify-end">
                <Button size="sm" data-test="add-contact" @click="openContact(null)"><Plus class="size-4" />Thêm người liên hệ</Button>
            </div>
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Họ tên</th>
                            <th class="px-3 py-2 font-medium">Chức vụ</th>
                            <th class="px-3 py-2 font-medium">Liên lạc</th>
                            <th class="px-3 py-2 font-medium">Quan hệ</th>
                            <th class="w-24 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in contacts" :key="c.id" class="border-t" data-test="contact-row">
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ c.full_name }}</span>
                                <Star v-if="c.is_primary" class="ml-1 inline size-3.5 fill-amber-400 text-amber-500" aria-label="Liên hệ chính" />
                            </td>
                            <td class="px-3 py-2">
                                {{ c.role_label || '—' }}<template v-if="c.homeroom_class"> · {{ c.homeroom_class }}</template>
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ c.phone || '—' }}<template v-if="c.zalo"> · Zalo {{ c.zalo }}</template></div>
                                <div v-if="c.email" class="text-xs text-muted-foreground">{{ c.email }}</div>
                            </td>
                            <td class="px-3 py-2">{{ c.relationship_level ? `${c.relationship_level}/5` : '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <Button v-if="can.contacts.update" variant="ghost" size="icon" class="size-8" aria-label="Sửa" @click="openContact(c)"
                                    ><Pencil class="size-4"
                                /></Button>
                                <Button v-if="can.contacts.delete" variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá" @click="removing = c"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </td>
                        </tr>
                        <tr v-if="contacts.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Chưa có người liên hệ.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="tab === 'applicants'" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="applicants-empty">
            Danh sách thí sinh của trường sẽ hiển thị tại đây khi module Thí sinh hoàn thiện.
        </section>

        <section v-else class="rounded-md border p-6 text-sm text-muted-foreground" data-test="timeline-empty">
            Timeline (ghi chú, cuộc gọi, hoạt động tại trường) sẽ hiển thị tại đây khi module Hoạt động hoàn thiện.
        </section>
    </div>

    <SchoolFormDialog v-model:open="editOpen" :record="school" :options="options" />
    <ContactFormDialog v-model:open="contactOpen" :school-id="school.id" :record="editingContact" :roles="options.contactRoles ?? []" />

    <Dialog :open="removing !== null" @update:open="(v) => !v && (removing = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ removing?.full_name }}?</DialogTitle>
                <DialogDescription>Thao tác này không thể hoàn tác.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="removing = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-remove-contact" @click="removeContact">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá trường {{ school.name }}?</DialogTitle>
                <DialogDescription>
                    Xoá luôn người liên hệ và vị trí của trường trong vùng phụ trách. Trường đã có thí sinh hoặc hoạt động không xoá được.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
