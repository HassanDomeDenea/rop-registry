import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import patientRoutes from '@/routes/patients';

export type PatientMatch = {
    id: number;
    name: string;
    file_number: string | null;
    dob: string | null;
    phone: string | null;
    unverified: boolean;
};

/**
 * Finds the registered patients matching what is typed, by name, file no. or phone,
 * while typing: the search box of the top bar and the name of a new patient.
 */
export function usePatientLookup() {
    const loading = ref(false);
    const matches = ref<PatientMatch[]>([]);
    const total = ref(0);

    let latest = 0;

    const fetchMatches = useDebounceFn(
        async (term: string, request: number) => {
            if (request !== latest) {
                return;
            }

            const response = await fetch(
                patientRoutes.lookup.url({ query: { search: term } }),
                { headers: { Accept: 'application/json' } },
            );

            // A slower, older answer must not replace a newer one.
            if (request !== latest) {
                return;
            }

            const result = response.ok
                ? ((await response.json()) as {
                      total: number;
                      patients: PatientMatch[];
                  })
                : { total: 0, patients: [] };

            matches.value = result.patients;
            total.value = result.total;
            loading.value = false;
        },
        250,
    );

    function lookup(search: string) {
        const term = search.trim();

        if (term === '') {
            clear();

            return;
        }

        loading.value = true;
        void fetchMatches(term, ++latest);
    }

    function clear() {
        latest++;
        matches.value = [];
        total.value = 0;
        loading.value = false;
    }

    return { loading, matches, total, lookup, clear };
}
