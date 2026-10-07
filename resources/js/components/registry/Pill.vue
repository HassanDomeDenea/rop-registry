<script setup lang="ts">
export type PillTone =
    | 'neutral'
    | 'info'
    | 'success'
    | 'warning'
    | 'danger'
    | 'eylea'
    | 'laser';

withDefaults(defineProps<{ tone?: PillTone }>(), { tone: 'neutral' });

const tones: Record<PillTone, string> = {
    neutral: 'bg-muted text-muted-foreground ring-border',
    info: 'bg-info/10 text-info ring-info/20',
    success: 'bg-success/10 text-success ring-success/20',
    warning: 'bg-warning/10 text-warning ring-warning/25',
    danger: 'bg-destructive/10 text-destructive ring-destructive/20',
    eylea: 'bg-eylea/10 text-eylea ring-eylea/25',
    laser: 'bg-laser/10 text-laser ring-laser/25',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset [&>svg]:size-3"
        :class="tones[tone]"
    >
        <slot />
    </span>
</template>
