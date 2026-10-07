<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import PasswordInput from '@/components/PasswordInput.vue';
import FormField from '@/components/registry/FormField.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

defineProps<{
    passwordRules: string;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('Password')" />

    <SectionCard
        :title="t('Change password')"
        :description="t('Use a long password that is not used anywhere else')"
        :icon="KeyRound"
    >
        <Form
            v-bind="SecurityController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-5"
            v-slot="{ errors, processing }"
        >
            <FormField
                :label="t('Current password')"
                for="current_password"
                :error="errors.current_password"
            >
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                />
            </FormField>

            <FormField
                :label="t('New password')"
                for="password"
                :error="errors.password"
            >
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
            </FormField>

            <FormField
                :label="t('Confirm password')"
                for="password_confirmation"
                :error="errors.password_confirmation"
            >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
            </FormField>

            <Button :disabled="processing" data-test="update-password-button">
                {{ t('Save') }}
            </Button>
        </Form>
    </SectionCard>
</template>
