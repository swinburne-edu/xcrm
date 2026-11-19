<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Copy, KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Token = { id: number; name: string; abilities: string[]; last_used_at: string | null; expires_at: string | null; created_at: string | null };
type Delivery = { id: number; event: string; status: string; attempts: number; response_code: number | null; error: string | null; created_at: string | null };
type Hook = { id: number; name: string; url: string; events: string[]; is_active: boolean; deliveries: Delivery[] };

const props = defineProps<{
    tokens: Token[];
    webhooks: Hook[];
    events: { value: string; label: string }[];
    new_secret: string | null;
    new_token: string | null;
    api_url: string;
    can: { tokens: boolean; webhooks: boolean };
}>();

const token = ref({ name: '', write: false, expires_in_days: '' as string | number });
const hook = ref({ name: '', url: '', events: props.events.map((e) => e.value), is_active: true });
const errors = ref<Record<string, string>>({});
const tones: Record<string, BadgeTone> = { delivered: 'green', pending: 'amber', failed: 'red' };
const statusLabel: Record<string, string> = { delivered: 'Đã gửi', pending: 'Đang chờ', failed: 'Lỗi' };

function createToken(): void {
    router.post('/settings/api/tokens', { ...token.value, expires_in_days: token.value.expires_in_days === '' ? null : token.value.expires_in_days }, { preserveScroll: true, onError: (e) => (errors.value = e), onSuccess: () => ((errors.value = {}), (token.value.name = '')) });
}

function createHook(): void {
    router.post('/settings/api/webhooks', hook.value, { preserveScroll: true, onError: (e) => (errors.value = e), onSuccess: () => ((errors.value = {}), (hook.value.name = ''), (hook.value.url = '')) });
}

const toggle = (h: Hook) => router.put(`/settings/api/webhooks/${h.id}`, { is_active: !h.is_active }, { preserveScroll: true });
const renew = (h: Hook) => router.put(`/settings/api/webhooks/${h.id}`, { ...h, renew_secret: true }, { preserveScroll: true });
const removeHook = (h: Hook) => router.delete(`/settings/api/webhooks/${h.id}`, { preserveScroll: true });
const resend = (h: Hook, d: Delivery) => router.post(`/settings/api/webhooks/${h.id}/deliveries/${d.id}/redeliver`, {}, { preserveScroll: true });
const revoke = (t: Token) => router.delete(`/settings/api/tokens/${t.id}`, { preserveScroll: true });

async function copy(text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Clipboard blocked: the text stays selectable.
    }
}

function toggleEvent(value: string, on: boolean): void {
    hook.value.events = on ? [...hook.value.events, value] : hook.value.events.filter((e) => e !== value);
}
</script>

