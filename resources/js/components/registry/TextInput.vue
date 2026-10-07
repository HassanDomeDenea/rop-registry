<script setup lang="ts">
/**
 * A text, number or date input whose empty value is null ("not recorded").
 * Number inputs emit numbers.
 */
const model = defineModel<string | number | boolean | null>();

const props = withDefaults(
    defineProps<{ type?: string; invalid?: boolean }>(),
    { type: 'text' },
);

function onInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;

    if (value === '') {
        model.value = null;
    } else if (props.type === 'number') {
        model.value = Number.isNaN(Number(value)) ? null : Number(value);
    } else {
        model.value = value;
    }
}
</script>

<template>
    <input
        :type="type"
        :value="model ?? ''"
        :aria-invalid="invalid || undefined"
        class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30"
        @input="onInput"
    />
</template>
