<script setup lang="ts">
import { computed, ref } from 'vue';
import TextInput from '@/components/registry/TextInput.vue';

/**
 * A free-text input that offers matching entries of a list while typing.
 * Anything may be typed; the list only saves keystrokes.
 */
defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | null>();

const props = defineProps<{ options: string[] }>();

const open = ref(false);
const active = ref(-1);

// Arabic spelling variants (hamza forms, ta marbuta, diacritics) match each other.
const normalise = (value: string) =>
    value
        .toLowerCase()
        .replace(/[ً-ْـ]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .trim();

const matches = computed(() => {
    const typed = normalise(String(model.value ?? ''));

    return props.options
        .filter((option) => {
            const candidate = normalise(option);

            return candidate.includes(typed) && candidate !== typed;
        })
        .slice(0, 8);
});

function choose(option: string) {
    model.value = option;
    open.value = false;
}

function move(step: number) {
    if (!open.value) {
        open.value = true;

        return;
    }

    const count = matches.value.length;

    active.value = count ? (active.value + step + count) % count : -1;
}

function onEnter(event: KeyboardEvent) {
    const option = open.value ? matches.value[active.value] : undefined;

    if (option) {
        event.preventDefault();
        choose(option);
    }
}

function onInput() {
    open.value = true;
    active.value = -1;
}
</script>

<template>
    <div class="relative">
        <TextInput
            v-bind="$attrs"
            v-model="model"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open && matches.length > 0"
            @focus="open = true"
            @blur="open = false"
            @input="onInput"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter="onEnter"
            @keydown.esc="open = false"
        />
        <ul
            v-if="open && matches.length"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
            role="listbox"
        >
            <li
                v-for="(option, index) in matches"
                :key="option"
                role="option"
                :aria-selected="index === active"
                class="cursor-pointer rounded-sm px-2.5 py-1.5"
                :class="
                    index === active
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-muted'
                "
                dir="auto"
                @mousedown.prevent="choose(option)"
                @mousemove="active = index"
            >
                {{ option }}
            </li>
        </ul>
    </div>
</template>
