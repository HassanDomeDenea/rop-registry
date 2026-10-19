<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Check,
    Merge,
    Search,
    TriangleAlert,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { Patient } from '@/types';

type Counts = {
    visits: number;
    treatments: number;
    attachments: number;
    review_items: number;
};

type Candidate = {
    id: number;
    name: string;
    file_number: string | null;
    dob: string | null;
    unverified: boolean;
    reason?: 'name' | 'dob' | 'name_and_dob';
};

/**
 * Combines two records of the same baby: the record on the left is kept and receives
 * everything recorded under the one on the right, which then goes to the recycle bin.
 */
const props = defineProps<{
    patient: Patient;
    duplicate: Patient | null;
    counts: { patient: Counts; duplicate: Counts | null };
    suggestions: Candidate[];
}>();

const {
    t,
    enumLabel,
    formatDate,
    formatGestationalAge,
    formatNumber,
    formatRespiratorySupport,
} = useI18n();

const reasons: Record<NonNullable<Candidate['reason']>, string> = {
    name: 'Similar name',
    dob: 'Same date of birth',
    name_and_dob: 'Similar name and same date of birth',
};

const search = ref<string | null>('');
const searching = ref(false);
const results = ref<Candidate[]>([]);

const lookup = useDebounceFn(async () => {
    const term = (search.value ?? '').trim();

    if (term === '') {
        results.value = [];
        searching.value = false;

        return;
    }

    const response = await fetch(
        patientRoutes.lookup.url({ query: { search: term } }),
        { headers: { Accept: 'application/json' } },
    );

    const found = response.ok
        ? ((await response.json()) as { patients: Candidate[] }).patients
        : [];

    results.value = found.filter((match) => match.id !== props.patient.id);
    searching.value = false;
}, 250);

watch(search, () => {
    searching.value = (search.value ?? '').trim() !== '';
    void lookup();
});

function compareHref(id: number) {
    return patientRoutes.merge.create.url(props.patient.id, {
        query: { with: id },
    });
}

type Field = {
    key: string;
    label: string;
    values: (patient: Patient) => unknown[];
    display: (patient: Patient) => string;
};

/** The details that can come from either record, matching PatientMerger::FIELDS. */
const fields = computed<Field[]>(() => [
    {
        key: 'file_number',
        label: t('Clinic file no.'),
        values: (p) => [p.file_number],
        display: (p) => p.file_number ?? '',
    },
    {
        key: 'name',
        label: t('Baby full name'),
        values: (p) => [p.name],
        display: (p) => p.name,
    },
    {
        key: 'mother_name',
        label: t('Mother name'),
        values: (p) => [p.mother_name],
        display: (p) => p.mother_name ?? '',
    },
    {
        key: 'sex',
        label: t('Sex'),
        values: (p) => [p.sex],
        display: (p) => enumLabel('sex', p.sex),
    },
    {
        key: 'dob',
        label: t('Date of birth'),
        values: (p) => [p.dob],
        display: (p) => (p.dob ? formatDate(p.dob) : ''),
    },
    {
        key: 'gestational_age',
        label: t('Gestational age'),
        values: (p) => [p.ga_weeks, p.ga_days],
        display: (p) =>
            p.ga_weeks === null
                ? ''
                : formatGestationalAge(p.ga_weeks, p.ga_days),
    },
    {
        key: 'birth_weight_g',
        label: t('Birth weight (g)'),
        values: (p) => [p.birth_weight_g],
        display: (p) => formatNumber(p.birth_weight_g, ''),
    },
    {
        key: 'multiplicity',
        label: t('Multiple births'),
        values: (p) => [p.multiplicity],
        display: (p) =>
            p.multiplicity ? enumLabel('multiplicity', p.multiplicity) : '',
    },
    {
        key: 'delivery_mode',
        label: t('Delivery mode'),
        values: (p) => [p.delivery_mode],
        display: (p) =>
            p.delivery_mode ? enumLabel('delivery_mode', p.delivery_mode) : '',
    },
    {
        key: 'nicu_days',
        label: t('NICU stay (days)'),
        values: (p) => [p.nicu_days],
        display: (p) => formatNumber(p.nicu_days, ''),
    },
    {
        key: 'respiratory_support',
        label: t('Respiratory support'),
        values: (p) => [
            p.respiratory_support,
            p.support_days,
            p.o2_days,
            p.cpap_days,
        ],
        display: (p) => formatRespiratorySupport(p, ''),
    },
    {
        key: 'systemic_illness',
        label: t('Other illnesses or details'),
        values: (p) => [p.systemic_illness],
        display: (p) => p.systemic_illness ?? '',
    },
    {
        key: 'referral_date',
        label: t('Referral date'),
        values: (p) => [p.referral_date],
        display: (p) => (p.referral_date ? formatDate(p.referral_date) : ''),
    },
    {
        key: 'referring_doctor',
        label: t('Referring doctor'),
        values: (p) => [p.referring_doctor],
        display: (p) => p.referring_doctor ?? '',
    },
    {
        key: 'phone',
        label: t('Parent phone'),
        values: (p) => [p.phone],
        display: (p) => p.phone ?? '',
    },
    {
        key: 'phone_alt',
        label: t('Second phone'),
        values: (p) => [p.phone_alt],
        display: (p) => p.phone_alt ?? '',
    },
    {
        key: 'address',
        label: t('Address'),
        values: (p) => [p.address],
        display: (p) => p.address ?? '',
    },
    {
        key: 'status',
        label: t('Status'),
        values: (p) => [p.status],
        display: (p) => enumLabel('patient_status', p.status),
    },
]);

