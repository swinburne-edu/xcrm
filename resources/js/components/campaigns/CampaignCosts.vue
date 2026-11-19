<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { money } from '@/components/campaigns/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type CostRow = { id: number; date: string; amount: number; note: string | null };

/** Daily spending of a campaign: list plus an inline add/edit form (POST/PUT/DELETE /campaigns/{id}/costs). */
const props = defineProps<{ campaignId: number; costs: CostRow[]; canEdit: boolean }>();

const editingId = ref<number | 'new' | null>(null);
const form = ref({ date: '', amount: '' as string | number, note: '' });
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function start(cost?: CostRow): void {
    editingId.value = cost ? cost.id : 'new';
    form.value = cost
        ? { date: cost.date, amount: cost.amount, note: cost.note ?? '' }
        : { date: new Date().toISOString().slice(0, 10), amount: '', note: '' };
    errors.value = {};
}

function save(): void {
    const url = `/campaigns/${props.campaignId}/costs` + (editingId.value === 'new' ? '' : `/${editingId.value}`);
    const options = {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: () => (editingId.value = null),
        onError: (e: Record<string, string>) => (errors.value = e),
        onFinish: () => (processing.value = false),
    };
    const data = { ...form.value, note: form.value.note || null };

    if (editingId.value === 'new') {
        router.post(url, data, options);
    } else {
        router.put(url, data, options);
    }
}

function remove(cost: CostRow): void {
    router.delete(`/campaigns/${props.campaignId}/costs/${cost.id}`, { preserveScroll: true });
}
</script>

<template>
    <section class="grid gap-3" data-test="costs">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Chi phí theo ngày</h2>
            <Button v-if="canEdit && editingId === null" variant="outline" size="sm" data-test="add-cost" @click="start()"><Plus class="size-4" />Thêm chi phí</Button>
        </div>

        <form v-if="editingId !== null" class="grid max-w-2xl items-start gap-3 rounded-md border p-3 sm:grid-cols-[10rem_10rem_1fr_auto]" data-test="cost-form" @submit.prevent="save">
            <div class="grid gap-1">
                <Label for="cost-date">Ngày</Label>
                <Input id="cost-date" v-model="form.date" type="date" required />
                <p v-if="errors.date" class="text-xs text-destructive">{{ errors.date }}</p>
            </div>
            <div class="grid gap-1">
                <Label for="cost-amount">Số tiền (₫)</Label>
                <Input id="cost-amount" v-model="form.amount" type="number" min="0" step="1" required />
                <p v-if="errors.amount" class="text-xs text-destructive" data-test="cost-error">{{ errors.amount }}</p>
            </div>
            <div class="grid gap-1">
                <Label for="cost-note">Ghi chú</Label>
                <Input id="cost-note" v-model="form.note" maxlength="255" />
            </div>
            <div class="flex gap-2 sm:mt-6">
                <Button type="submit" size="sm" :disabled="processing" data-test="save-cost">Lưu</Button>
                <Button type="button" variant="outline" size="sm" @click="editingId = null">Huỷ</Button>
            </div>
        </form>

        <p v-if="!costs.length" class="text-sm text-muted-foreground">Chưa ghi nhận chi phí nào.</p>
        <table v-else class="w-full max-w-2xl text-sm">
            <thead>
                <tr class="border-b text-left text-muted-foreground"><th class="py-1 font-normal">Ngày</th><th class="py-1 text-right font-normal">Số tiền</th><th class="py-1 pl-4 font-normal">Ghi chú</th><th></th></tr>
            </thead>
            <tbody>
                <tr v-for="c in costs" :key="c.id" class="border-b" data-test="cost-row">
                    <td class="py-1.5">{{ c.date }}</td>
                    <td class="py-1.5 text-right tabular-nums">{{ money(c.amount) }}</td>
                    <td class="py-1.5 pl-4 text-muted-foreground">{{ c.note }}</td>
                    <td class="py-1.5 text-right whitespace-nowrap">
                        <template v-if="canEdit">
                            <Button variant="ghost" size="icon" class="size-7" aria-label="Sửa chi phí" @click="start(c)"><Pencil class="size-3.5" /></Button>
                            <Button variant="ghost" size="icon" class="size-7 text-destructive" aria-label="Xoá chi phí" data-test="delete-cost" @click="remove(c)"><Trash2 class="size-3.5" /></Button>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
