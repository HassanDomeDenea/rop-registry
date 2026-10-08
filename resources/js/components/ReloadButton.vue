<script setup lang="ts">
import { RotateCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/** A full reload, the same as the refresh button of the browser. */
function reload() {
    window.location.reload();
}
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="no-print [&_svg]:transition-transform [&_svg]:duration-300 active:[&_svg]:rotate-180"
        :title="t('Reload the page')"
        :aria-label="t('Reload the page')"
        @click="reload"
    >
        <RotateCw />
    </Button>
</template>
