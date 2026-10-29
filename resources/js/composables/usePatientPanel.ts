import { useMediaQuery, useStorage } from '@vueuse/core';
import { computed, ref } from 'vue';

export type PatientPanelTab = 'overview' | 'timeline' | 'attachments';

type PatientPanelState = { open: boolean; tab: PatientPanelTab };

/**
 * Whether the patient record is shown beside the patient and visit forms, and on
 * which tab. Remembered in this browser, so the panel stays the way it was left.
 * On a phone the panel covers the whole form, so there it starts closed and is
 * not remembered.
 */
export function usePatientPanel() {
    const remembered = useStorage<PatientPanelState>(
        'registry.patient-panel',
        { open: true, tab: 'overview' },
        undefined,
        { mergeDefaults: true },
    );
    const isSmallScreen = useMediaQuery('(max-width: 767px)');
    const onSmallScreen = ref<PatientPanelState>({
        open: false,
        tab: remembered.value.tab,
    });

    const panel = computed<PatientPanelState>({
        get: () =>
            isSmallScreen.value ? onSmallScreen.value : remembered.value,
        set: (value) => {
            if (isSmallScreen.value) {
                onSmallScreen.value = value;
            } else {
                remembered.value = value;
            }
        },
    });

    function show(tab: PatientPanelTab) {
        panel.value = { open: true, tab };
    }

    function toggle() {
        panel.value = { ...panel.value, open: !panel.value.open };
    }

    return { panel, show, toggle };
}
