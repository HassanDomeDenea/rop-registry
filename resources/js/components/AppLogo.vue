<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
</script>

<template>
    <div
        data-slot="app-logo-mark"
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ms-1 grid flex-1 text-start text-sm">
        <span class="truncate leading-tight font-semibold">
            {{ t('ROP Registry') }}
        </span>
        <span class="truncate text-xs leading-tight text-sidebar-foreground/60">
            {{ t('Retinopathy of prematurity') }}
        </span>
    </div>
</template>
