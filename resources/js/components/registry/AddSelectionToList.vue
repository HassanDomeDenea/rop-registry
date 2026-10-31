<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';
import {
    cleanLabel,
    isListed,
    useSuggestionList,
} from '@/composables/useSuggestionList';
import type { SuggestionListName } from '@/composables/useSuggestionList';

/**
 * Wraps a free-text field so that text selected in it, when not yet an entry of the
 * list, can be added to that list with the small plus button that then appears.
 */
const props = defineProps<{
    list: SuggestionListName;
    options: string[];
}>();

const emit = defineEmits<{ added: [label: string] }>();

const { t } = useI18n();
const { addToList } = useSuggestionList();

const selection = ref('');

const canAdd = computed(() => {
    const label = cleanLabel(selection.value);

    return (
        label !== '' && label.length <= 100 && !isListed(label, props.options)
    );
});

function readSelection(event: Event) {
    const field = event.target;

    if (
        !(field instanceof HTMLInputElement) &&
        !(field instanceof HTMLTextAreaElement)
    ) {
        return;
    }

    selection.value = field.value.slice(
        field.selectionStart ?? 0,
        field.selectionEnd ?? 0,
    );
}

function add() {
    addToList(props.list, selection.value, (label) => {
        selection.value = '';
        emit('added', label);
    });
}
</script>

<template>
    <div
        class="relative"
        @select="readSelection"
        @mouseup="readSelection"
        @keyup="readSelection"
        @input="readSelection"
        @focusout="selection = ''"
    >
        <slot />
        <button
            v-if="canAdd"
            type="button"
            class="absolute end-0 bottom-full mb-1 inline-flex size-5 items-center justify-center rounded-md border bg-background text-primary shadow-sm hover:bg-accent"
            :title="
                t('Add “:label” to the list', {
                    label: cleanLabel(selection),
                })
            "
            :aria-label="
                t('Add “:label” to the list', {
                    label: cleanLabel(selection),
                })
            "
            @mousedown.prevent
            @click="add"
        >
            <Plus class="size-3.5" />
        </button>
    </div>
</template>
