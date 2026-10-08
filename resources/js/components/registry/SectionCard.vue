<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    title?: string;
    description?: string;
    icon?: LucideIcon;
    flush?: boolean;
}>();
</script>

<template>
    <section
        data-slot="section-card"
        class="rounded-xl border bg-card shadow-xs"
    >
        <header
            v-if="title || $slots.actions"
            class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3.5"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <component
                    :is="icon"
                    v-if="icon"
                    data-slot="section-card-icon"
                    class="size-4 shrink-0 text-muted-foreground"
                />
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-semibold">{{ title }}</h2>
                    <p v-if="description" class="text-xs text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="flush ? '' : 'p-5'">
            <slot />
        </div>
    </section>
</template>
