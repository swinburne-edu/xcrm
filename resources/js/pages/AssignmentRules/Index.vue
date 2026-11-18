<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import InputError from '@/components/InputError.vue';
import AssignmentRuleFormDialog from '@/components/settings/AssignmentRuleFormDialog.vue';
import type { RuleOptions, RuleRow } from '@/components/settings/AssignmentRuleFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * Quy tắc phân bổ & SLA (task 2.4): rules that decide who gets a new case, plus the organization's switches for
 * SLA alerts and automatic recall of idle cases (scheduler: crm:check-sla).
 */
const props = defineProps<{
    rules: RuleRow[];
    options: RuleOptions;
    settings: { sla_alerts: boolean; recall_after_days: number | null };
    stages: { name: string; sla_hours: number | null }[];
    can: { manage: boolean };
}>();

const formOpen = ref(false);
const editing = ref<RuleRow | null>(null);
const deleting = ref<RuleRow | null>(null);

const slaAlerts = ref(props.settings.sla_alerts);
const recallEnabled = ref(props.settings.recall_after_days !== null);
const recallDays = ref<number | string>(props.settings.recall_after_days ?? 14);
const saving = ref(false);
const settingsErrors = ref<Record<string, string>>({});

function openForm(rule: RuleRow | null): void {
    editing.value = rule;
    formOpen.value = true;
}

/** Switching a rule on or off sends the whole rule again (the endpoint validates it like an edit). */
function toggle(rule: RuleRow): void {
    router.put(
        `/settings/assignment-rules/${rule.id}`,
        {
            name: rule.name,
            priority: rule.priority,
            strategy: rule.strategy,
            is_active: !rule.is_active,
            user_ids: rule.user_ids,
            team_id: rule.team_id,
            conditions: rule.conditions,
        } as never,
        { preserveScroll: true, preserveState: true },
    );
}

function remove(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(`/settings/assignment-rules/${deleting.value.id}`, { preserveScroll: true, preserveState: true, onFinish: () => (deleting.value = null) });
}

