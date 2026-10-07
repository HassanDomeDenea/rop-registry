<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6"
    >
        <div class="w-full max-w-sm">
            <div class="mb-6 flex justify-center">
                <span
                    class="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm"
                >
                    <AppLogoIcon class="size-8" />
                </span>
            </div>
            <div class="rounded-2xl border bg-card p-7 shadow-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
