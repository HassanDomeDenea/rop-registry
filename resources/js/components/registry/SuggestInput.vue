<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import {
    cleanLabel,
    isListed,
    useSuggestionList,
} from '@/composables/useSuggestionList';
import type { SuggestionListName } from '@/composables/useSuggestionList';

/**
 * A free-text input that offers matching entries of a list while typing.
 * Anything may be typed; the list only saves keystrokes.
 *
 * Given the list the options come from, a value that was typed in full and is not
 * an entry yet offers a plus button after the field to add it to that list.
 */
defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | null>();

const props = defineProps<{
    options: string[];
    list?: SuggestionListName;
}>();

const { t } = useI18n();
const { addToList } = useSuggestionList();

const open = ref(false);
const active = ref(-1);
/** Whether the value was changed and typing it is finished, rather than in progress. */
const committed = ref(false);

const canAdd = computed(
    () =>
        props.list !== undefined &&
        committed.value &&
        cleanLabel(model.value) !== '' &&
        !isListed(cleanLabel(model.value), props.options),
);

function add() {
    if (!props.list) {
        return;
    }

    addToList(props.list, cleanLabel(model.value), (label) => {
        model.value = label;
    });
}

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
    committed.value = false;
}
</script>

<template>
    <div class="flex items-center gap-1.5">
        <div class="relative min-w-0 flex-1">
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
                @change="committed = true"
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
        <Button
            v-if="canAdd"
            type="button"
            variant="outline"
            size="icon-sm"
            class="text-primary"
            :title="t('Add “:label” to the list', { label: cleanLabel(model) })"
            :aria-label="
                t('Add “:label” to the list', { label: cleanLabel(model) })
            "
            @click="add"
        >
            <Plus />
        </Button>
    </div>
</template>
