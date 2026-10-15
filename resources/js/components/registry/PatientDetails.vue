<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { Patient } from '@/types';

/** The recorded details of a patient as a label and value list. */
const props = defineProps<{ patient: Patient }>();

const { t, enumLabel, formatDate, formatGestationalAge, formatNumber } =
    useI18n();

const details = computed(() =>
    [
        { label: t('Clinic file no.'), value: props.patient.file_number },
        { label: t('Mother name'), value: props.patient.mother_name },
        { label: t('Sex'), value: enumLabel('sex', props.patient.sex) },
        { label: t('Date of birth'), value: formatDate(props.patient.dob) },
        {
            label: t('Gestational age'),
            value: formatGestationalAge(
                props.patient.ga_weeks,
                props.patient.ga_days,
            ),
        },
        {
            label: t('Birth weight (g)'),
            value: formatNumber(props.patient.birth_weight_g),
        },
        {
            label: t('Multiple births'),
            value: enumLabel('multiplicity', props.patient.multiplicity),
        },
        {
            label: t('Delivery mode'),
            value: enumLabel('delivery_mode', props.patient.delivery_mode),
        },
        {
            label: t('NICU stay (days)'),
            value: formatNumber(props.patient.nicu_days),
        },
        {
            label: t('Respiratory support'),
            value:
                enumLabel(
                    'respiratory_support',
                    props.patient.respiratory_support,
                ) +
                (props.patient.support_days !== null
                    ? ` · ${t(':count days', { count: props.patient.support_days })}`
                    : ''),
        },
        {
            label: t('O₂ days'),
            value: formatNumber(props.patient.o2_days, ''),
            optional: true,
        },
        {
            label: t('CPAP days'),
            value: formatNumber(props.patient.cpap_days, ''),
            optional: true,
        },
        {
            label: t('Associated systemic illness'),
            value: props.patient.illness_summary,
        },
        {
            label: t('Referral date'),
            value: formatDate(props.patient.referral_date),
        },
        { label: t('Referring doctor'), value: props.patient.referring_doctor },
        { label: t('Parent phone'), value: props.patient.phone, ltr: true },
        {
            label: t('Second phone'),
            value: props.patient.phone_alt,
            ltr: true,
            optional: true,
        },
        { label: t('Address'), value: props.patient.address },
    ].filter((row) => !row.optional || row.value),
);
</script>

<template>
    <dl class="divide-y text-sm">
        <div
            v-for="row in details"
            :key="row.label"
            class="grid grid-cols-5 gap-3 px-5 py-2.5"
        >
            <dt class="col-span-2 text-muted-foreground">
                {{ row.label }}
            </dt>
            <dd
                class="col-span-3 break-words"
                :dir="row.ltr ? 'ltr' : 'auto'"
                :class="row.ltr ? 'text-start' : ''"
            >
                {{ row.value || '—' }}
            </dd>
        </div>
    </dl>
</template>
