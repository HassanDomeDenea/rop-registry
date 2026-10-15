import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { Treatment, Visit } from '@/types';

export type TimelineEntry =
    | {
          kind: 'visit';
          key: string;
          date: string | null;
          visit: Visit;
          number: number;
      }
    | {
          kind: 'treatment';
          key: string;
          date: string | null;
          treatment: Treatment;
      };

/** Visits and treatments in one chronological list; undated records come last. */
export function usePatientTimeline(
    visits: MaybeRefOrGetter<Visit[]>,
    treatments: MaybeRefOrGetter<Treatment[]>,
) {
    return computed<TimelineEntry[]>(() => {
        const entries: TimelineEntry[] = [
            ...toValue(visits).map((visit, index) => ({
                kind: 'visit' as const,
                key: `visit-${visit.id}`,
                date: visit.visit_date,
                visit,
                number: index + 1,
            })),
            ...toValue(treatments).map((treatment) => ({
                kind: 'treatment' as const,
                key: `treatment-${treatment.id}`,
                date: treatment.performed_date,
                treatment,
            })),
        ];

        return entries.sort((a, b) => {
            if (a.date === b.date) {
                return a.kind === b.kind ? 0 : a.kind === 'visit' ? -1 : 1;
            }

            if (a.date === null) {
                return 1;
            }

            if (b.date === null) {
                return -1;
            }

            return a.date < b.date ? -1 : 1;
        });
    });
}
