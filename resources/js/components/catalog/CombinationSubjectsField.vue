<script setup lang="ts">
import { computed } from 'vue';
import type { CombinationSubjectRow } from '@/components/catalog/types';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import { Input } from '@/components/ui/input';
import { formatNumber } from '@/lib/format';

/**
 * Exactly 3 subjects with weights (rule R2 — validated on the server):
 * Toán or Ngữ văn is required and must weigh ≥ 1/3 of the total.
 */
const rows = defineModel<CombinationSubjectRow[]>({ required: true });

const total = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.weight) || 0), 0),
);

function update(index: number, patch: Partial<CombinationSubjectRow>): void {
    rows.value = rows.value.map((row, i) =>
        i === index ? { ...row, ...patch } : row,
    );
}
</script>

<template>
    <div class="grid gap-2" data-test="combination-subjects">
        <div
            class="grid grid-cols-[1fr_6rem] gap-2 text-xs font-medium text-muted-foreground"
        >
            <span>Môn</span>
            <span>Trọng số</span>
        </div>
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid grid-cols-[1fr_6rem] items-center gap-2"
        >
            <EntityPicker
                :model-value="row.subject_id"
                entity="subjects"
                :placeholder="`Môn ${index + 1}`"
                search-placeholder="Tìm môn…"
                :clearable="false"
                :initial-items="
                    row.subject_id && row.name
                        ? [
                              {
                                  id: row.subject_id,
                                  label: row.name,
                                  description: row.code ?? null,
                              },
                          ]
                        : []
                "
                @update:model-value="
                    (value) =>
                        update(index, {
                            subject_id: value as number | string | null,
                        })
                "
            />
            <Input
                :model-value="row.weight"
                type="number"
                min="0.25"
                max="10"
                step="0.25"
                :aria-label="`Trọng số môn ${index + 1}`"
                @update:model-value="
                    (value) => update(index, { weight: value })
                "
            />
        </div>
        <p class="text-xs text-muted-foreground">
            Tổng trọng số: {{ formatNumber(total) }}. Bắt buộc có Toán hoặc Ngữ
            văn, trọng số môn đó ≥ 1/3 tổng (≥ {{ formatNumber(total / 3) }}).
        </p>
    </div>
</template>
