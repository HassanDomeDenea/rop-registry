import { router } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from '@/composables/useI18n';
import listRoutes from '@/routes/lists';

export type SuggestionListName = 'illness' | 'referring_doctor' | 'eye_finding';

/** Runs of spaces and line breaks count as one space, and the ends are trimmed. */
export function cleanLabel(value: string | number | null | undefined): string {
    return String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Whether a label is already an entry of the list, ignoring spacing and letter case. */
export function isListed(label: string, options: string[]): boolean {
    const wanted = cleanLabel(label).toLowerCase();

    return options.some(
        (option) => cleanLabel(option).toLowerCase() === wanted,
    );
}

/**
 * Adds an entry to one of the editable lists straight from a form, without leaving
 * it: the page reloads its props with the typed values kept.
 */
export function useSuggestionList() {
    const { t } = useI18n();

    function addToList(
        list: SuggestionListName,
        value: string,
        onAdded?: (label: string) => void,
    ) {
        const label = cleanLabel(value);

        if (label === '') {
            return;
        }

        router.post(
            listRoutes.store.url(),
            { list, label },
            {
                preserveScroll: true,
                preserveState: true,
                errorBag: 'suggestion',
                onSuccess: () => {
                    toast.success(
                        t(':label was added to the list.', { label }),
                    );
                    // The child components receive the reloaded list on the next render.
                    void nextTick(() => onAdded?.(label));
                },
                onError: (errors) => {
                    toast.error(
                        errors.label ?? t('The entry could not be added.'),
                    );
                },
            },
        );
    }

    return { addToList };
}
