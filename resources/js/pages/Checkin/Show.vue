<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, GraduationCap, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';

/**
 * Trang điểm danh công khai sau mã QR của hoạt động tại trường (task 2.1, luồng F2). Không cần đăng nhập, dùng được
 * trên điện thoại: họ tên, số điện thoại, lớp, ngành quan tâm và đồng ý nhận thông tin.
 */
const props = defineProps<{
    activity: { title: string; type_label: string; school: string | null; location: string | null; start_at: string | null; organization: string | null };
    closed: 'cancelled' | 'done' | 'not_started' | 'ended' | null;
    opens_at: string | null;
    majors: { value: number; label: string }[];
}>();

const form = ref({ full_name: '', phone: '', class_name: '', major: '' as number | '', consent: false, hp: '' });
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const done = ref<{ message: string; repeat: boolean } | null>(null);

const closedMessage = computed(
    () =>
        ({
            cancelled: 'Hoạt động này đã bị huỷ.',
            done: 'Hoạt động đã diễn ra và đã đóng điểm danh. Cảm ơn bạn đã quan tâm!',
            ended: 'Đã hết thời gian điểm danh của hoạt động này.',
            not_started: props.opens_at ? `Chưa đến giờ điểm danh. Bạn quay lại từ ${formatDateTime(props.opens_at)} nhé.` : 'Chưa đến giờ điểm danh.',
        })[props.closed ?? 'done'],
);

async function submit(): Promise<void> {
    errors.value = {};
    processing.value = true;

    try {
        const data = await requestJson<{ ok: boolean; message: string; repeat: boolean }>('POST', window.location.pathname, {
            ...form.value,
            major: form.value.major === '' ? null : form.value.major,
        });
        done.value = { message: data.message, repeat: data.repeat };
    } catch (e) {
        const err = e as HttpError;
        errors.value = err.status === 429 ? { form: 'Bạn gửi quá nhanh, vui lòng đợi một chút rồi thử lại.' } : Object.keys(err.errors).length ? err.errors : { form: err.message };
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head :title="`Điểm danh - ${activity.title}`" />

    <div class="min-h-svh bg-muted/30 px-4 py-8">
        <main class="mx-auto grid w-full max-w-md gap-5 rounded-2xl border bg-background p-6 shadow-sm">
            <header class="grid gap-1 text-center">
                <GraduationCap class="mx-auto size-8 text-primary" />
                <p v-if="activity.organization" class="text-sm text-muted-foreground">{{ activity.organization }}</p>
                <h1 class="text-lg font-semibold" data-test="checkin-title">{{ activity.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ activity.type_label }}<template v-if="activity.school"> · {{ activity.school }}</template>
                    <template v-if="activity.start_at"><br />{{ formatDateTime(activity.start_at) }}</template>
                    <template v-if="activity.location"> · {{ activity.location }}</template>
                </p>
            </header>

            <section v-if="done" class="grid gap-3 text-center" data-test="checkin-done">
                <CheckCircle2 class="mx-auto size-12 text-green-600" />
                <p class="font-medium">{{ done.message }}</p>
            </section>

            <section v-else-if="closed" class="grid gap-3 text-center" data-test="checkin-closed">
                <Lock class="mx-auto size-10 text-muted-foreground" />
                <p class="text-sm">{{ closedMessage }}</p>
            </section>

            <form v-else class="grid gap-4" data-test="checkin-form" @submit.prevent="submit">
                <p class="text-sm text-muted-foreground">Điền thông tin để điểm danh và nhận tư vấn tuyển sinh.</p>

                <div class="grid gap-1.5">
                    <Label for="c-name">Họ và tên <span class="text-destructive">*</span></Label>
                    <Input id="c-name" v-model="form.full_name" autocomplete="name" data-test="checkin-name" />
                    <InputError :message="errors.full_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-phone">Số điện thoại <span class="text-destructive">*</span></Label>
                    <Input id="c-phone" v-model="form.phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" data-test="checkin-phone" />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid grid-cols-[7rem_1fr] gap-3">
                    <div class="grid gap-1.5">
                        <Label for="c-class">Lớp</Label>
                        <Input id="c-class" v-model="form.class_name" placeholder="12A1" data-test="checkin-class" />
                        <InputError :message="errors.class_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-major">Ngành quan tâm</Label>
                        <select id="c-major" v-model="form.major" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs" data-test="checkin-major">
                            <option value="">Chưa chọn</option>
                            <option v-for="m in majors" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <InputError :message="errors.major" />
                    </div>
                </div>

                <!-- honeypot: people never see or fill it -->
                <input v-model="form.hp" type="text" name="hp" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0" />

                <div class="grid gap-1">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :model-value="form.consent" class="mt-0.5" data-test="checkin-consent" @update:model-value="(v) => (form.consent = v === true)" />
                        <span>Tôi đồng ý để nhà trường liên hệ và gửi thông tin tuyển sinh qua số điện thoại này.</span>
                    </label>
                    <InputError :message="errors.consent" />
                </div>

                <InputError :message="errors.form ?? errors.activity" />
                <Button type="submit" size="lg" :disabled="processing" data-test="checkin-submit">
                    <Spinner v-if="processing" />
                    Điểm danh
                </Button>
            </form>
        </main>
    </div>
</template>
