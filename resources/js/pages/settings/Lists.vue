<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, ListChecks, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import listRoutes from '@/routes/lists';

type Suggestion = { id: number; list: string; label: string };

const props = defineProps<{ suggestions: Suggestion[] }>();

const { t, enumOptions } = useI18n();

const descriptions: Record<string, string> = {
    illness: 'Shown as a checklist on the patient form',
    referring_doctor:
        'Offered while typing the referring doctor. A new name typed on a patient is added here automatically.',
};

const drafts = reactive<Record<string, string | null>>({});
const errors = reactive<Record<string, string | undefined>>({});
const editing = ref<{ id: number; label: string | null } | null>(null);

const entries = (list: string) =>
    props.suggestions.filter((suggestion) => suggestion.list === list);

function add(list: string) {
    router.post(
        listRoutes.store.url(),
        { list, label: drafts[list] ?? '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                drafts[list] = null;
                errors[list] = undefined;
            },
            onError: (response) => {
                errors[list] = response.label;
            },
        },
    );
}

function save(list: string) {
    if (!editing.value) {
        return;
    }

    router.put(
        listRoutes.update.url(editing.value.id),
        { label: editing.value.label ?? '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
                errors[list] = undefined;
            },
            onError: (response) => {
                errors[list] = response.label;
            },
        },
    );
}

function remove(suggestion: Suggestion) {
    router.delete(listRoutes.destroy.url(suggestion.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('Lists')" />

    <SectionCard
        v-for="list in enumOptions('suggestion_list')"
        :key="list.value"
        :title="list.label"
        :description="t(descriptions[list.value] ?? '')"
        :icon="ListChecks"
        flush
    >
        <table class="w-full text-sm">
            <tbody>
                <tr
                    v-for="(suggestion, index) in entries(list.value)"
                    :key="suggestion.id"
                    class="border-b"
                >
                    <td
                        class="w-12 px-5 py-2 text-xs text-muted-foreground tabular-nums"
                    >
                        {{ index + 1 }}
                    </td>
                    <td class="py-1.5">
                        <form
                            v-if="editing?.id === suggestion.id"
                            @submit.prevent="save(list.value)"
                        >
                            <TextInput
                                v-model="editing.label"
                                v-focus
                                dir="auto"
                                class="h-8"
                                @keydown.esc="editing = null"
                            />
                        </form>
                        <span v-else dir="auto">{{ suggestion.label }}</span>
                    </td>
                    <td class="w-24 px-3 py-1.5 text-end whitespace-nowrap">
                        <template v-if="editing?.id === suggestion.id">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :title="t('Save')"
                                @click="save(list.value)"
                            >
                                <Check />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :title="t('Cancel')"
                                @click="editing = null"
                            >
                                <X />
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :title="t('Edit')"
                                @click="
                                    editing = {
                                        id: suggestion.id,
                                        label: suggestion.label,
                                    }
                                "
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-destructive"
                                :title="t('Delete')"
                                @click="remove(suggestion)"
                            >
                                <Trash2 />
                            </Button>
                        </template>
                    </td>
                </tr>
                <tr v-if="entries(list.value).length === 0">
                    <td
                        colspan="3"
                        class="border-b px-5 py-6 text-center text-muted-foreground"
                    >
                        {{ t('This list is empty') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <form class="px-5 py-3" @submit.prevent="add(list.value)">
            <div class="flex items-center gap-2">
                <TextInput
                    v-model="drafts[list.value]"
                    dir="auto"
                    :placeholder="t('Add an entry…')"
                />
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="!drafts[list.value]"
                >
                    <Plus />
                    {{ t('Add') }}
                </Button>
            </div>
            <InputError class="mt-1.5" :message="errors[list.value]" />
        </form>
    </SectionCard>

    <p class="text-xs text-muted-foreground">
        {{
            t(
                'Renaming or deleting an entry changes what is offered from now on. Records already saved keep their text.',
            )
        }}
    </p>
</template>
