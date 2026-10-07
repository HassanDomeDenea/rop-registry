<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import type { EnumOption } from '@/types';

/**
 * A styled native select. An empty selection is exposed as null, which the
 * registry uses throughout for "not recorded".
 */
const model = defineModel<string | number | boolean | null>();

withDefaults(
    defineProps<{
        options: EnumOption[] | { value: string | number; label: string }[];
        placeholder?: string;
        nullable?: boolean;
        id?: string;
        invalid?: boolean;
        size?: 'default' | 'sm';
    }>(),
    { nullable: true, size: 'default' },
);

function onChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;

    model.value = value === '' ? null : value;
}
</script>

<template>
    <div class="relative">
        <select
            :id="id"
            :value="model ?? ''"
            :aria-invalid="invalid || undefined"
            class="w-full appearance-none rounded-md border border-input bg-transparent ps-3 pe-8 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30"
            :class="[
                size === 'sm' ? 'h-8' : 'h-9',
                model === null || model === undefined || model === ''
                    ? 'text-muted-foreground'
                    : '',
            ]"
            @change="onChange"
        >
            <option v-if="nullable" value="">{{ placeholder ?? '—' }}</option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                class="text-foreground"
            >
                {{ option.label }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute end-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
