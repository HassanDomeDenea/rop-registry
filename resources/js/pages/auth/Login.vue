<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('Log in')" />

    <div class="mb-6 space-y-1.5 text-center">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ t('ROP Registry') }}
        </h1>
        <p class="text-sm text-muted-foreground">
            {{ t('Sign in with the administrator account to continue') }}
        </p>
    </div>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">{{ t('Email address') }}</Label>
            <Input
                id="email"
                type="email"
                name="email"
                dir="ltr"
                required
                v-focus
                :tabindex="1"
                autocomplete="email"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">{{ t('Password') }}</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
            />
            <InputError :message="errors.password" />
        </div>

        <!-- Every login is remembered until the administrator logs out. -->
        <input type="hidden" name="remember" value="on" />

        <Button
            type="submit"
            class="mt-2 w-full"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ t('Log in') }}
        </Button>
    </Form>
</template>
