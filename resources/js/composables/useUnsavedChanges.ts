import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

/**
 * Keeps the administrator from losing what was typed into a form: leaving the page
 * with unsaved changes asks for confirmation first, inside the application and when
 * the browser tab is closed or reloaded.
 */
export function useUnsavedChanges(isDirty: () => boolean) {
    const confirming = ref(false);

    let saving = false;
    let pending: (() => void) | null = null;

    const guarded = () => isDirty() && !saving;

    const stopListening = router.on('before', (event) => {
        const { url, method, data, prefetch } = event.detail.visit;

        // Only leaving the page loses the form; actions taken on the page itself, like
        // uploading an attachment, return to it with the typed values preserved. Links
        // that prefetch on hover fire this event too without navigating anywhere.
        if (!guarded() || method !== 'get' || prefetch) {
            return;
        }

        pending = () => router.visit(url, { method, data });
        confirming.value = true;

        event.preventDefault();
    });

    function onBeforeUnload(event: BeforeUnloadEvent) {
        if (guarded()) {
            event.preventDefault();
        }
    }

    window.addEventListener('beforeunload', onBeforeUnload);

    onBeforeUnmount(() => {
        stopListening();
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    /** Call right before submitting the form, so that saving is not held back. */
    function startSaving() {
        saving = true;
    }

    /** Call when saving failed and the form stays open. */
    function stopSaving() {
        saving = false;
    }

    /** Let the next visit leave without asking, when nothing worth keeping would be lost. */
    function allowLeaving() {
        saving = true;
    }

    /** Leave without saving, to where the administrator was going. */
    function leave() {
        saving = true;
        confirming.value = false;
        pending?.();
    }

    return { confirming, startSaving, stopSaving, allowLeaving, leave };
}
