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
    english,
    enumLabel,
    formatDate,
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
        return value ? 'Yes' : 'No';
    }

    return enumName ? english.enumLabel(enumName, value) : value;
}

// The examination is written in English, like the paper form.
const rows: { label: string; field: string; enum?: EnumName }[] = [
    { label: 'Dilatation', field: 'dilatation' },
    { label: 'Lens', field: 'lens' },
    { label: 'Plus disease', field: 'plus', enum: 'plus_disease' },
    { label: 'Zone', field: 'zone', enum: 'zone' },
    { label: 'Stage', field: 'stage', enum: 'stage' },
    { label: 'A-ROP (aggressive)', field: 'a_rop' },
    { label: 'Type of ROP', field: 'rop_type', enum: 'rop_type' },
    { label: 'ROP status', field: 'rop_status', enum: 'rop_status' },
    { label: 'Notes', field: 'notes' },
];

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

        <!-- From here on the report is in English and left to right, whatever its language. -->
        <div dir="ltr" lang="en">
            <section class="mt-6 border-t pt-4">
                <h2 class="font-semibold tracking-wide uppercase underline">
                    Examination
                </h2>
                <dl class="mt-2 grid grid-cols-3 gap-x-8 gap-y-1.5">
                    <div class="flex items-baseline gap-2">
                        <dt class="shrink-0 font-semibold">
                            Date of examination:
                        </dt>
                        <dd
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        >
                            {{ english.formatDate(visit.visit_date, '') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="shrink-0 font-semibold">Age:</dt>
                        <dd
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        >
                            {{ english.formatAge(visit.age_days, '') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="shrink-0 font-semibold">PMA:</dt>
                        <dd
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        >
                            {{ english.formatWeeks(visit.pma_days, '') }}
                        </dd>
                    </div>
                    <div class="col-span-2 flex items-baseline gap-2">
                        <dt class="shrink-0 font-semibold">Examiner:</dt>
                        <dd
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                            dir="auto"
                        >
                            {{ visit.examiner }}
                        </dd>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <dt class="shrink-0 font-semibold">Visit:</dt>
                        <dd
                            class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        >
                            {{
                                visitNumber === 1
                                    ? 'Initial examination'
                                    : `Follow-up ${visitNumber - 1}`
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="mt-5 grid grid-cols-2 gap-10">
                <div v-for="eye in ['right', 'left'] as const" :key="eye">
                    <h3 class="mb-1 text-center font-semibold">
                        {{ eye === 'right' ? 'Right eye' : 'Left eye' }}
                    </h3>
                    <div class="flex justify-center">
                        <ZoneDiagram
                            :model-value="record[`${eye}_zone`]"
                            :eye="eye"
                            readonly
                        />
                    </div>
                    <ol class="mt-3 space-y-1.5">
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
                        >Overall assessment:</span
                    >
                    <span
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        dir="auto"
                    >
                        {{ visit.assessment }}
                    </span>
                </p>
                <p class="flex items-baseline gap-2">
                    <span class="shrink-0 font-semibold">Management plan:</span>
                    <span
                        class="min-h-5 flex-1 border-b border-dotted border-neutral-400"
                        dir="auto"
                    >
                        {{
                            [
                                english.enumLabel(
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
                        >Next appointment:</span
                    >
                    <span
                        class="min-h-5 w-48 border-b border-dotted border-neutral-400"
                    >
                        {{ english.formatDate(visit.next_visit_date, '') }}
                    </span>
                </p>
            </section>

            <div class="mt-14 flex justify-end">
                <div
                    class="w-56 border-t border-neutral-500 pt-1 text-center text-xs"
                >
                    Signature
                </div>
            </div>
        </div>
    </PrintSheet>
</template>
