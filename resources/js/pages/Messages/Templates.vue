<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { selectClass, textareaClass } from '@/components/campaigns/types';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Template = { id: number; channel: string; channel_label: string; name: string; subject: string | null; body: string; provider_template_id: string | null; is_active: boolean };

defineProps<{
    templates: Template[];
    options: { channels: { value: string; label: string }[]; variables: { name: string; label: string }[] };
    can: { manage: boolean };
}>();

const open = ref(false);
const editing = ref<Template | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref(blank());
const body = ref<HTMLTextAreaElement | null>(null);
const deleting = ref<Template | null>(null);

function blank() {
    return { channel: 'sms', name: '', subject: '', body: '', provider_template_id: '', is_active: true };
}

const isEmail = computed(() => form.value.channel === 'email');
const isZns = computed(() => form.value.channel === 'zns');

function start(t: Template | null): void {
    editing.value = t;
    errors.value = {};
    form.value = t ? { channel: t.channel, name: t.name, subject: t.subject ?? '', body: t.body, provider_template_id: t.provider_template_id ?? '', is_active: t.is_active } : blank();
    open.value = true;
}

function insert(name: string): void {
    const el = body.value;
    const token = `{{${name}}}`;

    if (!el) {
        form.value.body += token;

        return;
    }

    const from = el.selectionStart ?? form.value.body.length;
    const to = el.selectionEnd ?? from;
    form.value.body = form.value.body.slice(0, from) + token + form.value.body.slice(to);
}

function submit(): void {
    const data = { ...form.value, subject: form.value.subject || null, provider_template_id: form.value.provider_template_id || null };

    router.visit(editing.value ? `/settings/message-templates/${editing.value.id}` : '/settings/message-templates', {
        method: editing.value ? 'put' : 'post',
        data,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}

function confirmDelete(): void {
    if (deleting.value) {
        router.delete(`/settings/message-templates/${deleting.value.id}`, { preserveScroll: true, onFinish: () => (deleting.value = null) });
    }
}
</script>

<template>
    <Head title="Mẫu tin nhắn" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Mẫu tin nhắn</h1>
                <p class="text-sm text-muted-foreground">Soạn một lần, gửi hàng loạt từ danh sách thí sinh. Dùng biến như <code v-text="'{{applicant.first_name}}'"></code> để cá nhân hoá.</p>
            </div>
            <Button v-if="can.manage" data-test="create-template" @click="start(null)"><Plus class="size-4" />Thêm mẫu</Button>
        </div>

        <p v-if="!templates.length" class="text-sm text-muted-foreground">Chưa có mẫu nào.</p>
        <ul v-else class="grid gap-3" data-test="templates">
            <li v-for="t in templates" :key="t.id" class="grid gap-1 rounded-md border p-3" data-test="template-row">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ t.name }}</span>
                    <ToneBadge tone="blue">{{ t.channel_label }}</ToneBadge>
                    <ToneBadge v-if="!t.is_active" tone="gray">Đang tắt</ToneBadge>
                    <span v-if="can.manage" class="ml-auto flex gap-1">
                        <Button variant="ghost" size="icon" class="size-8" aria-label="Sửa mẫu" @click="start(t)"><Pencil class="size-4" /></Button>
                        <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá mẫu" @click="deleting = t"><Trash2 class="size-4" /></Button>
                    </span>
                </div>
                <p v-if="t.subject" class="text-sm font-medium">{{ t.subject }}</p>
                <p class="text-sm whitespace-pre-line text-muted-foreground">{{ t.body }}</p>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl" data-test="template-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Sửa mẫu tin' : 'Thêm mẫu tin' }}</DialogTitle>
                    <DialogDescription>Bấm vào tên biến để chèn vào nội dung tại vị trí con trỏ.</DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="mt-channel">Kênh</Label>
                        <select id="mt-channel" v-model="form.channel" :class="selectClass" :disabled="!!editing" data-test="mt-channel">
                            <option v-for="c in options.channels" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <InputError :message="errors.channel" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mt-name">Tên mẫu <span class="text-destructive">*</span></Label>
                        <Input id="mt-name" v-model="form.name" autocomplete="off" data-test="mt-name" />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <div v-if="isEmail" class="grid gap-1.5">
                    <Label for="mt-subject">Tiêu đề email <span class="text-destructive">*</span></Label>
                    <Input id="mt-subject" v-model="form.subject" autocomplete="off" data-test="mt-subject" />
                    <InputError :message="errors.subject" />
                </div>

                <div v-if="isZns" class="grid gap-1.5">
                    <Label for="mt-provider">Mã mẫu ZNS đã được Zalo duyệt <span class="text-destructive">*</span></Label>
                    <Input id="mt-provider" v-model="form.provider_template_id" autocomplete="off" data-test="mt-provider" />
                    <p class="text-xs text-muted-foreground">Tên tham số trong mẫu Zalo phải trùng tên biến, thay dấu chấm bằng gạch dưới (vd. applicant_first_name).</p>
                    <InputError :message="errors.provider_template_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="mt-body">Nội dung <span class="text-destructive">*</span></Label>
                    <textarea id="mt-body" ref="body" v-model="form.body" rows="5" :class="textareaClass" data-test="mt-body"></textarea>
                    <div class="flex flex-wrap gap-1">
                        <button v-for="v in options.variables" :key="v.name" type="button" class="rounded border px-1.5 py-0.5 text-xs hover:bg-muted" :title="v.label" @click="insert(v.name)">{{ v.name }}</button>
                    </div>
                    <InputError :message="errors.body" />
                </div>

                <Label class="flex items-center gap-2 font-normal"><Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" />Đang dùng</Label>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="save-template">Lưu</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader><DialogTitle>Xoá mẫu {{ deleting?.name }}?</DialogTitle><DialogDescription>Lịch sử tin đã gửi vẫn được giữ.</DialogDescription></DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" @click="confirmDelete">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
