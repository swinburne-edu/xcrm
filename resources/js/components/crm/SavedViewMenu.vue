<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bookmark, Check, Globe, Pencil, Plus, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { buildListParams } from '@/composables/useListQuery';
import type { ListQuery } from '@/composables/useListQuery';
import type { FilterValue } from '@/types';

/**
 * Saved views of a list screen (task 2.6): apply a saved set of filters / sort / page size / visible columns,
 * save the current state as a new view, update, rename, share or delete one.
 *
 *   <SavedViewMenu :views="saved_views" :query="query" storage-key="applicants" />
 *
 * Applying visits the list page again with the view's query string; the columns go through the same
 * localStorage key the DataTable keeps them in. Cases pass `view-type` ("kanban" / "table") to switch boards.
 */
export type SavedViewConfig = {
    filters: Record<string, FilterValue>;
    sort: string | null;
    per_page: number | null;
    columns: Record<string, boolean> | null;
};

export type SavedViewItem = {
    id: number;
    name: string;
    is_shared: boolean;
    view_type: string;
    config: SavedViewConfig;
    can_edit: boolean;
};

export type SavedViewsProps = {
    object: string;
    paths: Record<string, string>;
    can_share: boolean;
    items: SavedViewItem[];
};

const props = withDefaults(defineProps<{ views: SavedViewsProps; query: ListQuery; storageKey?: string; viewType?: string }>(), { storageKey: undefined, viewType: 'table' });

const columnsKey = computed(() => (props.storageKey ? `xcrm:table:${props.storageKey}:columns` : null));

function readColumns(): Record<string, boolean> | null {
    try {
        const raw = columnsKey.value ? localStorage.getItem(columnsKey.value) : null;
        const parsed = raw ? (JSON.parse(raw) as Record<string, boolean>) : null;

        return parsed && Object.keys(parsed).length > 0 ? parsed : null;
    } catch {
        return null;
    }
}

function writeColumns(columns: Record<string, boolean> | null): void {
    try {
        if (!columnsKey.value) {
            return;
        }

        columns ? localStorage.setItem(columnsKey.value, JSON.stringify(columns)) : localStorage.removeItem(columnsKey.value);
    } catch {
        // Storage unavailable: filters and sort still apply.
    }
}

function currentConfig(): SavedViewConfig {
    return {
        filters: { ...props.query.filters.value },
        sort: props.query.sort.value,
        per_page: props.query.perPage.value,
        columns: readColumns(),
    };
}

/** Same filters / sort / page size, whatever the key order. */
function stable(value: unknown): string {
    return JSON.stringify(value, (_k, v) => (v && typeof v === 'object' && !Array.isArray(v) ? Object.fromEntries(Object.entries(v).sort(([a], [b]) => a.localeCompare(b))) : v));
}

const active = computed(
    () =>
        props.views.items.find(
            (v) =>
                v.view_type === props.viewType &&
                stable(v.config.filters ?? {}) === stable(props.query.filters.value) &&
                (v.config.sort ?? null) === (props.query.sort.value ?? null) &&
                (v.config.per_page ?? 25) === props.query.perPage.value,
        ) ?? null,
);

function apply(view: SavedViewItem): void {
    writeColumns(view.config.columns ?? null);
    router.get(
        props.views.paths[view.view_type] ?? window.location.pathname,
        buildListParams(view.config.filters ?? {}, view.config.sort ?? null, 1, view.config.per_page ?? 25) as never,
        { preserveState: false, preserveScroll: true },
    );
}

/* ---------- save / rename dialog ---------- */
const dialogOpen = ref(false);
const editing = ref<SavedViewItem | null>(null);
const name = ref('');
const shared = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const deleting = ref<SavedViewItem | null>(null);

function openDialog(view: SavedViewItem | null): void {
    editing.value = view;
    name.value = view?.name ?? '';
    shared.value = view?.is_shared ?? false;
    errors.value = {};
    dialogOpen.value = true;
}

