<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { UserRound } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import FormField from '@/components/registry/FormField.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();
</script>

<template>
    <Head :title="t('Profile')" />

    <SectionCard
        :title="t('Profile')"
        :description="t('The name and email address used to sign in')"
        :icon="UserRound"
    >
        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-5"
            v-slot="{ errors, processing }"
        >
            <FormField :label="t('Name')" for="name" :error="errors.name">
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                />
            </FormField>

            <FormField
                :label="t('Email address')"
                for="email"
                :error="errors.email"
            >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    dir="ltr"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                />
            </FormField>

            <Button :disabled="processing" data-test="update-profile-button">
                {{ t('Save') }}
            </Button>
        </Form>
    </SectionCard>
</template>
