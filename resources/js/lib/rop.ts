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
