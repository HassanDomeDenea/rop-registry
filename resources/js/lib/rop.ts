/**
 * ETROP classification of an eye, mirroring App\Support\RopClassifier.
 *
 * Type 1 (treatment indicated): zone I any stage with plus disease, zone I stage 3
 * without plus disease, zone II stage 2 or 3 with plus disease, or aggressive ROP.
 * Type 2 (close observation): zone I stage 1 or 2 without plus disease, or zone II
 * stage 3 without plus disease.
 */
export function classifyRop(
    zone: unknown,
    stage: unknown,
    plus: unknown,
    aggressive: unknown,
): 'type_1' | 'type_2' | null {
    if (aggressive === true) {
        return 'type_1';
    }

    const severity = { stage_1: 1, stage_2: 2, stage_3: 3 }[String(stage)];

    if (!zone || !severity) {
        return null;
    }

    const hasPlus = plus === 'plus';

    if (zone === 'zone_1') {
        return hasPlus || severity === 3 ? 'type_1' : 'type_2';
    }

    if (zone === 'zone_2' || zone === 'posterior_zone_2') {
        if (hasPlus && severity >= 2) {
            return 'type_1';
        }

        if (!hasPlus && severity === 3) {
            return 'type_2';
        }
    }

    return null;
}

export const EYE_FIELDS = [
    'dilatation',
    'lens',
    'plus',
    'zone',
    'stage',
    'a_rop',
    'rop_type',
    'rop_status',
    'notes',
] as const;

export type FollowUpSuggestion = {
    /** Treatment is indicated rather than a further observation interval. */
    treat: boolean;
    minWeeks: number;
    maxWeeks: number;
};

/**
 * The longest follow-up interval recommended for one eye by the AAP / AAO / AAPOS
 * screening statement (Pediatrics 2018), or null when the findings give no basis.
 * It is a reminder of the published schedule, not a substitute for the examiner.
 */
function eyeFollowUp(
    zone: unknown,
    stage: unknown,
    plus: unknown,
    aggressive: unknown,
    status: unknown,
): FollowUpSuggestion | null {
    if (classifyRop(zone, stage, plus, aggressive) === 'type_1') {
        return { treat: true, minWeeks: 0, maxWeeks: 0 };
    }

    const severity = { stage_2: 2, stage_3: 3 }[String(stage)] ?? 0;
    const regressing = status === 'regressing' || status === 'regressed';
    const weeks = (min: number, max = min): FollowUpSuggestion => ({
        treat: false,
        minWeeks: min,
        maxWeeks: max,
    });

    switch (zone) {
        case 'zone_1':
            // Immature vascularisation or stage 1-2 in zone I; regressing ROP in zone I.
            return regressing ? weeks(1, 2) : weeks(1);
        case 'posterior_zone_2':
            return regressing ? weeks(1, 2) : weeks(1);
        case 'zone_2':
            if (severity === 3) {
                return weeks(1);
            }

            if (severity === 2) {
                return weeks(1, 2);
            }

            return weeks(2);
        case 'zone_3':
            return weeks(2, 3);
        default:
            return null;
    }
}

/** The follow-up interval for the visit: that of the eye needing the earlier review. */
export function suggestFollowUp(
    form: Record<string, unknown>,
): FollowUpSuggestion | null {
    const eyes = (['right', 'left'] as const)
        .map((eye) =>
            eyeFollowUp(
                form[`${eye}_zone`],
                form[`${eye}_stage`],
                form[`${eye}_plus`],
                form[`${eye}_a_rop`],
                form[`${eye}_rop_status`],
            ),
        )
        .filter((value): value is FollowUpSuggestion => value !== null);

    if (eyes.length === 0) {
        return null;
    }

    return eyes.reduce((earliest, eye) =>
        eye.treat || eye.maxWeeks < earliest.maxWeeks ? eye : earliest,
    );
}
