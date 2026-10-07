<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

/**
 * A set of toggle chips for ticking any number of entries of a list.
 * An empty selection is null: nothing was recorded.
 */
const model = defineModel<string[] | null>();

const props = defineProps<{ options: string[] }>();

// Entries saved on the record stay visible after they are removed from the list.
const entries = computed(() => [
    ...props.options,
    ...(model.value ?? []).filter((value) => !props.options.includes(value)),
]);

function toggle(option: string) {
    const selected = model.value ?? [];
    const next = selected.includes(option)
        ? selected.filter((value) => value !== option)
        : entries.value.filter(
              (value) => value === option || selected.includes(value),
          );

    model.value = next.length ? next : null;
}
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <button
            v-for="option in entries"
            :key="option"
            type="button"
            role="checkbox"
            :aria-checked="model?.includes(option) ?? false"
            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm transition-colors"
            :class="
                model?.includes(option)
                    ? 'border-primary bg-primary/10 font-medium text-primary'
                    : 'border-input text-muted-foreground hover:bg-muted hover:text-foreground'
            "
            dir="auto"
            @click="toggle(option)"
        >
            <span
                class="flex size-3.5 items-center justify-center rounded-[4px] border"
                :class="
                    model?.includes(option)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-muted-foreground/50'
                "
            >
                <Check v-if="model?.includes(option)" class="size-3" />
            </span>
            {{ option }}
        </button>
    </div>
</template>