function isBlank(values: unknown[]) {
    return values.every(
        (value) => value === null || value === '' || value === 'unknown',
    );
}

type Side = 'kept' | 'duplicate';

type Row = Field & {
    kept: string;
    duplicate: string;
    /** Where the value comes from when there is nothing to choose. */
    fixed: Side | null;
    conflict: boolean;
};

const rows = computed<Row[]>(() => {
    const duplicate = props.duplicate;

    if (!duplicate) {
        return [];
    }

    return fields.value.map((field) => {
        const keptValues = field.values(props.patient);
        const duplicateValues = field.values(duplicate);
        const keptBlank = isBlank(keptValues);
        const duplicateBlank = isBlank(duplicateValues);
        const same =
            JSON.stringify(keptValues) === JSON.stringify(duplicateValues);

        return {
            ...field,
            kept: field.display(props.patient),
            duplicate: field.display(duplicate),
            fixed:
                keptBlank && !duplicateBlank
                    ? 'duplicate'
                    : !keptBlank && duplicateBlank
                      ? 'kept'
                      : null,
            conflict: !keptBlank && !duplicateBlank && !same,
        };
    });
});

const choices = ref<Record<string, Side>>({});

function chosen(row: Row): Side | null {
    if (row.fixed) {
        return row.fixed;
    }

    return row.conflict ? (choices.value[row.key] ?? 'kept') : null;
}

function choose(row: Row, side: Side) {
    if (row.conflict) {
        choices.value[row.key] = side;
    }
}

const conflicts = computed(
    () => rows.value.filter((row) => row.conflict).length,
);

const recordCounts = computed(() => {
    const kept = props.counts.patient;
    const duplicate = props.counts.duplicate;

    return [
        { label: t('Visits'), kept: kept.visits, duplicate: duplicate?.visits },
        {
            label: t('Treatments'),
            kept: kept.treatments,
            duplicate: duplicate?.treatments,
        },
        {
            label: t('Attachments'),
            kept: kept.attachments,
            duplicate: duplicate?.attachments,
        },
        {
            label: t('Review'),
            kept: kept.review_items,
            duplicate: duplicate?.review_items,
        },
    ];
});

const confirming = ref(false);
const merging = ref(false);

