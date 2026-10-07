<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { store } from '@/routes/password/confirm';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('Confirm password')" />

    <div class="mb-6 space-y-1.5 text-center">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ t('Confirm password') }}
        </h1>
        <p class="text-sm text-muted-foreground">
            {{ t('Please confirm your password before continuing.') }}
        </p>
    </div>

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">{{ t('Password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="w-full"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                {{ t('Confirm password') }}
            </Button>
        </div>
    </Form>
</template>
