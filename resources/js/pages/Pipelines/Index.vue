<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ToneBadge from '@/components/crm/ToneBadge.vue';
import type { BadgeTone } from '@/components/crm/ToneBadge.vue';
import PipelineFormDialog from '@/components/pipelines/PipelineFormDialog.vue';
import type { Option, PipelineRow } from '@/components/pipelines/PipelineFormDialog.vue';
import StageFormDialog from '@/components/pipelines/StageFormDialog.vue';
import type { StageRow } from '@/components/pipelines/StageFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Pipeline = PipelineRow & { level_label: string; cases_count: number; stages: StageRow[] };

defineProps<{
    pipelines: Pipeline[];
    options: { levels: Option[]; types: Option[]; colors: string[]; requirable: Option[] };
    can: { manage: boolean };
}>();

const tones: Record<string, BadgeTone> = { gray: 'gray', blue: 'blue', green: 'green', amber: 'amber', red: 'red', violet: 'violet', cyan: 'cyan', pink: 'pink' };

const pipelineOpen = ref(false);
const editingPipeline = ref<PipelineRow | null>(null);
const stageOpen = ref(false);
const stagePipelineId = ref(0);
const editingStage = ref<StageRow | null>(null);

type Removal = { kind: 'pipeline' | 'stage'; pipelineId: number; stageId?: number; label: string };
const removal = ref<Removal | null>(null);

function openPipeline(p: PipelineRow | null): void {
    editingPipeline.value = p;
    pipelineOpen.value = true;
}

function openStage(pipelineId: number, s: StageRow | null): void {
    stagePipelineId.value = pipelineId;
    editingStage.value = s;
    stageOpen.value = true;
}

function confirmRemoval(): void {
    const r = removal.value;

    if (!r) {
        return;
    }

    const url = r.kind === 'pipeline' ? `/settings/pipelines/${r.pipelineId}` : `/settings/pipelines/${r.pipelineId}/stages/${r.stageId}`;

    router.delete(url, { preserveScroll: true, onFinish: () => (removal.value = null) });
}
</script>

<template>
    <Head title="Pipeline" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Pipeline</h1>
                <p class="text-sm text-muted-foreground">
                    Các giai đoạn của hồ sơ tuyển sinh. Hồ sơ mới vào giai đoạn "đang xử lý" đầu tiên của pipeline mặc định; trạng thái hồ sơ (đang xử lý / nhập học / thất bại) theo loại giai đoạn.
                </p>
            </div>
            <Button v-if="can.manage" data-test="add-pipeline" @click="openPipeline(null)"><Plus class="size-4" />Thêm pipeline</Button>
        </div>

        <section v-for="p in pipelines" :key="p.id" class="grid gap-3" data-test="pipeline">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold">{{ p.name }}</h2>
                    <ToneBadge tone="gray">{{ p.level_label }}</ToneBadge>
                    <ToneBadge v-if="p.is_default" tone="blue"><Star class="mr-1 inline size-3" />Mặc định</ToneBadge>
                    <span class="text-xs text-muted-foreground">{{ p.cases_count }} hồ sơ</span>
                </div>
                <div v-if="can.manage" class="flex gap-2">
                    <Button size="sm" variant="outline" data-test="add-stage" @click="openStage(p.id, null)"><Plus class="size-4" />Thêm giai đoạn</Button>
                    <Button size="sm" variant="outline" data-test="edit-pipeline" @click="openPipeline(p)"><Pencil class="size-4" />Sửa</Button>
                    <Button
                        v-if="!p.is_default && p.cases_count === 0"
                        size="sm"
                        variant="outline"
                        class="text-destructive"
                        data-test="delete-pipeline"
                        @click="removal = { kind: 'pipeline', pipelineId: p.id, label: p.name }"
                        ><Trash2 class="size-4" />Xoá</Button
                    >
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Giai đoạn</th>
                            <th class="px-3 py-2 font-medium">Loại</th>
                            <th class="px-3 py-2 font-medium">Xác suất</th>
                            <th class="px-3 py-2 font-medium">SLA</th>
                            <th class="px-3 py-2 font-medium">Bắt buộc</th>
                            <th class="px-3 py-2 font-medium">Hồ sơ</th>
                            <th class="w-24 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(s, i) in p.stages" :key="s.id" class="border-t" data-test="stage-row">
                            <td class="px-3 py-2 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="px-3 py-2">
                                <ToneBadge :tone="tones[s.color ?? ''] ?? 'gray'">{{ s.name }}</ToneBadge>
                                <code class="ml-2 text-xs text-muted-foreground">{{ s.code }}</code>
                            </td>
                            <td class="px-3 py-2">{{ s.type_label }}</td>
                            <td class="px-3 py-2">{{ s.probability }}%</td>
                            <td class="px-3 py-2">{{ s.sla_hours ? `${s.sla_hours} giờ` : '—' }}</td>
                            <td class="px-3 py-2">{{ s.required_fields.length ? s.required_fields.length + ' trường' : '—' }}</td>
                            <td class="px-3 py-2">{{ s.cases_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <template v-if="can.manage">
                                    <Button variant="ghost" size="icon" class="size-8" aria-label="Sửa giai đoạn" @click="openStage(p.id, s)"><Pencil class="size-4" /></Button>
                                    <Button
                                        v-if="s.cases_count === 0"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-destructive"
                                        aria-label="Xoá giai đoạn"
                                        @click="removal = { kind: 'stage', pipelineId: p.id, stageId: s.id, label: s.name }"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p v-if="pipelines.length === 0" class="text-sm text-muted-foreground">Chưa có pipeline nào.</p>
    </div>

    <PipelineFormDialog v-model:open="pipelineOpen" :record="editingPipeline" :levels="options.levels" />
    <StageFormDialog
        v-model:open="stageOpen"
        :pipeline-id="stagePipelineId"
        :record="editingStage"
        :types="options.types"
        :colors="options.colors"
        :requirable="options.requirable"
    />

    <Dialog :open="removal !== null" @update:open="(v) => !v && (removal = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá {{ removal?.kind === 'pipeline' ? 'pipeline' : 'giai đoạn' }} {{ removal?.label }}?</DialogTitle>
                <DialogDescription>Thao tác này không thể hoàn tác.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="removal = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-remove" @click="confirmRemoval">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
