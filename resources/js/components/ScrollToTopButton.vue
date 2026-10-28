<script setup lang="ts">
import { ArrowUp } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const { y } = useWindowScroll();

/** Shown once the page is scrolled past the first screen of a long form. */
const isVisible = computed(() => y.value > 300);

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-200"
        leave-to-class="translate-y-2 opacity-0"
    >
        <Button
            v-if="isVisible"
            variant="secondary"
            size="icon"
            class="no-print fixed end-6 bottom-6 z-40 size-10 rounded-full border shadow-lg in-[body:has([data-slot=form-save-bar])]:bottom-24"
            :title="t('Back to top')"
            :aria-label="t('Back to top')"
            @click="scrollToTop"
        >
            <ArrowUp />
        </Button>
    </Transition>
</template>