<template>
    <Head title="API và Webhook" />

    <div class="flex max-w-4xl flex-col gap-8 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">API và Webhook</h1>
            <p class="text-sm text-muted-foreground">
                Cho hệ thống khác (đào tạo, kế toán) đọc và ghi dữ liệu tuyển sinh qua REST API, và nhận thông báo khi thí sinh trúng tuyển hoặc xác nhận nhập học. Địa chỉ gốc: <code>{{ api_url }}</code>
            </p>
        </div>

        <div v-if="new_token" class="grid gap-1 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm" data-test="new-token">
            <p class="font-medium">Token mới (chỉ hiện một lần, hãy lưu lại ngay):</p>
            <p class="flex items-center gap-2"><code class="break-all">{{ new_token }}</code><Button variant="ghost" size="icon" class="size-7" aria-label="Chép token" @click="copy(new_token)"><Copy class="size-3.5" /></Button></p>
        </div>
        <div v-if="new_secret" class="grid gap-1 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm" data-test="new-secret">
            <p class="font-medium">Khoá bí mật của webhook (chỉ hiện một lần):</p>
            <p class="flex items-center gap-2"><code class="break-all">{{ new_secret }}</code><Button variant="ghost" size="icon" class="size-7" aria-label="Chép khoá" @click="copy(new_secret)"><Copy class="size-3.5" /></Button></p>
        </div>

        <section v-if="can.tokens" class="grid gap-3" data-test="tokens">
            <h2 class="font-semibold">Token API của tôi</h2>
            <p class="text-sm text-muted-foreground">Token hoạt động như chính bạn: thấy và làm đúng những gì bạn được phép. Gửi kèm header <code>Authorization: Bearer &lt;token&gt;</code>.</p>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="createToken">
                <div class="grid gap-1.5"><Label for="tk-name">Tên token</Label><Input id="tk-name" v-model="token.name" class="w-56" placeholder="VD: Hệ thống đào tạo" data-test="tk-name" /><InputError :message="errors.name" /></div>
                <div class="grid gap-1.5"><Label for="tk-days">Hết hạn sau (ngày)</Label><Input id="tk-days" v-model="token.expires_in_days" type="number" min="1" max="730" class="w-32" placeholder="Không hết hạn" /></div>
                <Label class="flex items-center gap-2 pb-2 font-normal"><Checkbox :model-value="token.write" @update:model-value="(v) => (token.write = v === true)" />Cho phép ghi</Label>
                <Button type="submit" data-test="tk-create"><KeyRound class="size-4" />Tạo token</Button>
            </form>
            <ul class="grid gap-2">
                <li v-for="t in tokens" :key="t.id" class="flex flex-wrap items-center gap-2 rounded-md border p-2 text-sm" data-test="token-row">
                    <span class="font-medium">{{ t.name }}</span>
                    <ToneBadge tone="blue">{{ t.abilities.includes('write') ? 'Đọc và ghi' : 'Chỉ đọc' }}</ToneBadge>
                    <span class="text-xs text-muted-foreground">Dùng lần cuối: {{ t.last_used_at ?? 'chưa' }} · Hết hạn: {{ t.expires_at ?? 'không' }}</span>
                    <Button variant="ghost" size="icon" class="ml-auto size-7 text-destructive" aria-label="Thu hồi" @click="revoke(t)"><Trash2 class="size-4" /></Button>
                </li>
            </ul>
        </section>

        <section v-if="can.webhooks || webhooks.length" class="grid gap-3" data-test="webhooks">
            <h2 class="font-semibold">Webhook</h2>
            <p class="text-sm text-muted-foreground">Mỗi sự kiện được gửi bằng POST JSON, ký HMAC-SHA256 trong header <code>X-Xcrm-Signature</code>. Gửi lại tự động 4 lần nếu máy nhận lỗi. Chỉ nhận địa chỉ https công khai.</p>
            <form v-if="can.webhooks" class="grid gap-3 rounded-md border p-3" @submit.prevent="createHook">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5"><Label for="wh-name">Tên</Label><Input id="wh-name" v-model="hook.name" data-test="wh-name" /><InputError :message="errors.name" /></div>
                    <div class="grid gap-1.5"><Label for="wh-url">Địa chỉ nhận (https)</Label><Input id="wh-url" v-model="hook.url" placeholder="https://..." data-test="wh-url" /><InputError :message="errors.url" /></div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <Label v-for="e in events" :key="e.value" class="flex items-center gap-2 font-normal"><Checkbox :model-value="hook.events.includes(e.value)" @update:model-value="(v) => toggleEvent(e.value, v === true)" />{{ e.label }} <code class="text-xs">{{ e.value }}</code></Label>
                </div>
                <InputError :message="errors.events" />
                <div><Button type="submit" data-test="wh-create">Thêm webhook</Button></div>
            </form>

            <ul class="grid gap-3">
                <li v-for="h in webhooks" :key="h.id" class="grid gap-2 rounded-md border p-3" data-test="webhook-row">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ h.name }}</span>
                        <ToneBadge :tone="h.is_active ? 'green' : 'gray'">{{ h.is_active ? 'Đang bật' : 'Đang tắt' }}</ToneBadge>
                        <span v-if="can.webhooks" class="ml-auto flex gap-1">
                            <Button variant="outline" size="sm" @click="toggle(h)">{{ h.is_active ? 'Tắt' : 'Bật' }}</Button>
                            <Button variant="outline" size="sm" title="Tạo khoá mới, khoá cũ hết hiệu lực" @click="renew(h)">Đổi khoá</Button>
                            <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá webhook" @click="removeHook(h)"><Trash2 class="size-4" /></Button>
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground"><code class="break-all">{{ h.url }}</code> · {{ h.events.join(', ') }}</p>
                    <ul v-if="h.deliveries.length" class="grid gap-1 text-xs">
                        <li v-for="d in h.deliveries" :key="d.id" class="flex flex-wrap items-center gap-2">
                            <ToneBadge :tone="tones[d.status] ?? 'gray'">{{ statusLabel[d.status] ?? d.status }}</ToneBadge>
                            <span>{{ d.event }} · {{ d.created_at }} · lần thử {{ d.attempts }}<template v-if="d.response_code"> · HTTP {{ d.response_code }}</template></span>
                            <span v-if="d.error" class="text-destructive">{{ d.error }}</span>
                            <Button v-if="can.webhooks && d.status !== 'delivered'" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="resend(h, d)">Gửi lại</Button>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
    </div>
</template>
