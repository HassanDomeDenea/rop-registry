import { useStorage } from '@vueuse/core';

export type PatientPanelTab = 'overview' | 'timeline' | 'attachments';

/**
 * Whether the patient record is shown beside the patient and visit forms, and on
 * which tab. Remembered in this browser, so the panel stays the way it was left.
 */
export function usePatientPanel() {
    const panel = useStorage<{ open: boolean; tab: PatientPanelTab }>(
        'registry.patient-panel',
        { open: true, tab: 'overview' },
        undefined,
        { mergeDefaults: true },
    );

    function show(tab: PatientPanelTab) {
        panel.value = { open: true, tab };
    }

    function toggle() {
        panel.value = { ...panel.value, open: !panel.value.open };
    }

    return { panel, show, toggle };
}
