<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import EntityPicker from '@/components/crm/EntityPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { LookupItem } from '@/types';

/**
 * Add or edit an assignment rule: who gets a new case (strategy + people / team) and when the rule applies
 * (conditions). Server rules: AssignmentRuleRequest; engine: AssignmentEngine.
 */
export type RuleRow = {
    id: number;
    name: string;
    priority: number;
    strategy: string;
    strategy_label: string;
    is_active: boolean;
    team_id: number | null;
    team: { id: number; label: string } | null;
    user_ids: number[];
    users: { id: number; label: string }[];
    conditions: Record<string, (number | string)[]>;
    condition_labels: { key: string; title: string; values: string[] }[];
    items: Record<'campaigns' | 'provinces' | 'high_schools' | 'majors' | 'users' | 'teams', LookupItem[]>;
};

export type RuleOptions = {
    strategies: { value: string; label: string; needs_people: boolean }[];
    sources: { value: number; label: string }[];
    tiers: { value: string; label: string }[];
};

const props = defineProps<{ record: RuleRow | null; options: RuleOptions }>();
const open = defineModel<boolean>('open', { default: false });

const isEdit = computed(() => props.record !== null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref(blank());

function blank() {
    return {
        name: '',
        priority: 100 as number | string,
        strategy: 'round_robin',
        is_active: true,
        user_ids: [] as (number | string)[],
        team_id: null as number | string | null,
        source_ids: [] as number[],
        school_tiers: [] as string[],
        campaign_ids: [] as (number | string)[],
        province_ids: [] as (number | string)[],
        high_school_ids: [] as (number | string)[],
        major_ids: [] as (number | string)[],
    };
}

const needsPeople = computed(() => props.options.strategies.find((s) => s.value === form.value.strategy)?.needs_people ?? false);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    errors.value = {};
    const r = props.record;
    form.value = r
        ? {
              name: r.name,
              priority: r.priority,
              strategy: r.strategy,
              is_active: r.is_active,
              user_ids: [...r.user_ids],
              team_id: r.team_id,
              source_ids: (r.conditions.source_ids ?? []).map(Number),
              school_tiers: (r.conditions.school_tiers ?? []).map(String),
              campaign_ids: [...(r.conditions.campaign_ids ?? [])],
              province_ids: [...(r.conditions.province_ids ?? [])],
              high_school_ids: [...(r.conditions.high_school_ids ?? [])],
              major_ids: [...(r.conditions.major_ids ?? [])],
          }
        : blank();
});

function toggle<T>(list: T[], value: T): void {
    const i = list.indexOf(value);
    i === -1 ? list.push(value) : list.splice(i, 1);
}

function fieldError(key: string): string | undefined {
    return Object.entries(errors.value).find(([k]) => k === key || k.startsWith(`${key}.`))?.[1];
}