function saveSettings(): void {
    router.put(
        '/settings/assignment-rules/settings',
        { sla_alerts: slaAlerts.value, recall_after_days: recallEnabled.value ? recallDays.value : null } as never,
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (saving.value = true),
            onError: (e) => (settingsErrors.value = e),
            onSuccess: () => (settingsErrors.value = {}),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Head title="Quy tắc phân bổ" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Quy tắc phân bổ &amp; SLA</h1>
                <p class="text-sm text-muted-foreground">Ai nhận hồ sơ mới, khi nào cảnh báo quá hạn và khi nào thu hồi hồ sơ không ai chăm sóc.</p>
            </div>
            <Button v-if="can.manage" data-test="add-rule" @click="openForm(null)"><Plus class="size-4" />Thêm quy tắc</Button>
        </div>

        <section class="grid gap-3" data-test="rules">
            <h2 class="font-medium">Quy tắc phân bổ hồ sơ mới</h2>
            <p class="text-sm text-muted-foreground">
                Hồ sơ mới (từ form, import hoặc nhập tay không chọn người) được thử lần lượt với các quy tắc đang áp dụng, số ưu tiên nhỏ chạy trước.
                Quy tắc đầu tiên khớp điều kiện <b>và</b> tìm được người nhận sẽ quyết định. Không quy tắc nào quyết định được thì dùng vùng phụ trách, rồi đến kho chung.
            </p>

            <p v-if="rules.length === 0" class="rounded-md border p-4 text-sm text-muted-foreground" data-test="rules-empty">
                Chưa có quy tắc nào: hồ sơ mới theo vùng phụ trách của trường/tỉnh, nếu không có thì vào kho chung.
            </p>

            <article v-for="r in rules" :key="r.id" class="grid gap-2 rounded-md border p-3 text-sm" :class="r.is_active ? '' : 'opacity-60'" data-test="rule-row">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 min-w-9 items-center justify-center rounded-full bg-muted px-2 text-xs font-semibold" title="Độ ưu tiên">{{ r.priority }}</span>
                        <div>
                            <div class="font-medium">{{ r.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ r.strategy_label }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <ToneBadge :tone="r.is_active ? 'green' : 'gray'">{{ r.is_active ? 'Đang áp dụng' : 'Tạm tắt' }}</ToneBadge>
                        <template v-if="can.manage">
                            <Button variant="ghost" size="sm" class="h-7" data-test="toggle-rule" @click="toggle(r)">{{ r.is_active ? 'Tắt' : 'Bật' }}</Button>
                            <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa" data-test="edit-rule" @click="openForm(r)"><Pencil class="size-3.5" /></Button>
                            <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá" data-test="delete-rule" @click="deleting = r"><Trash2 class="size-3.5" /></Button>
                        </template>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5 text-xs">
                    <template v-if="r.condition_labels.length">
                        <span v-for="c in r.condition_labels" :key="c.key" class="rounded-full border px-2 py-0.5"><span class="text-muted-foreground">{{ c.title }}:</span> {{ c.values.join(', ') }}</span>
                    </template>
                    <span v-else class="text-muted-foreground">Áp dụng cho mọi hồ sơ</span>
                </div>

                <div v-if="r.strategy !== 'territory'" class="text-xs text-muted-foreground">
                    Người nhận:
                    <template v-if="r.users.length">{{ r.users.map((u) => u.label).join(', ') }}</template>
                    <template v-else-if="r.team">thành viên nhóm {{ r.team.label }}</template>
                    <template v-if="r.users.length && r.team"> · nhóm {{ r.team.label }}</template>
                </div>
            </article>
        </section>

        <section class="grid gap-3 rounded-md border p-4" data-test="sla-settings">
            <h2 class="font-medium">SLA và thu hồi tự động</h2>

            <div class="grid gap-1">
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :model-value="slaAlerts" :disabled="!can.manage" data-test="sla-alerts" @update:model-value="(v) => (slaAlerts = v === true)" />
                    Báo trưởng nhóm khi hồ sơ quá hạn SLA của giai đoạn mà chưa có liên hệ
                </label>
                <p class="pl-6 text-xs text-muted-foreground">
                    Mỗi giai đoạn có SLA riêng (xem bên dưới, sửa ở Cài đặt → Pipeline). Hệ thống kiểm tra mỗi 15 phút và chỉ báo một lần cho mỗi lần hồ sơ vào giai đoạn hoặc được giao.
                </p>
            </div>

            <div class="grid gap-1">
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :model-value="recallEnabled" :disabled="!can.manage" data-test="recall-enabled" @update:model-value="(v) => (recallEnabled = v === true)" />
                    Tự thu hồi hồ sơ về kho chung khi không có hoạt động trong
                    <Input v-model="recallDays" type="number" min="1" max="365" class="h-8 w-20" :disabled="!can.manage || !recallEnabled" data-test="recall-days" />
                    ngày
                </label>
                <p class="pl-6 text-xs text-muted-foreground">
                    Không áp dụng cho hồ sơ đã nhập học / thất bại, mùa đã đóng, hoặc hồ sơ có ngày hành động tiếp theo trong tương lai. Người phụ trách cũ và trưởng nhóm được thông báo.
                </p>
                <InputError class="pl-6" :message="settingsErrors.recall_after_days" />
            </div>

            <Button v-if="can.manage" class="justify-self-start" :disabled="saving" data-test="save-settings" @click="saveSettings">
                <Spinner v-if="saving" />
                Lưu cấu hình
            </Button>

            <div v-if="stages.length" class="grid gap-1">
                <Label class="text-xs text-muted-foreground">SLA hiện tại theo giai đoạn</Label>
                <ul class="flex flex-wrap gap-2 text-xs">
                    <li v-for="s in stages" :key="s.name" class="rounded-full border px-2 py-0.5">{{ s.name }}: {{ s.sla_hours ? `${s.sla_hours} giờ` : 'không giới hạn' }}</li>
                </ul>
                <Link href="/settings/pipelines" class="text-xs text-primary hover:underline">Sửa SLA trong Pipeline</Link>
            </div>
        </section>
    </div>

    <AssignmentRuleFormDialog v-model:open="formOpen" :record="editing" :options="options" />

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá quy tắc {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>Hồ sơ đã được giao không đổi; từ nay hồ sơ mới không còn theo quy tắc này.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-rule" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
