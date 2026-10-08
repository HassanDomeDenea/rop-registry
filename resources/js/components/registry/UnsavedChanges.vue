<script setup lang="ts">
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import { useI18n } from '@/composables/useI18n';

/** The "unsaved changes" mark of a form, and the question asked before leaving it. */
const confirming = defineModel<boolean>('confirming', { default: false });

defineProps<{ dirty: boolean }>();

defineEmits<{ leave: [] }>();

const { t } = useI18n();
</script>

<template>
    <span class="contents">
        <span
            v-if="dirty"
            class="inline-flex items-center gap-1.5 rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning"
            role="status"
        >
            <span class="size-1.5 rounded-full bg-warning" />
            {{ t('Unsaved changes') }}
        </span>

        <ConfirmDialog
            v-model:open="confirming"
            :title="t('Leave without saving?')"
            :description="
                t(
                    'The changes on this page have not been saved and will be lost.',
                )
            "
            :confirm-label="t('Leave without saving')"
            @confirm="$emit('leave')"
        />
    </span>
</template>
