<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';

/**
 * The save and cancel buttons of a long form, kept at the bottom of the screen
 * while the form is scrolled, so that it can be saved without scrolling back up.
 */
defineProps<{
    label: string;
    cancelHref: string;
    processing: boolean;
    dirty: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <div
        data-slot="form-save-bar"
        class="no-print sticky bottom-4 z-30 flex items-center justify-end gap-2 rounded-xl border bg-card/95 px-4 py-3 shadow-lg backdrop-blur"
    >
        <span
            v-if="dirty"
            class="me-auto inline-flex items-center gap-1.5 text-xs font-medium text-warning"
        >
            <span class="size-1.5 rounded-full bg-warning" />
            {{ t('Unsaved changes') }}
        </span>
        <Button as-child variant="outline">
            <Link :href="cancelHref">{{ t('Cancel') }}</Link>
        </Button>
        <Button type="submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{ label }}
        </Button>
    </div>
</template>
