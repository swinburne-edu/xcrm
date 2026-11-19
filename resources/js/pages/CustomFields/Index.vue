<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import CustomFieldFormDialog from '@/components/custom/CustomFieldFormDialog.vue';
import type { CustomFieldOptions } from '@/components/custom/CustomFieldFormDialog.vue';
import type { CustomFieldDefinitionRow } from '@/components/custom/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Trường tuỳ chỉnh (task 2.5): the extra fields of each object (thí sinh, hồ sơ, trường THPT…). Values are stored in the
 * record's custom_data; the fields show up in the add/edit forms, on detail pages and (when filterable) in the list filters.
 */
const props = defineProps<{
    fields: CustomFieldDefinitionRow[];
    options: CustomFieldOptions;
    can: { manage: boolean };
}>();

const object = ref(props.options.objects[0]?.value ?? 'applicants');
const formOpen = ref(false);
const editing = ref<CustomFieldDefinitionRow | null>(null);
const deleting = ref<CustomFieldDefinitionRow | null>(null);

const rows = computed(() => props.fields.filter((f) => f.object === object.value));
const counts = computed(() => Object.fromEntries(props.options.objects.map((o) => [o.value, props.fields.filter((f) => f.object === o.value).length])));

function openForm(row: CustomFieldDefinitionRow | null): void {
    editing.value = row;
    formOpen.value = true;
}

function remove(): void {
    if (deleting.value) {
        router.delete(`/settings/custom-fields/${deleting.value.id}`, { preserveScroll: true, onFinish: () => (deleting.value = null) });
    }
}
</script>

<template>
    <Head title="Trường tuỳ chỉnh" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Trường tuỳ chỉnh</h1>
                <p class="text-sm text-muted-foreground">Thêm thông tin riêng của trường cho từng đối tượng mà không cần sửa hệ thống.</p>
            </div>
            <Button v-if="can.manage" data-test="add-custom-field" @click="openForm(null)"><Plus class="size-4" />Thêm trường</Button>
        </div>

        <div class="flex flex-wrap gap-1 border-b" role="tablist">
            <button
                v-for="o in options.objects"
                :key="o.value"
                type="button"
                role="tab"
                :aria-selected="object === o.value"
                :class="cn('-mb-px border-b-2 px-3 py-2 text-sm', object === o.value ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground')"
                :data-test="`tab-${o.value}`"
                @click="object = o.value"
            >
                {{ o.label }}<span v-if="counts[o.value]" class="ml-1 text-xs text-muted-foreground">({{ counts[o.value] }})</span>
            </button>
        </div>

        <p v-if="rows.length === 0" class="rounded-md border p-6 text-sm text-muted-foreground" data-test="custom-fields-empty">Chưa có trường tuỳ chỉnh nào cho đối tượng này.</p>
        <div v-else class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm" data-test="custom-fields-table">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">Tên hiển thị</th>
                        <th class="px-3 py-2 font-medium">Mã</th>
                        <th class="px-3 py-2 font-medium">Kiểu</th>
                        <th class="px-3 py-2 font-medium">Bắt buộc</th>
                        <th class="px-3 py-2 font-medium">Lọc được</th>
                        <th v-if="can.manage" class="w-24 px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in rows" :key="f.id" class="border-t" data-test="custom-field-row">
                        <td class="px-3 py-2 font-medium">{{ f.label }}<div v-if="f.options.length" class="text-xs font-normal text-muted-foreground">{{ f.options.join(', ') }}</div></td>
                        <td class="px-3 py-2 font-mono text-xs">{{ f.key }}</td>
                        <td class="px-3 py-2">{{ f.type_label }}</td>
                        <td class="px-3 py-2">{{ f.is_required ? 'Có' : '—' }}</td>
                        <td class="px-3 py-2">{{ f.is_filterable ? 'Có' : '—' }}</td>
                        <td v-if="can.manage" class="px-3 py-2">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" class="size-8" aria-label="Sửa" data-test="edit-custom-field" @click="openForm(f)"><Pencil class="size-4" /></Button>
                                <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Xoá" data-test="delete-custom-field" @click="deleting = f"><Trash2 class="size-4" /></Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <CustomFieldFormDialog v-model:open="formOpen" :record="editing" :options="options" :default-object="object" />

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá trường {{ deleting?.label }}?</DialogTitle>
                <DialogDescription>Trường biến mất khỏi form, trang chi tiết và bộ lọc. Giá trị đã nhập vẫn được giữ và hiện lại nếu tạo lại trường cùng mã.</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-custom-field" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
