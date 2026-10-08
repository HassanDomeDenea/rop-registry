<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ReloadButton from '@/components/ReloadButton.vue';
</script>

<template>
    <div
        data-slot="auth-shell"
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6"
    >
        <ReloadButton class="fixed end-4 top-4" />
        <div class="w-full max-w-sm">
            <div class="mb-6 flex justify-center">
                <span
                    class="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm"
                >
                    <AppLogoIcon class="size-8" />
                </span>
            </div>
            <div
                data-slot="auth-card"
                class="rounded-2xl border bg-card p-7 shadow-sm"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
