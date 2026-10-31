<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Baby,
    HeartPulse,
    Images,
    PanelRight,
    NotebookPen,
    Phone,
    Stethoscope,
    TriangleAlert,
    UserRound,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AddSelectionToList from '@/components/registry/AddSelectionToList.vue';
import CheckList from '@/components/registry/CheckList.vue';
import DateInput from '@/components/registry/DateInput.vue';
import FormField from '@/components/registry/FormField.vue';
import FormSaveBar from '@/components/registry/FormSaveBar.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import PatientLookupResults from '@/components/registry/PatientLookupResults.vue';
import PatientPanel from '@/components/registry/PatientPanel.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import SegmentedControl from '@/components/registry/SegmentedControl.vue';
import SuggestInput from '@/components/registry/SuggestInput.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import UnsavedChanges from '@/components/registry/UnsavedChanges.vue';
import VisitCard from '@/components/registry/VisitCard.vue';
import VisitFields from '@/components/registry/VisitFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import { usePatientLookup } from '@/composables/usePatientLookup';
import type { PatientMatch } from '@/composables/usePatientLookup';
import { usePatientPanel } from '@/composables/usePatientPanel';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useVisitForm } from '@/composables/useVisitForm';
import { daysBetween, todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import type {
    Attachment,
    FindingOption,
    Patient,
    Treatment,
    Visit,
} from '@/types';

const props = defineProps<{
    patient: Patient | null;
    /** The earliest visit of the patient, corrected on this form when asked. */
    firstVisit: Visit | null;
    commonFees: number[];
    visits?: Visit[];
    treatments?: Treatment[];
    attachments?: Attachment[];
    illnessOptions: string[];
    doctorOptions: string[];
    findingOptions: FindingOption[];
}>();

const { t, enumOptions, formatWeeks, formatAge, formatDate } = useI18n();

const form = useForm<Record<string, string | number | null>>({
    file_number: props.patient?.file_number ?? null,
    name: props.patient?.name ?? '',
    mother_name: props.patient?.mother_name ?? null,
    dob: props.patient?.dob ?? null,
    sex: props.patient?.sex ?? 'unknown',
    birth_weight_g: props.patient?.birth_weight_g ?? null,
    ga_weeks: props.patient?.ga_weeks ?? null,
    ga_days: props.patient?.ga_days ?? null,
    multiplicity: props.patient?.multiplicity ?? null,
    delivery_mode: props.patient?.delivery_mode ?? null,
    referral_date: props.patient?.referral_date ?? null,
    referring_doctor: props.patient?.referring_doctor ?? null,
    nicu_days: props.patient?.nicu_days ?? null,
    respiratory_support: props.patient?.respiratory_support ?? null,
    support_days: props.patient?.support_days ?? null,
    o2_days: props.patient?.o2_days ?? null,
    cpap_days: props.patient?.cpap_days ?? null,
    systemic_illness: props.patient?.systemic_illness ?? null,
    phone: props.patient?.phone ?? null,
    phone_alt: props.patient?.phone_alt ?? null,
    address: props.patient?.address ?? null,
    notes: props.patient?.notes ?? null,
    status: props.patient?.status ?? 'active',
});

/**
 * The days of the chosen type of support: one duration for a single type, and separate
 * O₂ and CPAP days for both, which may overlap and are never added up.
 */
const supportDaysLabel = computed(() => {
    switch (form.respiratory_support) {
        case 'o2':
            return t('Days on O₂');
        case 'cpap':
            return t('Days on CPAP');
        case 'ventilator':
            return t('Days on ventilator');
        default:
            return null;
    }
});

/**
 * Some earlier records of O₂ + CPAP hold only one total that cannot be split. It stays
 * visible on those records so that it can be corrected or cleared.
 */
const hasCombinedTotal = ref(
    props.patient?.respiratory_support === 'o2_cpap' &&
        props.patient.support_days !== null,
);

// Switching between one type and both carries the days to the field that now holds them.
watch(
    () => form.respiratory_support,
    (support, previous) => {
        const splitField = (type: unknown) =>
            type === 'o2' ? 'o2_days' : type === 'cpap' ? 'cpap_days' : null;

        if (support === 'o2_cpap') {
            const field = splitField(previous);

            if (field && !hasCombinedTotal.value) {
                form[field] ??= form.support_days;
                form.support_days = null;
            }

            return;
        }

        const field = splitField(support);

        if (field && previous === 'o2_cpap') {
            form.support_days ??= form[field];
        }
    },
);

// The checklist is the only field that is not a single value.
const illnesses = ref<string[] | null>(props.patient?.illnesses ?? null);

/** An illness added to the list from the free text is ticked for this patient too. */
function tickIllness(label: string) {
    if (!illnesses.value?.includes(label)) {
        illnesses.value = [...(illnesses.value ?? []), label];
    }
}

/**
 * The first examination is saved with the patient only while it is switched on: by
 * default for a new patient, and on request when an existing one is edited.
 */
const withVisit = ref(!props.patient);

const visitForm = useVisitForm(props.firstVisit);

form.transform((data) => ({
    ...data,
    illnesses: illnesses.value,
    visit: withVisit.value ? visitForm.data() : null,
    visit_id: withVisit.value ? (props.firstVisit?.id ?? null) : null,
}));

/** The errors of the examination fields, which the server returns as `visit.<field>`. */
const visitErrors = computed(() =>
    Object.fromEntries(
        Object.entries(form.errors as Record<string, string | undefined>)
            .filter(([field]) => field.startsWith('visit.'))
            .map(([field, message]) => [field.slice('visit.'.length), message]),
    ),
);

// Switching the examination off discards what was typed into it.
watch(withVisit, (enabled) => {
    if (enabled) {
        return;
    }

    visitForm.reset();
    form.clearErrors(
        ...Object.keys(form.errors).filter(
            (field) => field.startsWith('visit.') || field === 'visit_id',
        ),
    );
});

/** Age and postmenstrual age on the examination date, from the birth details above. */
const examinationHint = computed(() => {
    const dob = form.dob === null ? null : String(form.dob);
    const date = visitForm.visit_date as string | null;

    if (!dob || !date || date < dob) {
        return undefined;
    }

    const age = daysBetween(dob, date);
    const parts = [`${t('Age')} ${formatAge(age)}`];

    if (form.ga_weeks !== null && form.ga_weeks !== '') {
        const gestational =
            Number(form.ga_weeks) * 7 + Number(form.ga_days ?? 0);

        parts.push(`${t('PMA')} ${formatWeeks(gestational + age)}`);
    }

    return parts.join(' · ');
});

const visitDescription = computed(() => {
    if (!props.patient) {
        return withVisit.value
            ? t(
                  'Saved together with the patient. Switch off to register the patient without an examination.',
              )
            : t('Only the patient will be registered.');
    }

    if (!props.firstVisit) {
        return withVisit.value
            ? t(
                  'A new examination will be saved with the patient. Switch off to discard it.',
              )
            : t('No examination recorded yet. Switch on to record one here.');
    }

    if (withVisit.value) {
        return t(
            'Your corrections are saved with the patient. Switch off to discard them.',
        );
    }

    return (props.visits?.length ?? 0) > 1
        ? t(
              'The earliest visit by date. Switch on to correct it here; later visits are edited from the patient record.',
          )
        : t('Switch on to correct it here.');
});

const attachmentsOfFirstVisit = computed(
    () =>
        (props.attachments ?? []).filter(
            (attachment) => attachment.visit_id === props.firstVisit?.id,
        ).length,
);

const backHref = computed(() =>
    props.patient
        ? patientRoutes.show.url(props.patient.id)
        : patientRoutes.index.url(),
);

/** Age and postmenstrual age today, shown while typing as a plausibility check. */
const ageToday = computed(() => {
    const dob = form.dob === null ? null : String(form.dob);

    if (!dob || dob > todayIso()) {
        return null;
    }

    const age = daysBetween(dob, todayIso());
    const gestational =
        form.ga_weeks === null || form.ga_weeks === undefined
            ? null
            : Number(form.ga_weeks) * 7 + Number(form.ga_days ?? 0);

    return { age, pma: gestational === null ? null : gestational + age };
});

type Duplicate = {
    id: number;
    name: string;
    file_number: string | null;
    dob: string | null;
    ga_weeks: number | null;
    unverified: boolean;
    reason: 'name' | 'dob' | 'name_and_dob';
};

const duplicates = ref<Duplicate[]>([]);

const duplicateReasons: Record<Duplicate['reason'], string> = {
    name: 'Similar name',
    dob: 'Same date of birth',
    name_and_dob: 'Similar name and same date of birth',
};

/** Look for babies already registered under a similar name or the same birth date. */
const findDuplicates = useDebounceFn(async () => {
    const name = String(form.name ?? '').trim();

    if (name.length < 3 && !form.dob) {
        duplicates.value = [];

        return;
    }

    const response = await fetch(
        patientRoutes.duplicates.url({
            query: {
                name,
                dob: form.dob ? String(form.dob) : undefined,
                ignore: props.patient?.id,
            },
        }),
        { headers: { Accept: 'application/json' } },
    );

    duplicates.value = response.ok ? await response.json() : [];
}, 400);

watch(() => [form.name, form.dob], findDuplicates);

/** The rest of the record beside the form, to check the details against the reports. */
const { panel, show: showPanel, toggle: togglePanel } = usePatientPanel();

const savedIllnesses = JSON.stringify(illnesses.value);

const dataBesidesName = () => JSON.stringify({ ...form.data(), name: null });

const savedBesidesName = dataBesidesName();

/** Whether anything besides the name was typed, which opening another patient would lose. */
const isDirtyBesidesName = computed(
    () =>
        dataBesidesName() !== savedBesidesName ||
        JSON.stringify(illnesses.value) !== savedIllnesses ||
        (withVisit.value && visitForm.isDirty),
);

const isDirty = computed(() => form.isDirty || isDirtyBesidesName.value);

const unsaved = useUnsavedChanges(() => isDirty.value);

/**
 * While a new patient is named, the patients already registered under that name are
 * offered, to open the existing record instead of registering the baby twice.
 */
const nameLookup = usePatientLookup();
const nameLookupOpen = ref(false);
const nameLookupActive = ref(-1);

watch(
    () => form.name,
    (name) => {
        if (props.patient) {
            return;
        }

        nameLookupOpen.value = true;
        nameLookupActive.value = -1;
        nameLookup.lookup(String(name ?? ''));
    },
);

const showNameLookup = computed(
    () =>
        !props.patient &&
        nameLookupOpen.value &&
        String(form.name ?? '').trim() !== '' &&
        nameLookup.matches.value.length > 0,
);

function closeNameLookup() {
    nameLookupOpen.value = false;
    nameLookupActive.value = -1;
}

/** The arrow keys move through the patients offered, and the cursor otherwise. */
function moveNameLookup(event: KeyboardEvent, step: number) {
    const count = nameLookup.matches.value.length;

    if (!showNameLookup.value || count === 0) {
        return;
    }

    event.preventDefault();
    nameLookupActive.value = (nameLookupActive.value + step + count) % count;
}

/** Enter opens the highlighted patient, and saves the form otherwise. */
function chooseNameLookup(event: KeyboardEvent) {
    const match = showNameLookup.value
        ? nameLookup.matches.value[nameLookupActive.value]
        : undefined;

    if (match) {
        event.preventDefault();
        openExisting(match);
    }
}

/**
 * Open a registered patient instead: a form holding only the name is left without
 * asking, anything else typed is confirmed first, as when leaving by any other way.
 */
function openExisting(match: PatientMatch) {
    closeNameLookup();

    if (!isDirtyBesidesName.value) {
        unsaved.allowLeaving();
    }

    router.get(patientRoutes.show.url(match.id));
}

function submit() {
    const options = { onError: unsaved.stopSaving };

    unsaved.startSaving();

    if (props.patient) {
        form.put(patientRoutes.update.url(props.patient.id), options);
    } else {
        form.post(patientRoutes.store.url(), options);
    }
}
</script>

<template>
    <Head :title="patient ? t('Edit patient') : t('New patient')" />

    <div class="flex items-start gap-6">
        <form class="min-w-0 flex-1 space-y-6" @submit.prevent="submit">
            <PageHeader
                :title="patient ? t('Edit patient') : t('New patient')"
                :description="
                    patient
                        ? patient.name
                        : t('Fields left empty are stored as not recorded')
                "
                :back-href="backHref"
            >
                <UnsavedChanges
                    v-model:confirming="unsaved.confirming.value"
                    :dirty="isDirty"
                    @leave="unsaved.leave"
                />
                <Button
                    v-if="patient"
                    type="button"
                    :variant="panel.open ? 'secondary' : 'outline'"
                    :aria-pressed="panel.open"
                    @click="togglePanel"
                >
                    <PanelRight />
                    {{ t('Patient record') }}
                </Button>
                <Button
                    v-if="patient"
                    type="button"
                    variant="outline"
                    @click="showPanel('attachments')"
                >
                    <Images />
                    {{ t('Attachments') }}
                    <span
                        v-if="attachments?.length"
                        class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums"
                    >
                        {{ attachments.length }}
                    </span>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="backHref">{{ t('Cancel') }}</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ patient ? t('Save changes') : t('Register patient') }}
                </Button>
            </PageHeader>

            <div
                v-if="duplicates.length"
                class="rounded-xl border border-warning/40 bg-warning/10 px-5 py-4"
            >
                <p
                    class="flex items-center gap-2 text-sm font-semibold text-warning"
                >
                    <TriangleAlert class="size-4" />
                    {{ t('This baby may already be registered') }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        t(
                            'Open the record to check before saving. Twins share a date of birth and are registered separately.',
                        )
                    }}
                </p>
                <ul class="mt-3 grid gap-2 md:grid-cols-2">
                    <li v-for="match in duplicates" :key="match.id">
                        <a
                            :href="patientRoutes.show.url(match.id)"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2 text-sm hover:bg-muted/60"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">
                                    <bdi>{{ match.name }}</bdi>
                                </span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    <template v-if="match.file_number">
                                        #{{ match.file_number }} ·
                                    </template>
                                    {{ formatDate(match.dob) }}
                                    <template v-if="match.ga_weeks !== null">
                                        · {{ t('GA') }}
                                        {{
                                            t(':weeks w', {
                                                weeks: match.ga_weeks,
                                            })
                                        }}
                                    </template>
                                    <template v-if="match.unverified">
                                        · {{ t('Unverified') }}
                                    </template>
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-warning">
                                {{ t(duplicateReasons[match.reason]) }}
                            </span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div class="space-y-6 md:col-span-2">
                    <SectionCard :title="t('Identity')" :icon="UserRound">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-6">
                            <FormField
                                class="col-span-2 md:col-span-4"
                                :label="t('Baby full name')"
                                for="name"
                                :error="form.errors.name"
                                required
                            >
                                <div class="relative">
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        v-focus="!patient"
                                        dir="auto"
                                        data-keyboard="ar"
                                        :autocomplete="
                                            patient ? undefined : 'off'
                                        "
                                        required
                                        @focus="nameLookupOpen = true"
                                        @blur="closeNameLookup"
                                        @keydown.down="
                                            moveNameLookup($event, 1)
                                        "
                                        @keydown.up="moveNameLookup($event, -1)"
                                        @keydown.esc="closeNameLookup"
                                        @keydown.enter="chooseNameLookup"
                                    />
                                    <div
                                        v-if="showNameLookup"
                                        class="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-lg border bg-popover text-sm text-popover-foreground shadow-lg"
                                        @mousedown.prevent
                                    >
                                        <p
                                            class="border-b bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
                                        >
                                            {{
                                                t(
                                                    'Already registered: open the record instead?',
                                                )
                                            }}
                                        </p>
                                        <PatientLookupResults
                                            v-model:active="nameLookupActive"
                                            :matches="nameLookup.matches.value"
                                            :loading="nameLookup.loading.value"
                                            @select="openExisting"
                                        />
                                    </div>
                                </div>
                            </FormField>
                            <FormField
                                class="col-span-2"
                                :label="t('Clinic file no.')"
                                for="file_number"
                                :error="form.errors.file_number"
                            >
                                <TextInput
                                    id="file_number"
                                    v-model="form.file_number"
                                    dir="ltr"
                                    data-keyboard="en"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-6"
                                :label="t('Mother name')"
                                for="mother_name"
                                :error="form.errors.mother_name"
                            >
                                <TextInput
                                    id="mother_name"
                                    v-model="form.mother_name"
                                    dir="auto"
                                    data-keyboard="ar"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-3"
                                :label="t('Sex')"
                                :error="form.errors.sex"
                            >
                                <SegmentedControl
                                    v-model="form.sex"
                                    :options="enumOptions('sex')"
                                    :nullable="false"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-3"
                                :label="t('Date of birth')"
                                for="dob"
                                :error="form.errors.dob"
                                :hint="
                                    ageToday
                                        ? t('Age today: :age', {
                                              age: formatAge(ageToday.age),
                                          }) +
                                          (ageToday.pma !== null
                                              ? ` · ${t('PMA')} ${formatWeeks(ageToday.pma)}`
                                              : '')
                                        : undefined
                                "
                            >
                                <DateInput
                                    id="dob"
                                    v-model="form.dob"
                                    :max="todayIso()"
                                />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard :title="t('Birth history')" :icon="Baby">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-6">
                            <FormField
                                class="md:col-span-2"
                                :label="t('Gestational age (weeks)')"
                                for="ga_weeks"
                                :error="form.errors.ga_weeks"
                            >
                                <TextInput
                                    id="ga_weeks"
                                    v-model="form.ga_weeks"
                                    type="number"
                                    min="20"
                                    max="44"
                                />
                            </FormField>
                            <FormField
                                class="md:col-span-2"
                                :label="t('+ days')"
                                for="ga_days"
                                :error="form.errors.ga_days"
                            >
                                <TextInput
                                    id="ga_days"
                                    v-model="form.ga_days"
                                    type="number"
                                    min="0"
                                    max="6"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2"
                                :label="t('Birth weight (g)')"
                                for="birth_weight_g"
                                :error="form.errors.birth_weight_g"
                            >
                                <TextInput
                                    id="birth_weight_g"
                                    v-model="form.birth_weight_g"
                                    type="number"
                                    min="200"
                                    max="7000"
                                    step="10"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-3"
                                :label="t('Multiple births')"
                                :error="form.errors.multiplicity"
                            >
                                <SegmentedControl
                                    v-model="form.multiplicity"
                                    :options="
                                        enumOptions('multiplicity').filter(
                                            (option) =>
                                                option.value !== 'unknown',
                                        )
                                    "
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-3"
                                :label="t('Delivery mode')"
                                :error="form.errors.delivery_mode"
                            >
                                <NativeSelect
                                    v-model="form.delivery_mode"
                                    :options="enumOptions('delivery_mode')"
                                    :placeholder="t('Not recorded')"
                                />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard
                        :title="t('Neonatal course')"
                        :icon="HeartPulse"
                    >
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-6">
                            <FormField
                                class="col-span-2"
                                :label="t('NICU stay (days)')"
                                for="nicu_days"
                                :error="form.errors.nicu_days"
                            >
                                <TextInput
                                    id="nicu_days"
                                    v-model="form.nicu_days"
                                    type="number"
                                    min="0"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2"
                                :label="t('Respiratory support')"
                                :error="form.errors.respiratory_support"
                            >
                                <NativeSelect
                                    v-model="form.respiratory_support"
                                    :options="
                                        enumOptions('respiratory_support')
                                    "
                                    :placeholder="t('Not recorded')"
                                />
                            </FormField>
                            <FormField
                                v-if="supportDaysLabel"
                                class="col-span-2"
                                :label="supportDaysLabel"
                                for="support_days"
                                :error="form.errors.support_days"
                            >
                                <TextInput
                                    id="support_days"
                                    v-model="form.support_days"
                                    type="number"
                                    min="0"
                                />
                            </FormField>
                            <template
                                v-if="form.respiratory_support === 'o2_cpap'"
                            >
                                <FormField
                                    class="col-span-2"
                                    :label="t('O₂ days')"
                                    for="o2_days"
                                    :error="form.errors.o2_days"
                                >
                                    <TextInput
                                        id="o2_days"
                                        v-model="form.o2_days"
                                        type="number"
                                        min="0"
                                    />
                                </FormField>
                                <FormField
                                    class="col-span-2"
                                    :label="t('CPAP days')"
                                    for="cpap_days"
                                    :error="form.errors.cpap_days"
                                >
                                    <TextInput
                                        id="cpap_days"
                                        v-model="form.cpap_days"
                                        type="number"
                                        min="0"
                                    />
                                </FormField>
                                <FormField
                                    v-if="hasCombinedTotal"
                                    class="col-span-2"
                                    :label="t('Total support (days)')"
                                    for="support_days"
                                    :error="form.errors.support_days"
                                    :hint="t('Recorded earlier as one total')"
                                >
                                    <TextInput
                                        id="support_days"
                                        v-model="form.support_days"
                                        type="number"
                                        min="0"
                                    />
                                </FormField>
                            </template>
                            <FormField
                                class="col-span-2 md:col-span-6"
                                :label="t('Associated systemic illness')"
                                :error="
                                    (form.errors as Record<string, string>)
                                        .illnesses
                                "
                                :hint="t('Tick all that apply')"
                            >
                                <CheckList
                                    v-model="illnesses"
                                    :options="illnessOptions"
                                />
                            </FormField>
                            <FormField
                                class="col-span-2 md:col-span-6"
                                :label="t('Other illnesses or details')"
                                for="systemic_illness"
                                :error="form.errors.systemic_illness"
                            >
                                <AddSelectionToList
                                    list="illness"
                                    :options="illnessOptions"
                                    @added="tickIllness"
                                >
                                    <TextInput
                                        id="systemic_illness"
                                        v-model="form.systemic_illness"
                                        dir="auto"
                                    />
                                </AddSelectionToList>
                            </FormField>
                        </div>
                    </SectionCard>
                </div>

                <div class="space-y-6">
                    <SectionCard :title="t('Referral & contact')" :icon="Phone">
                        <div class="grid gap-4">
                            <FormField
                                :label="t('Referral date')"
                                for="referral_date"
                                :error="form.errors.referral_date"
                            >
                                <DateInput
                                    id="referral_date"
                                    v-model="form.referral_date"
                                />
                            </FormField>
                            <FormField
                                :label="t('Referring doctor')"
                                for="referring_doctor"
                                :error="form.errors.referring_doctor"
                            >
                                <SuggestInput
                                    id="referring_doctor"
                                    v-model="form.referring_doctor"
                                    :options="doctorOptions"
                                    list="referring_doctor"
                                    dir="auto"
                                    data-keyboard="ar"
                                />
                            </FormField>
                            <FormField
                                :label="t('Parent phone')"
                                for="phone"
                                :error="form.errors.phone"
                            >
                                <TextInput
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    dir="ltr"
                                    data-keyboard="en"
                                />
                            </FormField>
                            <FormField
                                :label="t('Second phone')"
                                for="phone_alt"
                                :error="form.errors.phone_alt"
                            >
                                <TextInput
                                    id="phone_alt"
                                    v-model="form.phone_alt"
                                    type="tel"
                                    dir="ltr"
                                    data-keyboard="en"
                                />
                            </FormField>
                            <FormField
                                :label="t('Address')"
                                for="address"
                                :error="form.errors.address"
                            >
                                <TextInput
                                    id="address"
                                    v-model="form.address"
                                    dir="auto"
                                    data-keyboard="ar"
                                />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard :title="t('Follow-up')" :icon="NotebookPen">
                        <div class="grid gap-4">
                            <FormField
                                :label="t('Status')"
                                :error="form.errors.status"
                            >
                                <NativeSelect
                                    v-model="form.status"
                                    :options="enumOptions('patient_status')"
                                    :nullable="false"
                                />
                            </FormField>
                            <FormField
                                :label="t('Notes')"
                                :error="form.errors.notes"
                            >
                                <TextArea v-model="form.notes" :rows="5" />
                            </FormField>
                        </div>
                    </SectionCard>
                </div>
            </div>

            <section class="space-y-4 border-t pt-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <Stethoscope class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">
                                {{ t('Initial examination') }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ visitDescription }}
                            </p>
                        </div>
                    </div>
                    <label
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg border bg-card px-3 py-2 text-sm font-medium shadow-xs"
                    >
                        <Switch v-model="withVisit" />
                        {{
                            firstVisit
                                ? t('Edit this examination')
                                : t('Record the first examination now')
                        }}
                    </label>
                </div>

                <p v-if="form.errors.visit_id" class="text-sm text-destructive">
                    {{ form.errors.visit_id }}
                </p>

                <VisitFields
                    v-if="withVisit"
                    :form="visitForm"
                    :errors="visitErrors"
                    :common-fees="commonFees"
                    :finding-options="findingOptions"
                    :date-hint="examinationHint"
                />
                <VisitCard
                    v-else-if="firstVisit"
                    :visit="firstVisit"
                    :number="1"
                    :attachments-count="attachmentsOfFirstVisit"
                    readonly
                />
            </section>

            <FormSaveBar
                :label="patient ? t('Save changes') : t('Register patient')"
                :cancel-href="backHref"
                :processing="form.processing"
                :dirty="isDirty"
            />
        </form>

        <PatientPanel
            v-if="patient && panel.open"
            v-model:tab="panel.tab"
            :patient="patient"
            :visits="visits ?? []"
            :treatments="treatments ?? []"
            :attachments="attachments ?? []"
            :current-visit-id="withVisit ? firstVisit?.id : null"
            @close="panel.open = false"
        />
    </div>
</template>