function submit(): void {
    const body = { name: name.value, is_shared: shared.value };
    // A new view captures the current state; renaming keeps the view's own settings.
    const data = editing.value ? body : { ...body, object: props.views.object, view_type: props.viewType, config: currentConfig() };

    router.visit(editing.value ? `/saved-views/${editing.value.id}` : '/saved-views', {
        method: editing.value ? 'put' : 'post',
        data: data as never,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onError: (e) => (errors.value = e),
        onSuccess: () => (dialogOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

/** Overwrites the view with the filters, sort, page size and columns on screen now. */
function updateFromCurrent(view: SavedViewItem): void {
    router.put(
        `/saved-views/${view.id}`,
        { name: view.name, is_shared: view.is_shared, view_type: props.viewType, config: currentConfig() } as never,
        { preserveScroll: true, preserveState: true },
    );
}

function remove(): void {
    if (deleting.value) {
        router.delete(`/saved-views/${deleting.value.id}`, { preserveScroll: true, preserveState: true, onFinish: () => (deleting.value = null) });
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="h-8" data-test="saved-views">
                <Bookmark class="size-4" />
                <span class="max-w-40 truncate">{{ active ? active.name : 'Chế độ xem' }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-64">
            <DropdownMenuLabel>Chế độ xem đã lưu</DropdownMenuLabel>
            <p v-if="views.items.length === 0" class="px-2 py-1.5 text-xs text-muted-foreground" data-test="saved-views-empty">Chưa có chế độ xem nào.</p>
            <DropdownMenuItem v-for="v in views.items" :key="v.id" data-test="saved-view-item" @select="apply(v)">
                <Check class="size-4" :class="active?.id === v.id ? 'opacity-100' : 'opacity-0'" />
                <span class="flex-1 truncate">{{ v.name }}</span>
                <Globe v-if="v.is_shared" class="size-3.5 text-muted-foreground" aria-label="Dùng chung" />
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem data-test="save-view-new" @select="openDialog(null)"><Plus class="size-4" />Lưu bộ lọc hiện tại…</DropdownMenuItem>
            <template v-if="active?.can_edit">
                <DropdownMenuItem data-test="save-view-update" @select="updateFromCurrent(active)"><RefreshCw class="size-4" />Cập nhật “{{ active.name }}”</DropdownMenuItem>
                <DropdownMenuItem data-test="save-view-rename" @select="openDialog(active)"><Pencil class="size-4" />Đổi tên / chia sẻ</DropdownMenuItem>
                <DropdownMenuItem class="text-destructive" data-test="save-view-delete" @select="deleting = active"><Trash2 class="size-4" />Xoá chế độ xem</DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md" data-test="saved-view-form">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Đổi tên chế độ xem' : 'Lưu chế độ xem' }}</DialogTitle>
                    <DialogDescription>{{ editing ? 'Đổi tên hoặc chia sẻ chế độ xem.' : 'Lưu bộ lọc, thứ tự sắp xếp, số dòng mỗi trang và các cột đang hiển thị.' }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="sv-name">Tên chế độ xem <span class="text-destructive">*</span></Label>
                    <Input id="sv-name" v-model="name" autocomplete="off" placeholder="VD: Hồ sơ nóng chưa gọi" data-test="saved-view-name" />
                    <InputError :message="errors.name" />
                </div>
                <label v-if="views.can_share" class="flex items-center gap-2 text-sm">
                    <Checkbox :model-value="shared" data-test="saved-view-shared" @update:model-value="(v) => (shared = v === true)" />
                    Chia sẻ cho cả tổ chức
                </label>
                <InputError :message="errors.object ?? errors.view_type" />
                <DialogFooter>
                    <Button type="button" variant="outline" @click="dialogOpen = false">Huỷ</Button>
                    <Button type="submit" :disabled="processing" data-test="saved-view-submit"><Spinner v-if="processing" />Lưu</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(v) => !v && (deleting = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Xoá chế độ xem {{ deleting?.name }}?</DialogTitle>
                <DialogDescription>{{ deleting?.is_shared ? 'Chế độ xem dùng chung sẽ biến mất với mọi người.' : 'Dữ liệu trong danh sách không bị ảnh hưởng.' }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">Huỷ</Button>
                <Button variant="destructive" data-test="confirm-delete-view" @click="remove">Xoá</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
