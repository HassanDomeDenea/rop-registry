<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';

defineProps<{
    title: string;
    description?: string;
    backHref?: string;
}>();

const { isRtl, t } = useI18n();
</script>

<template>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <Link
                v-if="backHref"
                :href="backHref"
                class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-card text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                :aria-label="t('Back')"
            >
                <component
                    :is="isRtl ? ArrowRight : ArrowLeft"
                    class="size-4"
                />
            </Link>
            <div class="min-w-0">
                <h1
                    data-slot="page-title"
                    class="truncate text-2xl font-semibold tracking-tight"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description || $slots.description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
