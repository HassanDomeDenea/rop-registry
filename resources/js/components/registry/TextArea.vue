<script setup lang="ts">
const model = defineModel<string | number | boolean | null>();

withDefaults(defineProps<{ rows?: number; invalid?: boolean }>(), {
    rows: 3,
});
</script>

<template>
    <textarea
        :value="String(model ?? '')"
        :rows="rows"
        :aria-invalid="invalid || undefined"
        dir="auto"
        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30"
        @input="model = ($event.target as HTMLTextAreaElement).value || null"
    />
</template>
