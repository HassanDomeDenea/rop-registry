<script setup lang="ts">
import type { EnumOption } from '@/types';

/**
 * A row of toggle buttons for short option lists. Clicking the active option
 * again clears it when the control is nullable. An option with a short label
 * may carry its full name as a title, shown on hover and read by screen readers.
 */
const model = defineModel<string | number | boolean | null>();

const props = withDefaults(
    defineProps<{
        options: (EnumOption & { title?: string })[];
        nullable?: boolean;
    }>(),
    { nullable: true },
);

function select(value: string) {
    model.value = props.nullable && model.value === value ? null : value;
}
</script>

<template>
    <div
        data-slot="segmented-control"
        class="inline-flex flex-wrap gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="rounded-md px-3 py-1 text-sm transition-colors"
            :class="
                model === option.value
                    ? 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :aria-pressed="model === option.value"
            :title="option.title"
            :aria-label="option.title"
            @click="select(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
