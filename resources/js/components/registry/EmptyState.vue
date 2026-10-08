<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-10 text-center"
    >
        <span
            v-if="icon"
            data-slot="empty-state-icon"
            class="mb-3 flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <component :is="icon" class="size-5" />
        </span>
        <p class="text-sm font-medium">{{ title }}</p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
