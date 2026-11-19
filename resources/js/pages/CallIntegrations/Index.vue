<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Copy, KeyRound, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Integration = { id: number; name: string; is_active: boolean; webhook_url: string; last_received_at: string | null };

defineProps<{ integrations: Integration[]; new_secret: string | null; driver: string; can: { manage: boolean } }>();

const name = ref('');
const errors = ref<Record<string, string>>({});
const copied = ref<string | null>(null);

function create(): void {
    router.post('/settings/call-integrations', { name: name.value }, { preserveScroll: true, onError: (e) => (errors.value = e), onSuccess: () => (name.value = '') });
}

function toggle(i: Integration): void {
    router.put(`/settings/call-integrations/${i.id}`, { is_active: !i.is_active }, { preserveScroll: true });
}

function renew(i: Integration): void {
    router.put(`/settings/call-integrations/${i.id}`, { renew_secret: true }, { preserveScroll: true });
}

function remove(i: Integration): void {
    router.delete(`/settings/call-integrations/${i.id}`, { preserveScroll: true });
}

async function copy(text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = text;
        setTimeout(() => (copied.value = null), 1500);
    } catch {
        // Clipboard blocked: the text stays selectable.
    }
}
</script>

<template>
    <Head title="Tích hợp tổng đài" />

    <div class="flex max-w-3xl flex-col gap-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Tích hợp tổng đài</h1>
            <p class="text-sm text-muted-foreground">
                Cấu hình tổng đài gửi sự kiện cuộc gọi (đổ chuông, nhấc máy, kết thúc) tới địa chỉ webhook bên dưới, ký bằng khoá bí mật. Gọi đi (click-to-call) đang dùng driver
                <code>{{ driver }}</code>.
            </p>
        </div>

        <div v-if="new_secret" class="grid gap-1 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm" data-test="new-secret">
            <p class="font-medium">Khoá bí mật (chỉ hiện một lần, hãy lưu lại ngay):</p>
            <p class="flex items-center gap-2"><code class="break-all">{{ new_secret }}</code><Button variant="ghost" size="icon" class="size-7" aria-label="Chép khoá" @click="copy(new_secret)"><Copy class="size-3.5" /></Button></p>
        </div>

        <form v-if="can.manage" class="flex flex-wrap items-end gap-2" @submit.prevent="create">
            <div class="grid gap-1.5">
                <Label for="ci-name">Tên tích hợp</Label>
                <Input id="ci-name" v-model="name" class="w-64" placeholder="VD: Tổng đài chính" data-test="ci-name" />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" data-test="ci-create"><Plus class="size-4" />Thêm</Button>
        </form>

        <p v-if="!integrations.length" class="text-sm text-muted-foreground">Chưa có tích hợp nào.</p>
        <ul v-else class="grid gap-3" data-test="integrations">
            <li v-for="i in integrations" :key="i.id" class="grid gap-2 rounded-md border p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ i.name }}</span>
                    <ToneBadge :tone="i.is_active ? 'green' : 'gray'">{{ i.is_active ? 'Đang bật' : 'Đang tắt' }}</ToneBadge>
                    <span v-if="can.manage" class="ml-auto flex gap-1">
                        <Button variant="outline" size="sm" @click="toggle(i)">{{ i.is_active ? 'Tắt' : 'Bật' }}</Button>
                        <Button variant="outline" size="sm" title="Tạo khoá mới, khoá cũ hết hiệu lực" @click="renew(i)"><KeyRound class="size-3.5" />Đổi khoá</Button>
                        <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá" @click="remove(i)"><Trash2 class="size-4" /></Button>
                    </span>
                </div>
                <p class="flex items-center gap-2 text-sm"><code class="break-all">{{ i.webhook_url }}</code><Button variant="ghost" size="icon" class="size-7" aria-label="Chép địa chỉ" @click="copy(i.webhook_url)"><Copy class="size-3.5" /></Button></p>
                <p class="text-xs text-muted-foreground">Sự kiện gần nhất: {{ i.last_received_at ?? 'chưa có' }}</p>
            </li>
        </ul>

        <section class="grid gap-2 text-sm">
            <h2 class="font-semibold">Định dạng sự kiện</h2>
            <p class="text-muted-foreground">POST JSON tới địa chỉ webhook, kèm header <code>X-Signature: sha256=&lt;HMAC-SHA256 của nội dung gửi, khoá bí mật&gt;</code>.</p>
            <pre class="overflow-x-auto rounded-md border bg-muted p-3 text-xs">{
  "call_id": "abc-123",
  "event": "ringing | answered | ended",
  "direction": "in | out",
  "from": "0912345678",
  "to": "02812345678",
  "agent_email": "tuvan@truong.edu.vn",
  "duration_sec": 120,
  "recording_url": "https://..."
}</pre>
            <p class="text-muted-foreground">Cuộc gọi đến từ số lạ tự tạo thí sinh mới (nguồn Hotline). Cuộc gọi nhỡ tạo việc gọi lại cho người phụ trách.</p>
        </section>
    </div>
</template>
