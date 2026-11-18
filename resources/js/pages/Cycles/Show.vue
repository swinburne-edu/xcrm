<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Pencil, Plus, RotateCcw, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import CloneCycleDialog from '@/components/cycles/CloneCycleDialog.vue';
import CycleFormDialog from '@/components/cycles/CycleFormDialog.vue';
import MethodFormDialog from '@/components/cycles/MethodFormDialog.vue';
import RoundFormDialog from '@/components/cycles/RoundFormDialog.vue';
import type {
    CycleRow,
    FormulaOptions,
    MethodRow,
    Option,
    RoundRow,
} from '@/components/cycles/types';
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
    cycle: CycleRow;
    rounds: RoundRow[];
    methods: MethodRow[];
    methodOptions: (Option & { used: boolean })[];
    levelOptions: Option[];
    statusOptions: Option[];
    roundTypeOptions: Option[];
    formulaOptions: FormulaOptions;
    can: { update: boolean; delete: boolean; reopen: boolean; clone: boolean };
}>();

const tones: Record<string, BadgeTone> = {
    preparing: 'amber',
    active: 'green',
    closed: 'gray',
};

const nonDirectCount = computed(
    () => props.methods.filter((m) => !m.is_direct).length,
);

const cycleOpen = ref(false);
const cloneOpen = ref(false);
const roundOpen = ref(false);
const editingRound = ref<RoundRow | null>(null);
const methodOpen = ref(false);
const editingMethod = ref<MethodRow | null>(null);

type Removal = { kind: 'cycle' | 'round' | 'method'; id: number; label: string };
const removal = ref<Removal | null>(null);
const removalOpen = ref(false);

const base = computed(() => `/settings/cycles/${props.cycle.id}`);

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [date, time] = value.split('T');
    const [y, m, d] = date.split('-');

    return `${d}/${m}/${y}${time ? ` ${time}` : ''}`;
}

function formatDate(value: string | null): string {
    return value ? formatDateTime(value) : '—';
}

function openRound(round: RoundRow | null): void {
    editingRound.value = round;
    roundOpen.value = true;
}

function openMethod(method: MethodRow | null): void {
    editingMethod.value = method;
    methodOpen.value = true;
}

function ask(kind: Removal['kind'], id: number, label: string): void {
    removal.value = { kind, id, label };
    removalOpen.value = true;
}

function confirmRemoval(): void {
    const r = removal.value;

    if (!r) {
        return;
    }

    const url =
        r.kind === 'cycle'
            ? base.value
            : `${base.value}/${r.kind === 'round' ? 'rounds' : 'methods'}/${r.id}`;

    router.delete(url, {
        preserveScroll: true,
        onFinish: () => (removalOpen.value = false),
    });
}

function setCurrent(): void {
    router.post(`${base.value}/current`, {}, { preserveScroll: true });
}

