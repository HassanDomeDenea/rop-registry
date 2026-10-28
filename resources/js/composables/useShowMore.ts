import type { MaybeRefOrGetter } from 'vue';
import { computed, ref, toValue } from 'vue';

/**
 * Shows the first entries of a long list and folds the rest behind a button.
 * Folded entries are hidden on screen only, so a printout keeps the whole list.
 */
export function useShowMore(
    count: MaybeRefOrGetter<number>,
    limit: MaybeRefOrGetter<number> = 10,
) {
    const expanded = ref(false);

    /** A list one entry longer than the limit is shown whole: a button would save nothing. */
    const folded = computed(
        () => toValue(count) > toValue(limit) + 1 && !expanded.value,
    );

    const hiddenCount = computed(() =>
        toValue(count) > toValue(limit) + 1
            ? toValue(count) - toValue(limit)
            : 0,
    );

    function isFolded(index: number) {
        return folded.value && index >= toValue(limit);
    }

    function toggle() {
        expanded.value = !expanded.value;
    }

    return { expanded, hiddenCount, isFolded, toggle };
}
