<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintSheet from '@/components/registry/PrintSheet.vue';
import { useI18n } from '@/composables/useI18n';
import type { Clinic, Eye, Patient, Treatment, Visit } from '@/types';

/** A one-page summary of the patient: birth history, every examination and every treatment. */
const props = defineProps<{
    patient: Patient;
    visits: Visit[];
    treatments: Treatment[];
    clinic: Clinic;
}>();

const {
    t,
    enumLabel,
    formatDate,
    formatWeeks,
    formatGestationalAge,
    formatNumber,
} = useI18n();

const header = computed(() => [
    [t('Patient name'), props.patient.name],
    [t('File no.'), props.patient.file_number ?? '—'],
    [t('Date of birth'), formatDate(props.patient.dob)],
    [t('Sex'), enumLabel('sex', props.patient.sex)],
    [
        t('Gestational age'),
        formatGestationalAge(props.patient.ga_weeks, props.patient.ga_days),
    ],
    [t('Birth weight (g)'), formatNumber(props.patient.birth_weight_g)],
    [
        t('Multiple births'),
        enumLabel('multiplicity', props.patient.multiplicity),
    ],
    [
        t('Delivery mode'),
        enumLabel('delivery_mode', props.patient.delivery_mode),
    ],
    [t('NICU stay (days)'), formatNumber(props.patient.nicu_days)],
    [
        t('Respiratory support'),
        enumLabel('respiratory_support', props.patient.respiratory_support) +
            (props.patient.support_days !== null
                ? ` · ${t(':count days', { count: props.patient.support_days })}`
                : ''),
    ],
    [t('Referring doctor'), props.patient.referring_doctor ?? '—'],
    [t('Parent phone'), props.patient.phone ?? '—'],
    [t('Status'), enumLabel('patient_status', props.patient.status)],
    [t('Associated systemic illness'), props.patient.systemic_illness ?? '—'],
]);

function eye(visit: Visit, side: Eye) {
    const record = visit as unknown as Record<string, string | boolean | null>;

    const parts = [
        enumLabel('rop_status', record[`${side}_rop_status`] as string, ''),
        enumLabel('zone', record[`${side}_zone`] as string, ''),
        enumLabel('stage', record[`${side}_stage`] as string, ''),
        enumLabel('plus_disease', record[`${side}_plus`] as string, ''),
        record[`${side}_a_rop`] === true ? 'A-ROP' : '',
        enumLabel(
            'rop_type',
            (record[`${side}_rop_type`] ??
                record[`${side}_suggested_type`]) as string,
            '',
        ),
    ].filter(Boolean);

    return parts.length ? parts.join(', ') : '—';
}
</script>

<template>
    <Head :title="patient.name" />

    <PrintSheet :clinic="clinic" :title="t('ROP screening summary')">
        <dl class="grid grid-cols-2 gap-x-10 gap-y-1">
            <div
                v-for="[label, value] in header"
                :key="label"
                class="flex items-baseline gap-2 border-b border-neutral-200 py-1"
            >
                <dt class="w-40 shrink-0 text-neutral-500">{{ label }}</dt>
                <dd class="flex-1 font-medium" dir="auto">{{ value }}</dd>
            </div>
        </dl>

        <h2 class="mt-7 mb-2 font-semibold">{{ t('Examinations') }}</h2>
        <table class="w-full border-collapse text-xs">
            <thead>
                <tr
                    class="border-y border-neutral-400 bg-neutral-100 text-start"
                >
                    <th class="px-2 py-1.5 text-start font-semibold">#</th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('Date') }}
                    </th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('PMA') }}
                    </th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('Right eye') }}
                    </th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('Left eye') }}
                    </th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('Plan') }}
                    </th>
                    <th class="px-2 py-1.5 text-start font-semibold">
                        {{ t('Next visit') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(visit, index) in visits"
                    :key="visit.id"
                    class="border-b border-neutral-200 align-top"
                >
                    <td class="px-2 py-1.5 tabular-nums">{{ index + 1 }}</td>
                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">
                        {{ formatDate(visit.visit_date) }}
                    </td>
                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">
                        {{ formatWeeks(visit.pma_days) }}
                    </td>
                    <td class="px-2 py-1.5">{{ eye(visit, 'right') }}</td>
                    <td class="px-2 py-1.5">{{ eye(visit, 'left') }}</td>
                    <td class="px-2 py-1.5">
                        {{
                            enumLabel('management_plan', visit.management_plan)
                        }}
                        <span
                            v-if="visit.assessment"
                            class="block text-neutral-500"
                            dir="auto"
                        >
                            {{ visit.assessment }}
                        </span>
                    </td>
                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">
                        {{ formatDate(visit.next_visit_date) }}
                    </td>
                </tr>
                <tr v-if="visits.length === 0">
                    <td
                        colspan="7"
                        class="px-2 py-4 text-center text-neutral-500"
                    >
                        {{ t('No visits recorded yet') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <template v-if="treatments.length">
            <h2 class="mt-7 mb-2 font-semibold">
                {{ t('Treatments performed') }}
            </h2>
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="border-y border-neutral-400 bg-neutral-100">
                        <th class="px-2 py-1.5 text-start font-semibold">
                            {{ t('Date') }}
                        </th>
                        <th class="px-2 py-1.5 text-start font-semibold">
                            {{ t('PMA') }}
                        </th>
                        <th class="px-2 py-1.5 text-start font-semibold">
                            {{ t('Treatment') }}
                        </th>
                        <th class="px-2 py-1.5 text-start font-semibold">
                            {{ t('Eye') }}
                        </th>
                        <th class="px-2 py-1.5 text-start font-semibold">
                            {{ t('Notes') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="treatment in treatments"
                        :key="treatment.id"
                        class="border-b border-neutral-200 align-top"
                    >
                        <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">
                            {{ formatDate(treatment.performed_date) }}
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">
                            {{ formatWeeks(treatment.pma_days) }}
                        </td>
                        <td class="px-2 py-1.5">
                            {{ enumLabel('treatment_type', treatment.type) }}
                        </td>
                        <td class="px-2 py-1.5">
                            {{ enumLabel('eye_side', treatment.eye) }}
                        </td>
                        <td class="px-2 py-1.5" dir="auto">
                            {{
                                [treatment.agent, treatment.notes]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="patient.notes">
            <h2 class="mt-7 mb-2 font-semibold">{{ t('Notes') }}</h2>
            <p class="whitespace-pre-line" dir="auto">{{ patient.notes }}</p>
        </template>
    </PrintSheet>
</template>
