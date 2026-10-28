import { useForm } from '@inertiajs/vue3';
import { EYE_FIELDS } from '@/lib/rop';
import { todayIso } from '@/lib/utils';
import type { Eye, Visit } from '@/types';

/** The findings checklist of each eye is a list of ids; every other field is one value. */
export type VisitFormData = Record<string, string | number | boolean | null>;

/**
 * The examination form, filled with the given visit, or empty and dated today for a
 * new one. A new visit keeps the examiner of the previous one.
 */
export function useVisitForm(
    visit: Visit | null,
    examiner: string | null = null,
) {
    const source = visit as unknown as VisitFormData | null;

    const eyeDefaults = (eye: Eye): VisitFormData =>
        Object.fromEntries(
            EYE_FIELDS.map((field) => [
                `${eye}_${field}`,
                source?.[`${eye}_${field}`] ?? null,
            ]),
        );

    return useForm<VisitFormData>({
        kind: visit?.kind ?? 'examination',
        visit_date: visit ? visit.visit_date : todayIso(),
        examiner: visit?.examiner ?? examiner,
        ...eyeDefaults('right'),
        ...eyeDefaults('left'),
        assessment: visit?.assessment ?? null,
        management_plan: visit?.management_plan ?? null,
        management_notes: visit?.management_notes ?? null,
        next_visit_date: visit?.next_visit_date ?? null,
        fee: visit?.fee ?? null,
        notes: visit?.notes ?? null,
    });
}
