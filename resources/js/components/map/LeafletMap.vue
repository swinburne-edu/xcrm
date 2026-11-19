<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

export type MapProvince = { id: number; code: string; name: string; lat: number; lng: number; applicants: number; leads: number; enrolled: number };
export type MapSchool = { id: number; name: string; province: string | null; lat: number; lng: number; applicants: number; leads: number; enrolled: number };
export type Metric = 'applicants' | 'leads' | 'enrolled';

/**
 * Leaflet map of Vietnam: one heat circle per province (area follows the chosen metric) and a dot per high school.
 * Tiles come from OpenStreetMap. Circles are drawn at an approximate province centre, not at its boundary.
 */
const props = defineProps<{ provinces: MapProvince[]; schools: MapSchool[]; metric: Metric; showSchools: boolean }>();

const el = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let heat: L.LayerGroup | null = null;
let dots: L.LayerGroup | null = null;

const labels: Record<Metric, string> = { applicants: 'Thí sinh', leads: 'Hồ sơ trong mùa', enrolled: 'Nhập học' };
const escape = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);

function stats(row: { applicants: number; leads: number; enrolled: number }): string {
    return `Thí sinh: ${row.applicants}<br>Hồ sơ trong mùa: ${row.leads}<br>Nhập học: ${row.enrolled}`;
}

function draw(): void {
    if (!map) {
        return;
    }

    heat?.clearLayers();
    dots?.clearLayers();

    const max = Math.max(1, ...props.provinces.map((p) => p[props.metric]));

    for (const p of props.provinces) {
        const value = p[props.metric];

        if (value <= 0) {
            continue;
        }

        L.circleMarker([p.lat, p.lng], {
            radius: 8 + 30 * Math.sqrt(value / max),
            color: '#b91c1c',
            weight: 1,
            fillColor: '#ef4444',
            fillOpacity: 0.25 + 0.45 * (value / max),
        })
            .bindTooltip(`<strong>${escape(p.name)}</strong><br>${labels[props.metric]}: ${value}`)
            .addTo(heat!);
    }

    if (props.showSchools) {
        for (const s of props.schools) {
            L.circleMarker([s.lat, s.lng], { radius: 4, color: '#1d4ed8', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.9 })
                .bindPopup(`<strong>${escape(s.name)}</strong><br>${escape(s.province ?? '')}<br>${stats(s)}<br><a href="/high-schools/${s.id}">Xem trường</a>`)
                .addTo(dots!);
        }
    }
}

onMounted(() => {
    map = L.map(el.value!, { scrollWheelZoom: true }).setView([16.0, 106.0], 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
    heat = L.layerGroup().addTo(map);
    dots = L.layerGroup().addTo(map);
    draw();
});

watch(() => [props.provinces, props.schools, props.metric, props.showSchools], draw, { deep: true });

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div ref="el" class="z-0 h-[65vh] min-h-96 w-full rounded-md border" data-test="map"></div>
</template>