function submit(): void {
    const f = form.value;
    const payload = {
        name: f.name,
        priority: f.priority,
        strategy: f.strategy,
        is_active: f.is_active,
        user_ids: needsPeople.value ? f.user_ids : [],
        team_id: needsPeople.value ? f.team_id : null,
        conditions: {
            source_ids: f.source_ids,
            campaign_ids: f.campaign_ids,
            province_ids: f.province_ids,
            high_school_ids: f.high_school_ids,
            major_ids: f.major_ids,
            school_tiers: f.school_tiers,
        },
    };

    router.visit(isEdit.value ? `/settings/assignment-rules/${props.record!.id}` : '/settings/assignment-rules', {
        method: isEdit.value ? 'put' : 'post',
        data: payload as never,
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl" data-test="rule-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isEdit ? 'Sửa quy tắc phân bổ' : 'Thêm quy tắc phân bổ' }}</DialogTitle>
                    <DialogDescription>
                        Hồ sơ mới được thử lần lượt với các quy tắc theo độ ưu tiên (số nhỏ chạy trước). Quy tắc đầu tiên khớp điều kiện và tìm được người nhận sẽ quyết định.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                    <div class="grid gap-1.5">
                        <Label for="r-name">Tên quy tắc <span class="text-destructive">*</span></Label>
                        <Input id="r-name" v-model="form.name" autocomplete="off" data-test="rule-name" />
                        <InputError :message="fieldError('name')" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-priority">Độ ưu tiên</Label>
                        <Input id="r-priority" v-model="form.priority" type="number" min="1" max="9999" data-test="rule-priority" />
                        <InputError :message="fieldError('priority')" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="r-strategy">Cách phân bổ <span class="text-destructive">*</span></Label>
                    <select id="r-strategy" v-model="form.strategy" :class="selectClass" data-test="rule-strategy">
                        <option v-for="s in options.strategies" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <InputError :message="fieldError('strategy')" />
                </div>

                <template v-if="needsPeople">
                    <div class="grid gap-1.5">
                        <Label for="r-users">{{ form.strategy === 'fixed' ? 'Người phụ trách' : 'Người được nhận hồ sơ' }}</Label>
                        <EntityPicker id="r-users" v-model="form.user_ids" entity="users" multiple placeholder="Chọn người dùng" :initial-items="record?.items.users ?? []" />
                        <InputError :message="fieldError('user_ids')" />
                    </div>
                    <div v-if="form.strategy !== 'fixed'" class="grid gap-1.5">
                        <Label for="r-team">Nhóm <span class="font-normal text-muted-foreground">(tuỳ chọn; để trống danh sách người thì lấy thành viên nhóm)</span></Label>
                        <EntityPicker id="r-team" v-model="form.team_id" entity="teams" placeholder="Chọn nhóm" :initial-items="record?.items.teams ?? []" />
                        <InputError :message="fieldError('team_id')" />
                    </div>
                </template>
                <p v-else class="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
                    Hồ sơ được giao cho người/nhóm phụ trách vùng của trường THPT (hoặc tỉnh) của thí sinh, cấu hình ở Cài đặt → Vùng phụ trách. Nếu chưa có vùng, hệ thống thử quy tắc tiếp theo.
                </p>

                <fieldset class="grid gap-3 rounded-md border p-3">
                    <legend class="px-1 text-sm font-medium">Điều kiện áp dụng <span class="font-normal text-muted-foreground">— để trống = áp dụng cho mọi hồ sơ</span></legend>

                    <div class="grid gap-1.5">
                        <Label>Nguồn thí sinh</Label>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            <label v-for="s in options.sources" :key="s.value" class="flex items-center gap-1.5 text-sm">
                                <Checkbox :model-value="form.source_ids.includes(s.value)" @update:model-value="toggle(form.source_ids, s.value)" />{{ s.label }}
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Hạng trường THPT</Label>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            <label v-for="t in options.tiers" :key="t.value" class="flex items-center gap-1.5 text-sm">
                                <Checkbox :model-value="form.school_tiers.includes(t.value)" @update:model-value="toggle(form.school_tiers, t.value)" />{{ t.label }}
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="c-campaign">Chiến dịch</Label>
                            <EntityPicker id="c-campaign" v-model="form.campaign_ids" entity="campaigns" multiple placeholder="Mọi chiến dịch" :initial-items="record?.items.campaigns ?? []" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="c-province">Tỉnh/thành</Label>
                            <EntityPicker id="c-province" v-model="form.province_ids" entity="provinces" multiple placeholder="Mọi tỉnh" :initial-items="record?.items.provinces ?? []" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="c-school">Trường THPT</Label>
                            <EntityPicker id="c-school" v-model="form.high_school_ids" entity="high_schools" multiple placeholder="Mọi trường" :initial-items="record?.items.high_schools ?? []" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="c-major">Ngành quan tâm</Label>
                            <EntityPicker id="c-major" v-model="form.major_ids" entity="majors" multiple placeholder="Mọi ngành" :initial-items="record?.items.majors ?? []" />
                        </div>
                    </div>
                </fieldset>

                <div class="flex items-center gap-2">
                    <Checkbox id="r-active" :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" />
                    <Label for="r-active" class="font-normal">Đang áp dụng</Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="rule-submit">
                        <Spinner v-if="processing" />
                        {{ isEdit ? 'Lưu thay đổi' : 'Thêm quy tắc' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
