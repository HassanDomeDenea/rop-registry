<script setup lang="ts" generic="T extends string | number">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

export type CheckListOption<T> = { value: T; label: string };

/**
 * A set of toggle chips for ticking any number of entries of a list.
 * An empty selection is null: nothing was recorded.
 *
 * Options are plain labels, or value / label pairs when the record keeps an id.
 */
const model = defineModel<T[] | null>();

const props = defineProps<{ options: Array<T | CheckListOption<T>> }>();

const normalised = computed<CheckListOption<T>[]>(() =>
    props.options.map((option) =>
        typeof option === 'object'
            ? option
            : { value: option, label: String(option) },
    ),
);

// Entries saved on the record stay visible after they are removed from the list.
const entries = computed<CheckListOption<T>[]>(() => [
    ...normalised.value,
    ...(model.value ?? [])
        .filter(
            (value) =>
                !normalised.value.some((option) => option.value === value),
        )
        .map((value) => ({ value, label: String(value) })),
]);

function toggle(option: T) {
    const selected = model.value ?? [];
    const next = selected.includes(option)
        ? selected.filter((value) => value !== option)
        : entries.value
              .map((entry) => entry.value)
              .filter((value) => value === option || selected.includes(value));

    model.value = next.length ? next : null;
}
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <button
            v-for="option in entries"
            :key="option.value"
            type="button"
            role="checkbox"
            :aria-checked="model?.includes(option.value) ?? false"
            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm transition-colors"
            :class="
                model?.includes(option.value)
                    ? 'border-primary bg-primary/10 font-medium text-primary'
                    : 'border-input text-muted-foreground hover:bg-muted hover:text-foreground'
            "
            dir="auto"
            @click="toggle(option.value)"
        >
            <span
                class="flex size-3.5 items-center justify-center rounded-[4px] border"
                :class="
                    model?.includes(option.value)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-muted-foreground/50'
                "
            >
                <Check v-if="model?.includes(option.value)" class="size-3" />
            </span>
            {{ option.label }}
        </button>
    </div>
</template>
