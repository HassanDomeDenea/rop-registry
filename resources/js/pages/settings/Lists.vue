<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    Eye,
    EyeOff,
    ListChecks,
    Pencil,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import listRoutes from '@/routes/lists';

type Suggestion = {
    id: number;
    list: string;
    label: string;
    hidden: boolean;
    /** For eye findings: the visits that ticked the entry, which keep it when it is removed. */
    visits_count: number | null;
    /** For referring doctors: the patients referred by them. */
    patients_count: number | null;
};

const props = defineProps<{ suggestions: Suggestion[] }>();

const { t, enumOptions } = useI18n();

const descriptions: Record<string, string> = {
    illness: 'Shown as a checklist on the patient form',
    referring_doctor:
        'Offered while typing the referring doctor. A new name typed on a patient is added here automatically. Renaming a doctor renames them on every patient they referred; renaming one to the name of another merges the two.',
    eye_finding:
        'Ticked for each eye on the examination form and counted in the statistics. Renaming an entry renames it on every visit; an entry already recorded on visits is hidden instead of deleted.',
};

const drafts = reactive<Record<string, string | null>>({});
const errors = reactive<Record<string, string | undefined>>({});
const editing = ref<{ id: number; label: string | null } | null>(null);
/** A rename of a referring doctor to the name of another entry, waiting to be confirmed. */
const merging = ref<{
    list: string;
    from: Suggestion;
    into: Suggestion;
} | null>(null);

/** Referring doctors are written in Arabic; the other lists in either language. */
const keyboardFor = (list: string) =>
    list === 'referring_doctor' ? 'ar' : undefined;

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

function save(list: string, confirmed = false) {
    if (!editing.value) {
        return;
    }

    const id = editing.value.id;
    const label = (editing.value.label ?? '').trim();
    const into = entries(list).find(
        (suggestion) => suggestion.id !== id && suggestion.label === label,
    );
    const from = entries(list).find((suggestion) => suggestion.id === id);

    if (list === 'referring_doctor' && into && from && !confirmed) {
        merging.value = { list, from, into };

        return;
    }

    merging.value = null;

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

function setHidden(suggestion: Suggestion, hidden: boolean) {
    router.put(
        listRoutes.visibility.url(suggestion.id),
        { hidden },
        { preserveScroll: true },
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
        <div class="max-md:overflow-x-auto">
            <table class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="(suggestion, index) in entries(list.value)"
                        :key="suggestion.id"
                        class="border-b"
                        :class="{ 'text-muted-foreground': suggestion.hidden }"
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
                                    :data-keyboard="keyboardFor(list.value)"
                                    class="h-8"
                                    @keydown.esc="editing = null"
                                />
                            </form>
                            <span v-else class="flex items-center gap-2">
                                <span dir="auto">{{ suggestion.label }}</span>
                                <span
                                    v-if="suggestion.hidden"
                                    class="rounded bg-muted px-1.5 py-0.5 text-xs"
                                >
                                    {{ t('Hidden from the form') }}
                                </span>
                                <span
                                    v-if="suggestion.patients_count !== null"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        t(':count patients', {
                                            count: suggestion.patients_count,
                                        })
                                    }}
                                </span>
                                <span
                                    v-if="suggestion.visits_count"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        t(':count visits', {
                                            count: suggestion.visits_count,
                                        })
                                    }}
                                </span>
                            </span>
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
                                    v-if="suggestion.hidden"
                                    variant="ghost"
                                    size="icon-sm"
                                    :title="t('Offer on the form again')"
                                    @click="setHidden(suggestion, false)"
                                >
                                    <Eye />
                                </Button>
                                <Button
                                    v-else-if="suggestion.visits_count"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground"
                                    :title="
                                        t(
                                            'Hide from the form. Visits that recorded it keep it.',
                                        )
                                    "
                                    @click="setHidden(suggestion, true)"
                                >
                                    <EyeOff />
                                </Button>
                                <Button
                                    v-else
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
        </div>

        <form class="px-5 py-3" @submit.prevent="add(list.value)">
            <div class="flex items-center gap-2">
                <TextInput
                    v-model="drafts[list.value]"
                    dir="auto"
                    :data-keyboard="keyboardFor(list.value)"
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
                'Illnesses: renaming or deleting an entry changes what is offered from now on; records already saved keep their text. Deleting a referring doctor only removes the suggestion; their patients keep the name.',
            )
        }}
    </p>

    <ConfirmDialog
        :open="merging !== null"
        :title="t('Merge the two doctors?')"
        :description="
            merging
                ? t(
                      ':from will be renamed to :into on its :count patients, and removed from the list.',
                      {
                          from: merging.from.label,
                          into: merging.into.label,
                          count: merging.from.patients_count ?? 0,
                      },
                  )
                : undefined
        "
        :confirm-label="t('Merge')"
        :destructive="false"
        @update:open="merging = $event ? merging : null"
        @confirm="merging && save(merging.list, true)"
    />
</template>