function merge() {
    if (!props.duplicate) {
        return;
    }

    router.post(
        patientRoutes.merge.store.url(props.patient.id),
        {
            duplicate_id: props.duplicate.id,
            choices: Object.fromEntries(
                rows.value
                    .filter((row) => row.conflict)
                    .map((row) => [row.key, chosen(row)]),
            ),
        },
        {
            onStart: () => (merging.value = true),
            onFinish: () => {
                merging.value = false;
                confirming.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('Merge duplicate records')" />

    <div class="space-y-6">
        <PageHeader
            :title="t('Merge duplicate records')"
            :description="patient.name"
            :back-href="patientRoutes.show.url(patient.id)"
        >
            <template v-if="duplicate">
                <Button as-child variant="outline">
                    <Link :href="patientRoutes.merge.create.url(patient.id)">
                        {{ t('Choose another record') }}
                    </Link>
                </Button>
                <Button :disabled="merging" @click="confirming = true">
                    <Merge />
                    {{ t('Merge records') }}
                </Button>
            </template>
        </PageHeader>

        <template v-if="!duplicate">
            <SectionCard
                v-if="suggestions.length"
                :title="t('Possible duplicates')"
                :description="
                    t(
                        'Registered patients with a similar name or the same date of birth',
                    )
                "
                :icon="TriangleAlert"
                flush
            >
                <ul class="divide-y">
                    <li v-for="match in suggestions" :key="match.id">
                        <Link
                            :href="compareHref(match.id)"
                            class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-muted/60"
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
                                    <template v-if="match.unverified">
                                        · {{ t('Unverified') }}
                                    </template>
                                </span>
                            </span>
                            <span
                                v-if="match.reason"
                                class="shrink-0 text-xs text-warning"
                            >
                                {{ t(reasons[match.reason]) }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </SectionCard>

            <SectionCard :title="t('Find the other record')" :icon="Search">
                <div class="relative">
                    <TextInput
                        v-model="search"
                        v-focus
                        dir="auto"
                        :placeholder="
                            t('Search patients by name, file no. or phone…')
                        "
                    />
                    <Spinner
                        v-if="searching"
                        class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                    />
                </div>
                <ul
                    v-if="results.length"
                    class="mt-3 divide-y rounded-lg border"
                >
                    <li v-for="match in results" :key="match.id">
                        <Link
                            :href="compareHref(match.id)"
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-muted/60"
                        >
                            <span class="min-w-0 truncate font-medium">
                                <bdi>{{ match.name }}</bdi>
                            </span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground tabular-nums"
                            >
                                <template v-if="match.file_number">
                                    #{{ match.file_number }} ·
                                </template>
                                {{ formatDate(match.dob) }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <p
                    v-else-if="search?.trim() && !searching"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ t('No patient matches') }}
                </p>
            </SectionCard>
        </template>

        <template v-else>
            <p
                class="rounded-lg border bg-muted px-4 py-3 text-sm text-muted-foreground"
            >
                {{
                    t(
                        'Everything recorded under the second record moves to the kept record, which then goes to the recycle bin. Pick which value to keep where the records disagree; notes are kept from both.',
                    )
                }}
            </p>

            <SectionCard flush>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-start">
                                <th
                                    class="w-48 px-5 py-3 text-start text-xs font-medium text-muted-foreground"
                                >
                                    <span v-if="conflicts" class="text-warning">
                                        {{
                                            t(':count to choose', {
                                                count: conflicts,
                                            })
                                        }}
                                    </span>
                                </th>
                                <th class="px-3 py-3 text-start">
                                    <span
                                        class="block text-xs font-medium text-muted-foreground"
                                    >
                                        {{ t('Kept record') }}
                                    </span>
                                    <Link
                                        :href="
                                            patientRoutes.show.url(patient.id)
                                        "
                                        class="font-semibold hover:underline"
                                    >
                                        <bdi>{{ patient.name }}</bdi>
                                    </Link>
                                </th>
                                <th class="px-3 py-3 text-start">
                                    <span
                                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ t('Merged into it, then removed') }}
                                        <Link
                                            :href="
                                                patientRoutes.merge.create.url(
                                                    duplicate.id,
                                                    {
                                                        query: {
                                                            with: patient.id,
                                                        },
                                                    },
                                                )
                                            "
                                            class="inline-flex items-center gap-1 text-primary hover:underline"
                                            :title="
                                                t('Keep this record instead')
                                            "
                                        >
                                            <ArrowLeftRight class="size-3.5" />
                                            {{ t('Swap') }}
                                        </Link>
                                    </span>
                                    <Link
                                        :href="
                                            patientRoutes.show.url(duplicate.id)
                                        "
                                        class="font-semibold hover:underline"
                                    >
                                        <bdi>{{ duplicate.name }}</bdi>
                                    </Link>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in rows" :key="row.key">
                                <th
                                    scope="row"
                                    class="px-5 py-2 text-start font-normal text-muted-foreground"
                                >
                                    {{ row.label }}
                                </th>
                                <td
                                    v-for="side in [
                                        'kept',
                                        'duplicate',
                                    ] as const"
                                    :key="side"
                                    class="px-1.5 py-1"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-start"
                                        :class="[
                                            chosen(row) === side
                                                ? 'bg-primary/10 ring-1 ring-primary/40'
                                                : chosen(row) !== null
                                                  ? 'text-muted-foreground line-through decoration-muted-foreground/40'
                                                  : 'text-muted-foreground',
                                            row.conflict
                                                ? 'cursor-pointer hover:bg-muted'
                                                : 'cursor-default',
                                        ]"
                                        :disabled="!row.conflict"
                                        :aria-pressed="chosen(row) === side"
                                        @click="choose(row, side)"
                                    >
                                        <span
                                            v-if="row.conflict"
                                            class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                                            :class="
                                                chosen(row) === side
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : ''
                                            "
                                        >
                                            <Check
                                                v-if="chosen(row) === side"
                                                class="size-3"
                                            />
                                        </span>
                                        <span
                                            class="min-w-0 break-words"
                                            dir="auto"
                                        >
                                            {{ row[side] || '—' }}
                                        </span>
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-for="count in recordCounts"
                                :key="count.label"
                                class="bg-muted/30"
                            >
                                <th
                                    scope="row"
                                    class="px-5 py-2 text-start font-normal text-muted-foreground"
                                >
                                    {{ count.label }}
                                </th>
                                <td class="px-3 py-2 tabular-nums">
                                    {{ count.kept }}
                                    <span
                                        v-if="count.duplicate"
                                        class="text-xs text-primary"
                                    >
                                        →
                                        {{ count.kept + count.duplicate }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 text-muted-foreground tabular-nums"
                                >
                                    {{ count.duplicate }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>
        </template>
    </div>

    <ConfirmDialog
        v-if="duplicate"
        v-model:open="confirming"
        :title="t('Merge these records?')"
        :description="
            t(
                'The record of :name moves to the recycle bin after its visits, treatments and attachments join this patient.',
                { name: duplicate.name },
            )
        "
        :confirm-label="t('Merge records')"
        :destructive="false"
        :processing="merging"
        @confirm="merge"
    />
</template>
