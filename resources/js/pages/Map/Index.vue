<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { selectClass } from '@/components/campaigns/types';
import LeafletMap from '@/components/map/LeafletMap.vue';
import type { MapProvince, MapSchool, Metric } from '@/components/map/LeafletMap.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    cycle: { id: number; name: string } | null;
    provinces: MapProvince[];
    schools: MapSchool[];
    without_coordinates: number;
}>();

const metric = ref<Metric>('applicants');
const showSchools = ref(true);

const metrics: { value: Metric; label: string }[] = [
    { value: 'applicants', label: 'Thí sinh' },
    { value: 'leads', label: 'Hồ sơ trong mùa' },
    { value: 'enrolled', label: 'Nhập học' },
];

const top = computed(() =>
    [...props.provinces]
        .filter((p) => p[metric.value] > 0)
        .sort((a, b) => b[metric.value] - a[metric.value])
        .slice(0, 10),
);
</script>

<template>
    <Head title="Bản đồ" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Bản đồ</h1>
            <p class="text-sm text-muted-foreground">
                Vòng tròn đỏ là số liệu theo tỉnh (đặt tại tâm tỉnh, diện tích theo số lượng), chấm xanh là trường THPT có toạ độ.
                <template v-if="cycle">Hồ sơ và nhập học tính theo {{ cycle.name }}.</template>
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-4">
            <div class="grid gap-1.5">
                <Label for="map-metric">Hiển thị theo</Label>
                <select id="map-metric" v-model="metric" :class="selectClass" class="w-48" data-test="map-metric">
                    <option v-for="m in metrics" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
            <Label class="flex items-center gap-2 pb-2 font-normal">
                <Checkbox :model-value="showSchools" @update:model-value="(v) => (showSchools = v === true)" />Hiện trường THPT
            </Label>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_16rem]">
            <LeafletMap :provinces="provinces" :schools="schools" :metric="metric" :show-schools="showSchools" />

            <aside class="grid content-start gap-3 text-sm">
                <h2 class="font-semibold">Tỉnh dẫn đầu</h2>
                <p v-if="!top.length" class="text-muted-foreground" data-test="map-empty">Chưa có số liệu theo tỉnh.</p>
                <ol v-else class="grid gap-1" data-test="map-top">
                    <li v-for="p in top" :key="p.id" class="flex justify-between gap-2"><span>{{ p.name }}</span><span class="tabular-nums">{{ p[metric] }}</span></li>
                </ol>
                <p class="text-xs text-muted-foreground">{{ schools.length }} trường có toạ độ.<template v-if="without_coordinates"> {{ without_coordinates }} trường chưa có toạ độ, hãy nhập lat/lng ở trang trường.</template></p>
            </aside>
        </div>
    </div>
</template>