function reopen(): void {
    router.post(`${base.value}/reopen`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="cycle.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <Link
                    href="/settings/cycles"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                    ><ArrowLeft class="size-3.5" />Mùa tuyển sinh</Link
                >
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ cycle.name }}</h1>
                    <ToneBadge :tone="tones[cycle.status] ?? 'gray'">{{
                        cycle.status_label
                    }}</ToneBadge>
                    <ToneBadge v-if="cycle.is_current" tone="blue"
                        >Hiện tại</ToneBadge
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ cycle.level_label }} · năm {{ cycle.year }} ·
                    {{ formatDate(cycle.start_date) }} –
                    {{ formatDate(cycle.end_date) }}
                    <template v-if="cycle.target_enrolled">
                        · chỉ tiêu {{ cycle.target_enrolled }}</template
                    >
                    <template v-if="cycle.regulation_ref">
                        · {{ cycle.regulation_ref }}</template
                    >
                </p>
                <p
                    v-if="cycle.status === 'closed'"
                    class="text-sm text-amber-700 dark:text-amber-400"
                    data-test="closed-note"
                >
                    Mùa đã đóng — chỉ đọc. Mở lại mùa để chỉnh sửa.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.reopen"
                    variant="outline"
                    data-test="reopen"
                    @click="reopen"
                    ><RotateCcw class="size-4" />Mở lại mùa</Button
                >
                <Button
                    v-if="can.clone"
                    variant="outline"
                    data-test="clone-cycle"
                    @click="cloneOpen = true"
                    ><Copy class="size-4" />Sao chép sang mùa mới</Button
                >
                <Button
                    v-if="can.update && !cycle.is_current"
                    variant="outline"
                    data-test="set-current"
                    @click="setCurrent"
                    ><Star class="size-4" />Đặt làm mùa hiện tại</Button
                >
                <Button
                    v-if="can.update"
                    variant="outline"
                    data-test="edit-cycle"
                    @click="cycleOpen = true"
                    ><Pencil class="size-4" />Sửa</Button
                >
                <Button
                    v-if="can.delete"
                    variant="outline"
                    class="text-destructive"
                    data-test="delete-cycle"
                    @click="ask('cycle', cycle.id, cycle.name)"
                    ><Trash2 class="size-4" />Xoá</Button
                >
            </div>
        </div>

        <section class="grid gap-3" data-test="methods">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold">Phương thức xét tuyển</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ nonDirectCount }}/{{ formulaOptions.maxMethods }}
                        phương thức (không tính xét tuyển thẳng).
                    </p>
                </div>
                <Button
                    v-if="can.update"
                    size="sm"
                    data-test="add-method"
                    @click="openMethod(null)"
                    ><Plus class="size-4" />Thêm phương thức</Button
                >
            </div>
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Phương thức</th>
                            <th class="px-3 py-2 font-medium">Công thức điểm</th>
                            <th class="px-3 py-2 font-medium">Giấy tờ</th>
                            <th class="w-24 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in methods"
                            :key="m.id"
                            class="border-t"
                            data-test="method-row"
                        >
                            <td class="px-3 py-2">
                                <div class="font-medium">
                                    {{ m.display_name || m.method_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ m.method_code }}
                                    <ToneBadge v-if="m.is_direct" tone="violet"
                                        >Xét thẳng</ToneBadge
                                    >
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                {{ m.formula_summary ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                {{ m.required_documents.length || '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <template v-if="can.update">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Sửa phương thức"
                                        @click="openMethod(m)"
                                        ><Pencil class="size-4"
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-destructive"
                                        aria-label="Gỡ phương thức"
                                        @click="
                                            ask(
                                                'method',
                                                m.id,
                                                m.display_name ||
                                                    m.method_name ||
                                                    '',
                                            )
                                        "
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="methods.length === 0">
                            <td
                                colspan="4"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Chưa có phương thức nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3" data-test="rounds">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold">Đợt xét tuyển</h2>
                    <p class="text-sm text-muted-foreground">
                        Thời gian theo giờ Việt Nam.
                    </p>
                </div>
                <Button
                    v-if="can.update"
                    size="sm"
                    data-test="add-round"
                    @click="openRound(null)"
                    ><Plus class="size-4" />Thêm đợt</Button
                >
            </div>
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Đợt</th>
                            <th class="px-3 py-2 font-medium">Đăng ký</th>
                            <th class="px-3 py-2 font-medium">Công bố</th>
                            <th class="px-3 py-2 font-medium">Hạn xác nhận</th>
                            <th class="w-24 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in rounds"
                            :key="r.id"
                            class="border-t"
                            data-test="round-row"
                        >
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ r.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ r.type_label ?? '' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                {{ formatDateTime(r.apply_start) }} →
                                {{ formatDateTime(r.apply_end) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ formatDate(r.result_date) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ formatDateTime(r.confirm_deadline) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <template v-if="can.update">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Sửa đợt"
                                        @click="openRound(r)"
                                        ><Pencil class="size-4"
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-destructive"
                                        aria-label="Xoá đợt"
                                        @click="ask('round', r.id, r.name)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="rounds.length === 0">
                            <td
                                colspan="5"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Chưa có đợt nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <CloneCycleDialog v-model:open="cloneOpen" :source="cycle" />
    <CycleFormDialog
        v-model:open="cycleOpen"
        :record="cycle"
        :level-options="levelOptions"
        :status-options="statusOptions"
    />
    <RoundFormDialog
        v-model:open="roundOpen"
        :cycle-id="cycle.id"
        :record="editingRound"
        :type-options="roundTypeOptions"
    />
    <MethodFormDialog
        v-model:open="methodOpen"
        :cycle-id="cycle.id"
        :record="editingMethod"
        :method-options="methodOptions"
        :formula-options="formulaOptions"
    />

    <Dialog v-model:open="removalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    >{{
                        removal?.kind === 'cycle'
                            ? 'Xoá mùa'
                            : removal?.kind === 'round'
                              ? 'Xoá đợt'
                              : 'Gỡ phương thức'
                    }}
                    {{ removal?.label }}?</DialogTitle
                >
                <DialogDescription>
                    Thao tác này không thể hoàn tác.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="removalOpen = false"
                    >Huỷ</Button
                >
                <Button
                    variant="destructive"
                    data-test="confirm-delete"
                    @click="confirmRemoval"
                    >Xác nhận</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
