<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { secretLabels } from './types';
import type { LeadIntegrationOptions, LeadIntegrationRow } from './types';

/**
 * Add or edit a lead ads integration. The platform is chosen on create and then fixed. Secrets are never shown back:
 * when editing, leave them blank to keep the saved ones. Server rules: LeadIntegrationRequest.
 */
const props = defineProps<{ record: LeadIntegrationRow | null; options: LeadIntegrationOptions }>();
const open = defineModel<boolean>('open', { default: false });

type MapRow = { name: string; key: string };

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref(blank());

function blank() {
    return {
        provider: props.options.providers[0]?.value ?? 'facebook',
        name: '',
        campaign_id: '' as number | string,
        source_id: '' as number | string,
        secret: '',
        verify_token: '',
        access_token: '',
        is_active: true,
        field_map: [] as MapRow[],
    };
}

const provider = computed(() => (isEdit.value ? props.record!.provider : form.value.provider));
const secretText = computed(() => secretLabels[provider.value] ?? secretLabels.facebook);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = r
        ? { ...blank(), provider: r.provider, name: r.name, campaign_id: r.campaign_id ?? '', source_id: r.source_id ?? '', is_active: r.is_active, field_map: r.field_map.map((m) => ({ ...m })) }
        : blank();
});

function submit(): void {
    const f = form.value;
    const body: Record<string, unknown> = {
        name: f.name,
        campaign_id: f.campaign_id === '' ? null : f.campaign_id,
        source_id: f.source_id === '' ? null : f.source_id,
        is_active: f.is_active,
        secret: f.secret,
        verify_token: f.verify_token,
        access_token: f.access_token,
        field_map: f.field_map,
    };

    if (!isEdit.value) {
        body.provider = f.provider;
    }

    router.visit(isEdit.value ? `/settings/lead-integrations/${props.record!.id}` : '/settings/lead-integrations', {
        method: isEdit.value ? 'put' : 'post',
        data: body as never,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl" data-test="lead-integration-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa tích hợp' : 'Thêm tích hợp lead ads' }}</DialogTitle>
                    <DialogDescription>Mỗi tích hợp có một địa chỉ webhook riêng để dán vào Facebook hoặc TikTok.</DialogDescription>
                </DialogHeader>

                <div v-if="!isEdit" class="grid gap-1.5">
                    <Label for="li-provider">Nền tảng <span class="text-destructive">*</span></Label>
                    <select id="li-provider" v-model="form.provider" :class="selectClass" data-test="li-provider">
                        <option v-for="p in options.providers" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <InputError :message="errors.provider" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="li-name">Tên <span class="text-destructive">*</span></Label>
                    <Input id="li-name" v-model="form.name" autocomplete="off" placeholder="VD: Form tuyển sinh 2027 - Facebook" data-test="li-name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="li-source">Nguồn lead</Label>
                        <select id="li-source" v-model="form.source_id" :class="selectClass" data-test="li-source">
                            <option value="">Mặc định theo nền tảng</option>
                            <option v-for="s in options.sources" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.source_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="li-campaign">Chiến dịch</Label>
                        <select id="li-campaign" v-model="form.campaign_id" :class="selectClass" data-test="li-campaign">
                            <option value="">Không gắn chiến dịch</option>
                            <option v-for="c in options.campaigns" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <InputError :message="errors.campaign_id" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="li-secret">{{ secretText.label }} <span v-if="!isEdit" class="text-destructive">*</span></Label>
                    <Input id="li-secret" v-model="form.secret" type="password" autocomplete="new-password" :placeholder="isEdit && record?.has_secret ? 'Đã lưu — để trống nếu giữ nguyên' : ''" data-test="li-secret" />
                    <p class="text-xs text-muted-foreground">{{ secretText.help }}</p>
                    <InputError :message="errors.secret" />
                </div>

                <template v-if="provider === 'facebook'">
                    <div class="grid gap-1.5">
                        <Label for="li-token">Page access token</Label>
                        <Input id="li-token" v-model="form.access_token" type="password" autocomplete="new-password" :placeholder="isEdit && record?.has_access_token ? 'Đã lưu — để trống nếu giữ nguyên' : ''" data-test="li-access-token" />
                        <p class="text-xs text-muted-foreground">Cần để hệ thống đọc nội dung lead từ Facebook (quyền leads_retrieval).</p>
                        <InputError :message="errors.access_token" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="li-verify">Verify token</Label>
                        <Input id="li-verify" v-model="form.verify_token" autocomplete="off" :placeholder="isEdit ? 'Để trống nếu giữ nguyên' : 'Để trống để hệ thống tự tạo'" data-test="li-verify-token" />
                        <p class="text-xs text-muted-foreground">Dán cùng giá trị này vào ô Verify token khi đăng ký webhook trên Facebook.</p>
                        <InputError :message="errors.verify_token" />
                    </div>
                </template>

                <fieldset class="grid gap-2 rounded-md border p-3">
                    <legend class="px-1 text-sm font-medium">Ghép trường (tuỳ chọn)</legend>
                    <p class="text-xs text-muted-foreground">Các trường quen thuộc (họ tên, số điện thoại, email, tỉnh, trường, ngành…) tự nhận ra. Chỉ thêm khi form quảng cáo dùng tên trường riêng.</p>
                    <div v-for="(row, i) in form.field_map" :key="i" class="grid grid-cols-[1fr_1fr_auto] items-center gap-2" data-test="li-map-row">
                        <Input v-model="row.name" autocomplete="off" placeholder="Tên trường trên form" aria-label="Tên trường trên form" />
                        <select v-model="row.key" :class="selectClass" aria-label="Ghép với">
                            <option value="">Chọn…</option>
                            <option v-for="(label, key) in options.lead_keys" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <Button type="button" variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Bỏ dòng" @click="form.field_map.splice(i, 1)"><Trash2 class="size-4" /></Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="w-fit" data-test="li-add-map" @click="form.field_map.push({ name: '', key: '' })"><Plus class="size-4" />Thêm dòng</Button>
                    <InputError :message="errors.field_map" />
                </fieldset>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :model-value="form.is_active" data-test="li-active" @update:model-value="(v) => (form.is_active = v === true)" />
                    Đang nhận lead (bỏ chọn để tạm dừng, webhook sẽ trả 404)
                </label>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="li-submit"><Spinner v-if="processing" />Lưu</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
