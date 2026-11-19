<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PortalShell from '@/components/portal/PortalShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { requestJson } from '@/lib/http';
import type { HttpError } from '@/lib/http';

defineProps<{ school: string }>();

const step = ref<'phone' | 'code' | 'name'>('phone');
const phone = ref('');
const code = ref('');
const fullName = ref('');
const errors = ref<Record<string, string>>({});
const busy = ref(false);

async function run(fn: () => Promise<void>): Promise<void> {
    busy.value = true;
    errors.value = {};

    try {
        await fn();
    } catch (e) {
        const err = e as HttpError;
        errors.value = Object.keys(err.errors).length ? err.errors : { form: err.message };
    } finally {
        busy.value = false;
    }
}

const sendCode = () => run(async () => {
    await requestJson('POST', '/cong-thi-sinh/ma-xac-thuc', { phone: phone.value });
    step.value = 'code';
});

const verify = () => run(async () => {
    const result = await requestJson<{ needs_name: boolean }>('POST', '/cong-thi-sinh/xac-thuc', { phone: phone.value, code: code.value });

    if (result.needs_name) {
        step.value = 'name';
    } else {
        router.visit('/cong-thi-sinh');
    }
});

const register = () => run(async () => {
    await requestJson('POST', '/cong-thi-sinh/dang-ky', { full_name: fullName.value });
    router.visit('/cong-thi-sinh');
});
</script>

<template>
    <Head title="Cổng thí sinh" />

    <PortalShell :school="school">
        <h1 class="text-2xl font-semibold tracking-tight">Cổng thí sinh</h1>
        <p class="mt-1 mb-6 text-sm text-muted-foreground">Đăng nhập bằng mã xác thực gửi qua SMS để xem hồ sơ, nhập điểm và nộp nguyện vọng.</p>

        <form v-if="step === 'phone'" class="grid gap-4" @submit.prevent="sendCode">
            <div class="grid gap-2">
                <Label for="portal-phone">Số điện thoại</Label>
                <Input id="portal-phone" v-model="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" class="h-11" data-test="portal-phone" />
                <InputError :message="errors.phone" />
            </div>
            <Button type="submit" size="lg" class="h-11" :disabled="busy" data-test="portal-send">Gửi mã xác thực</Button>
        </form>

        <form v-else-if="step === 'code'" class="grid gap-4" @submit.prevent="verify">
            <p class="text-sm">Mã gồm 6 số đã gửi tới <strong>{{ phone }}</strong>.</p>
            <div class="grid gap-2">
                <Label for="portal-code">Mã xác thực</Label>
                <Input id="portal-code" v-model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="h-11 text-center text-lg tracking-widest" data-test="portal-code" />
                <InputError :message="errors.code" />
            </div>
            <Button type="submit" size="lg" class="h-11" :disabled="busy" data-test="portal-verify">Đăng nhập</Button>
            <button type="button" class="text-sm text-muted-foreground underline" @click="step = 'phone'">Dùng số khác hoặc gửi lại mã</button>
        </form>

        <form v-else class="grid gap-4" @submit.prevent="register">
            <p class="text-sm">Số này chưa có hồ sơ. Nhập họ tên để tạo hồ sơ mới.</p>
            <div class="grid gap-2">
                <Label for="portal-name">Họ và tên</Label>
                <Input id="portal-name" v-model="fullName" autocomplete="name" class="h-11" data-test="portal-name" />
                <InputError :message="errors.full_name" />
            </div>
            <Button type="submit" size="lg" class="h-11" :disabled="busy" data-test="portal-register">Tạo hồ sơ</Button>
        </form>

        <InputError :message="errors.form" class="mt-3" />
    </PortalShell>
</template>
