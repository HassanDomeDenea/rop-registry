<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintSheet from '@/components/registry/PrintSheet.vue';
import ZoneDiagram from '@/components/registry/ZoneDiagram.vue';
import { useI18n } from '@/composables/useI18n';
import type { Clinic, EnumName, Eye, Patient, Visit } from '@/types';

/**
 * The examination report, laid out like the paper form "Fundus examination details
 * and staging of ROP": patient header, one diagram per eye, and the numbered findings.
 */
const props = defineProps<{
    patient: Patient;
    visit: Visit;
    visitNumber: number;
    clinic: Clinic;
}>();

const {
    t,
    direction,
    enumLabel,
    formatDate,
    formatWeeks,
    formatAge,
    formatGestationalAge,
    formatNumber,
} = useI18n();

const record = computed(
    () => props.visit as unknown as Record<string, string | boolean | null>,
);

function finding(eye: Eye, field: string, enumName?: EnumName) {
    const value = record.value[`${eye}_${field}`];

    if (value === null || value === '') {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? t('Yes') : t('No');
    }

    return enumName ? enumLabel(enumName, value) : value;
}

const rows = computed(() => [
    { label: t('Dilatation'), field: 'dilatation' },
    { label: t('Lens'), field: 'lens' },
    { label: t('Plus disease'), field: 'plus', enum: 'plus_disease' as const },
    { label: t('Zone'), field: 'zone', enum: 'zone' as const },
    { label: t('Stage'), field: 'stage', enum: 'stage' as const },
    { label: t('A-ROP (aggressive)'), field: 'a_rop' },
    { label: t('Type of ROP'), field: 'rop_type', enum: 'rop_type' as const },
    {
        label: t('ROP status'),
        field: 'rop_status',
        enum: 'rop_status' as const,
    },
    { label: t('Notes'), field: 'notes' },
]);

const header = computed(() => [
    [t('Patient name'), props.patient.name],
    [t('File no.'), props.patient.file_number ?? ''],
    [t('Date of birth'), formatDate(props.patient.dob, '')],
    [t('Sex'), enumLabel('sex', props.patient.sex)],
    [t('Birth weight (g)'), formatNumber(props.patient.birth_weight_g, '')],
    [
        t('Gestational age'),
        formatGestationalAge(props.patient.ga_weeks, props.patient.ga_days, ''),
    ],
    [
        t('Multiple births'),
        enumLabel('multiplicity', props.patient.multiplicity, ''),
    ],
    [t('Referral date'), formatDate(props.patient.referral_date, '')],
    [
        t('Respiratory support'),
        [
            enumLabel(
                'respiratory_support',
                props.patient.respiratory_support,
                '',
            ),
            props.patient.support_days !== null
                ? t(':count days', { count: props.patient.support_days })
                : '',
        ]
            .filter(Boolean)
            .join(' · '),
    ],
    [t('NICU stay (days)'), formatNumber(props.patient.nicu_days, '')],
]);
</script>

<template>
    <Head :title="`${patient.name} · ${formatDate(visit.visit_date, '')}`" />

    <PrintSheet
        :clinic="clinic"
        :title="t('Fundus examination details and staging of ROP')"
    >
        <dl class="grid grid-cols-2 gap-x-10 gap-y-1.5">
            <div
                v-for="[label, value] in header"
                :key="label"
                class="flex items-baseline gap-2"
            >
                <dt class="shrink-0 font-semibold">{{ label }}:</dt>
                <dd
                    class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    dir="auto"
                >
                    {{ value }}
                </dd>
            </div>
            <div class="col-span-2 flex items-baseline gap-2">
                <dt class="shrink-0 font-semibold">
                    {{ t('Associated systemic illness') }}:
                </dt>
                <dd
                    class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    dir="auto"
                >
                    {{ patient.illness_summary }}
                </dd>
            </div>
        </dl>

        <section class="mt-6 border-t pt-4">
            <h2 class="font-semibold tracking-wide uppercase underline">
                {{ t('Examination') }}
            </h2>
            <dl class="mt-2 grid grid-cols-3 gap-x-8 gap-y-1.5">
                <div class="flex items-baseline gap-2">
                    <dt class="shrink-0 font-semibold">
                        {{ t('Date of examination') }}:
                    </dt>
                    <dd
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    >
                        {{ formatDate(visit.visit_date, '') }}
                    </dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="shrink-0 font-semibold">{{ t('Age') }}:</dt>
                    <dd
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    >
                        {{ formatAge(visit.age_days, '') }}
                    </dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="shrink-0 font-semibold">{{ t('PMA') }}:</dt>
                    <dd
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    >
                        {{ formatWeeks(visit.pma_days, '') }}
                    </dd>
                </div>
                <div class="col-span-2 flex items-baseline gap-2">
                    <dt class="shrink-0 font-semibold">{{ t('Examiner') }}:</dt>
                    <dd
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        dir="auto"
                    >
                        {{ visit.examiner }}
                    </dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="shrink-0 font-semibold">{{ t('Visit') }}:</dt>
                    <dd
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    >
                        {{
                            visitNumber === 1
                                ? t('Initial examination')
                                : t('Follow-up :number', {
                                      number: visitNumber - 1,
                                  })
                        }}
                    </dd>
                </div>
            </dl>
        </section>

        <!-- The diagrams keep their clinical orientation in both languages. -->
        <section class="mt-5 grid grid-cols-2 gap-10" dir="ltr">
            <div v-for="eye in ['right', 'left'] as const" :key="eye">
                <h3 class="mb-1 text-center font-semibold" :dir="direction">
                    {{ eye === 'right' ? t('Right eye') : t('Left eye') }}
                </h3>
                <div class="flex justify-center">
                    <ZoneDiagram
                        :model-value="record[`${eye}_zone`]"
                        :eye="eye"
                        readonly
                    />
                </div>
                <ol class="mt-3 space-y-1.5" :dir="direction">
                    <li
                        v-for="(row, index) in rows"
                        :key="row.field"
                        class="flex items-baseline gap-2"
                    >
                        <span class="shrink-0 font-semibold">
                            {{ index + 1 }}. {{ row.label }}:
                        </span>
                        <span
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                            dir="auto"
                        >
                            {{ finding(eye, row.field, row.enum) }}
                        </span>
                    </li>
                </ol>
            </div>
        </section>

        <section class="mt-6 space-y-2 border-t pt-4">
            <p class="flex items-baseline gap-2">
                <span class="shrink-0 font-semibold"
                    >{{ t('Overall assessment') }}:</span
                >
                <span
                    class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    dir="auto"
                >
                    {{ visit.assessment }}
                </span>
            </p>
            <p class="flex items-baseline gap-2">
                <span class="shrink-0 font-semibold"
                    >{{ t('Management plan') }}:</span
                >
                <span
                    class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                    dir="auto"
                >
                    {{
                        [
                            enumLabel(
                                'management_plan',
                                visit.management_plan,
                                '',
                            ),
                            visit.management_notes,
                        ]
                            .filter(Boolean)
                            .join(' — ')
                    }}
                </span>
            </p>
            <p class="flex items-baseline gap-2">
                <span class="shrink-0 font-semibold"
                    >{{ t('Next appointment') }}:</span
                >
                <span
                    class="min-h-5 w-48 border-b border-dotted border-neutral-400"
                >
                    {{ formatDate(visit.next_visit_date, '') }}
                </span>
            </p>
        </section>

        <div class="mt-14 flex justify-end">
            <div
                class="w-56 border-t border-neutral-500 pt-1 text-center text-xs"
            >
                {{ t('Signature') }}
            </div>
        </div>
    </PrintSheet>
</template>
