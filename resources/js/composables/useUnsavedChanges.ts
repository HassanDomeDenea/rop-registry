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
        if (!guarded()) {
            return;
        }

        const { url, method, data } = event.detail.visit;

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

    /** Leave without saving, to where the administrator was going. */
    function leave() {
        saving = true;
        confirming.value = false;
        pending?.();
    }

    return { confirming, startSaving, stopSaving, leave };
}
