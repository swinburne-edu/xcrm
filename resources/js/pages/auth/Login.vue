<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Đăng nhập',
        description: 'Nhập tài khoản được nhà trường cấp để tiếp tục.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Đăng nhập" />

    <div v-if="status" class="mb-5 rounded-md border bg-muted px-3 py-2 text-center text-sm font-medium">
        {{ status }}
    </div>

    <PasskeyVerify label="Đăng nhập bằng passkey" loading-label="Đang xác thực…" separator="Hoặc dùng email" />

    <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="flex flex-col gap-5">
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input id="email" type="email" name="email" required v-focus :tabindex="1" autocomplete="email" placeholder="ten@truong.edu.vn" class="h-11" />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password">Mật khẩu</Label>
                <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">Quên mật khẩu?</TextLink>
            </div>
            <PasswordInput id="password" name="password" required :tabindex="2" autocomplete="current-password" placeholder="Nhập mật khẩu" class="h-11" />
            <InputError :message="errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-2.5 font-normal">
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <span>Ghi nhớ đăng nhập</span>
        </Label>

        <Button type="submit" size="lg" class="h-11 w-full" :tabindex="4" :disabled="processing" data-test="login-button">
            <Spinner v-if="processing" />
            {{ processing ? 'Đang đăng nhập…' : 'Đăng nhập' }}
        </Button>

        <p class="text-center text-xs text-muted-foreground">Chưa có tài khoản? Liên hệ quản trị hệ thống để được cấp.</p>
    </Form>
</template>
