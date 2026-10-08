<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    label: string;
    value: string | number;
    hint?: string | null;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div data-slot="stat-card" class="rounded-xl border bg-card p-4 shadow-xs">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <span
                v-if="icon"
                data-slot="stat-card-icon"
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
