<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleHelp,
    ClipboardCheck,
    Columns3,
    Download,
    FileSpreadsheet,
    FileText,
    Printer,
    RotateCcw,
    Search,
    Stethoscope,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { useDebounceFn, useStorage } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pagination from '@/components/registry/Pagination.vue';
import PatientFlags from '@/components/registry/PatientFlags.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import SuggestInput from '@/components/registry/SuggestInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { FindingOption, Paginated, PatientRow } from '@/types';

type Filters = {
    search: string;
    status: string;
    sex: string;
    rop: string;
    treatment: string;
    /** Patients with this eye finding ticked at one visit or more. */
    finding: number | null;
    /** A referring doctor, or "none" for the patients without one. */
    doctor: string;
    review: boolean;
    unverified: boolean;
    trashed: boolean;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

const props = defineProps<{
    patients: Paginated<PatientRow>;
    filters: Filters;
    findingOptions: FindingOption[];
    /** The referring doctors on record, with the number of patients of each. */
    doctorFilterOptions: { label: string; patients: number }[];
    doctorOptions: string[];
    unverifiedCount: number;
}>();

const {
    t,
    enumLabel,
    enumOptions,
    formatDate,
    formatGestationalAge,
    formatNumber,
    formatRelativeDays,
} = useI18n();

const state = reactive<Filters>({ ...props.filters });

const allColumns = computed<Column[]>(() => [
    {
        key: 'file_number',
        label: t('File no.'),
        sortable: true,
        class: 'w-20 tabular-nums text-muted-foreground',
    },
    {
        key: 'name',
        label: t('Patient'),
        sortable: true,
        class: 'max-md:min-w-40',
    },
    {
        key: 'dob',
        label: t('Born'),
        sortable: true,
        class: 'whitespace-nowrap tabular-nums',
    },
    {
        key: 'ga_weeks',
        label: t('GA'),
        sortable: true,
        class: 'whitespace-nowrap tabular-nums',
    },
    {
        key: 'birth_weight_g',
        label: t('Weight (g)'),
        sortable: true,
        align: 'end',
        class: 'tabular-nums',
    },
    { key: 'referring_doctor', label: t('Referring doctor'), sortable: true },
    {
        key: 'referral_date',
        label: t('Referral date'),
        sortable: true,
        class: 'whitespace-nowrap tabular-nums',
    },
    { key: 'rop', label: t('ROP') },
    {
        key: 'exams_count',
        label: t('Exams'),
        sortable: true,
        align: 'center',
        class: 'tabular-nums',
    },
    {
        key: 'last_visit_date',
        label: t('Last visit'),
        sortable: true,
        class: 'whitespace-nowrap tabular-nums',
    },
    {
        key: 'next_appointment_date',
        label: t('Next appointment'),
        sortable: true,
        class: 'whitespace-nowrap',
    },
    { key: 'status', label: t('Status'), sortable: true },
    { key: 'actions', label: '', align: 'end', class: 'w-10' },
]);

/** The patient name and the actions are always shown. */
const fixedColumns = ['name', 'actions'];

/** The columns switched off, remembered in this browser. */
const hiddenColumns = useStorage<string[]>('registry.patient-columns', [
    'referral_date',
]);

const optionalColumns = computed(() =>
    allColumns.value.filter((column) => !fixedColumns.includes(column.key)),
);

const columns = computed(() =>
    allColumns.value.filter(
        (column) => !hiddenColumns.value.includes(column.key),
    ),
);

function toggleColumn(key: string, shown: boolean | 'indeterminate') {
    hiddenColumns.value =
        shown === true
            ? hiddenColumns.value.filter((hidden) => hidden !== key)
            : [...hiddenColumns.value, key];
}

const statusTones: Record<string, PillTone> = {
    active: 'info',
    discharged: 'success',
    referred: 'warning',
    lost: 'neutral',
    deceased: 'neutral',
};

const ropOptions = computed(() => [
    { value: 'yes', label: t('ROP documented') },
    { value: 'no', label: t('No ROP documented') },
    { value: 'unknown', label: t('Not documented') },
    { value: 'type_one', label: t('Type 1 ROP') },
]);

const treatmentOptions = computed(() => [
    { value: 'injection', label: t('Injection performed') },
    { value: 'laser', label: t('Laser performed') },
    { value: 'pending', label: t('Treatment pending') },
    { value: 'none', label: t('No treatment recorded') },
]);

const findingSelectOptions = computed(() =>
    props.findingOptions.map((option) => ({
        value: String(option.id),
        label: option.label,
    })),
);

const doctorSelectOptions = computed(() => [
    { value: 'none', label: t('No referring doctor') },
    ...props.doctorFilterOptions.map((doctor) => ({
        value: doctor.label,
        label: `${doctor.label} (${formatNumber(doctor.patients)})`,
    })),
]);

const hasFilters = computed(
    () =>
        state.search !== '' ||
        state.doctor !== '' ||
        state.status !== '' ||
        state.sex !== '' ||
        state.rop !== '' ||
        state.treatment !== '' ||
        state.finding !== null ||
        state.review ||
        state.unverified,
);

/** Only non-default values are kept in the address bar. */
function query(): Record<string, string | number | boolean> {
    const defaults: Filters = {
        search: '',
        status: '',
        sex: '',
        rop: '',
        treatment: '',
        finding: null,
        doctor: '',
        review: false,
        unverified: false,
        trashed: false,
        sort: 'created_at',
        direction: 'desc',
        per_page: 25,
    };

    return Object.fromEntries(
        Object.entries(state)
            .filter(
                (entry): entry is [string, string | number | boolean] =>
                    entry[1] !== null &&
                    entry[1] !== defaults[entry[0] as keyof Filters],
            )
            .map(([key, value]) => [key, value === true ? 1 : value]),
    );
}

function reload() {
    router.get(patientRoutes.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const reloadDebounced = useDebounceFn(reload, 300);

function setFilter<K extends keyof Filters>(key: K, value: Filters[K] | null) {
    state[key] = (value ?? '') as Filters[K];
    reload();
}

function setFinding(value: string | number | boolean | null | undefined) {
    state.finding = value ? Number(value) : null;
    reload();
}

function sortBy(key: string, direction: 'asc' | 'desc') {
    state.sort = key;
    state.direction = direction;
    reload();
}

function reset() {
    Object.assign(state, {
        search: '',
        status: '',
        sex: '',
        rop: '',
        treatment: '',
        finding: null,
        doctor: '',
        review: false,
        unverified: false,
    });
    reload();
}

/** The recycle bin is opened from the settings; this returns to the patients. */
function leaveTrashed() {
    state.trashed = false;
    reload();
}

function restore(patient: PatientRow) {
    router.post(patientRoutes.restore.url(patient.id));
}

function rowClass(patient: PatientRow) {
    // The same row highlighting as the original workbook: lavender for a completed
    // injection, peach for completed laser, with the injection taking precedence.
    if (patient.had_injection) {
        return 'bg-eylea/[0.06] hover:bg-eylea/10';
    }

    if (patient.had_laser) {
        return 'bg-laser/[0.06] hover:bg-laser/10';
    }

    if (patient.unverified) {
        return 'text-muted-foreground';
    }

    return undefined;
}

function printTable() {
    window.print();
}

/** The patients ticked for a bulk change; kept while moving between pages. */
const selected = ref<number[]>([]);
const doctorDialog = ref(false);
const bulkDoctor = ref<string | number | null>(null);
const bulkProcessing = ref(false);

function openDoctorDialog() {
    const doctors = new Set(
        props.patients.data
            .filter((patient) => selected.value.includes(patient.id))
            .map((patient) => patient.referring_doctor),
    );

    // Start from the doctor the ticked patients share, if they share one.
    bulkDoctor.value = doctors.size === 1 ? [...doctors][0] : null;
    doctorDialog.value = true;
}

function applyDoctor() {
    router.put(
        patientRoutes.referringDoctor.url(),
        { ids: selected.value, referring_doctor: bulkDoctor.value },
        {
            preserveScroll: true,
            onStart: () => (bulkProcessing.value = true),
            onFinish: () => (bulkProcessing.value = false),
            onSuccess: () => {
                doctorDialog.value = false;
                selected.value = [];
            },
        },
    );
}
</script>

<template>
    <Head :title="t('Patients')" />

    <PageHeader
        :title="state.trashed ? t('Recycle bin') : t('Patients')"
        :description="
            t(':count patients', { count: formatNumber(patients.total) })
        "
        class="no-print"
    >
        <Button v-if="state.trashed" variant="ghost" @click="leaveTrashed">
            <Users />
            {{ t('Back to patients') }}
        </Button>
        <Button variant="outline" @click="printTable">
            <Printer />
            {{ t('Print') }}
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline">
                    <Download />
                    {{ t('Export') }}
                    <ChevronDown class="opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-72">
                <DropdownMenuItem as-child>
                    <a
                        :href="patientRoutes.workbook.url({ query: query() })"
                        class="flex cursor-pointer items-start gap-3"
                    >
                        <FileSpreadsheet class="mt-0.5 size-4 text-success" />
                        <span>
                            <span class="block font-medium">
                                {{ t('Excel workbook') }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{
                                    t(
                                        'One row per baby, visits, treatments, statistics and review log',
                                    )
                                }}
                            </span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <a
                        :href="patientRoutes.export.url({ query: query() })"
                        class="flex cursor-pointer items-start gap-3"
                    >
                        <FileText class="mt-0.5 size-4 text-muted-foreground" />
                        <span>
                            <span class="block font-medium">
                                {{ t('CSV patient list') }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ t('A plain table of the patients shown') }}
                            </span>
                        </span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <Button as-child>
            <Link :href="patientRoutes.create()">
                <UserPlus />
                {{ t('New patient') }}
            </Link>
        </Button>
    </PageHeader>

    <section class="rounded-xl border bg-card shadow-xs">
        <div class="no-print flex flex-wrap items-center gap-2 border-b p-3">
            <div
                class="relative min-w-64 flex-1 max-md:min-w-0 max-md:basis-full"
            >
                <Search
                    class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="state.search"
                    type="search"
                    data-keyboard="ar"
                    :placeholder="
                        t(
                            'Search by name, file no., phone or referring doctor…',
                        )
                    "
                    class="h-9 w-full rounded-md border border-input bg-transparent ps-8 pe-3 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    @input="reloadDebounced"
                />
            </div>
            <NativeSelect
                class="w-44 max-md:w-[calc(50%-0.25rem)]"
                :model-value="state.status || null"
                :options="enumOptions('patient_status')"
                :placeholder="t('Any status')"
                @update:model-value="setFilter('status', $event as string)"
            />
            <NativeSelect
                class="w-32 max-md:w-[calc(50%-0.25rem)]"
                :model-value="state.sex || null"
                :options="enumOptions('sex')"
                :placeholder="t('Any sex')"
                @update:model-value="setFilter('sex', $event as string)"
            />
            <NativeSelect
                class="w-48 max-md:w-[calc(50%-0.25rem)]"
                :model-value="state.rop || null"
                :options="ropOptions"
                :placeholder="t('Any ROP status')"
                @update:model-value="setFilter('rop', $event as string)"
            />
            <NativeSelect
                class="w-48 max-md:w-[calc(50%-0.25rem)]"
                :model-value="state.treatment || null"
                :options="treatmentOptions"
                :placeholder="t('Any treatment')"
                @update:model-value="setFilter('treatment', $event as string)"
            />
            <NativeSelect
                v-if="findingSelectOptions.length"
                class="w-48"
                :model-value="
                    state.finding === null ? null : String(state.finding)
                "
                :options="findingSelectOptions"
                :placeholder="t('Any eye finding')"
                @update:model-value="setFinding"
            />
            <NativeSelect
                class="w-52 max-md:w-[calc(50%-0.25rem)]"
                :model-value="state.doctor || null"
                :options="doctorSelectOptions"
                :placeholder="t('Any referring doctor')"
                dir="auto"
                @update:model-value="setFilter('doctor', $event as string)"
            />
            <Button
                :variant="state.review ? 'default' : 'outline'"
                size="sm"
                class="h-9"
                @click="setFilter('review', !state.review)"
            >
                <ClipboardCheck />
                {{ t('Needs review') }}
            </Button>
            <Button
                v-if="unverifiedCount > 0 || state.unverified"
                :variant="state.unverified ? 'default' : 'outline'"
                size="sm"
                class="h-9"
                @click="setFilter('unverified', !state.unverified)"
            >
                <CircleHelp />
                {{ t('Unverified (:count)', { count: unverifiedCount }) }}
            </Button>
            <Button v-if="hasFilters" variant="ghost" size="sm" @click="reset">
                <X />
                {{ t('Clear') }}
            </Button>
            <Popover>
                <PopoverTrigger as-child>
                    <Button variant="outline" size="sm" class="ms-auto h-9">
                        <Columns3 />
                        {{ t('Columns') }}
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="end" class="w-56 p-2">
                    <p
                        class="px-2 pt-1 pb-2 text-xs font-medium text-muted-foreground"
                    >
                        {{ t('Columns shown') }}
                    </p>
                    <label
                        v-for="column in optionalColumns"
                        :key="column.key"
                        class="flex cursor-pointer items-center gap-2.5 rounded-sm px-2 py-1.5 text-sm hover:bg-muted"
                    >
                        <Checkbox
                            :model-value="!hiddenColumns.includes(column.key)"
                            @update:model-value="
                                toggleColumn(column.key, $event)
                            "
                        />
                        {{ column.label }}
                    </label>
                </PopoverContent>
            </Popover>
        </div>

        <div
            v-if="selected.length && !state.trashed"
            class="no-print flex flex-wrap items-center gap-2 border-b bg-muted/40 px-5 py-2 text-sm"
        >
            <span class="font-medium">
                {{ t(':count selected', { count: selected.length }) }}
            </span>
            <Button variant="outline" size="sm" @click="openDoctorDialog">
                <Stethoscope />
                {{ t('Set referring doctor') }}
            </Button>
            <Button variant="ghost" size="sm" @click="selected = []">
                <X />
                {{ t('Clear selection') }}
            </Button>
        </div>

        <Pagination :paginator="patients" top class="no-print" />

        <DataTable
            :columns="columns"
            :rows="patients.data"
            :sort="state.sort"
            :direction="state.direction"
            v-model:selected="selected"
            :row-class="rowClass"
            :selectable="!state.trashed"
            clickable
            @sort="sortBy"
            @row-click="router.visit(patientRoutes.show.url($event.id))"
        >
            <template #cell-file_number="{ row }">
                {{ row.file_number ?? '—' }}
            </template>
            <template #cell-name="{ row }">
                <Link
                    :href="patientRoutes.show(row.id)"
                    class="font-medium hover:underline"
                    dir="auto"
                >
                    {{ row.name }}
                </Link>
                <div
                    class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                >
                    <span>{{ enumLabel('sex', row.sex) }}</span>
                    <span
                        v-if="row.multiplicity && row.multiplicity !== 'single'"
                    >
                        {{ enumLabel('multiplicity', row.multiplicity) }}
                    </span>
                    <span v-if="row.phone" dir="ltr">{{ row.phone }}</span>
                    <Link
                        v-if="row.open_review_items_count"
                        :href="
                            patientRoutes.show(row.id, {
                                query: { tab: 'review' },
                            })
                        "
                        class="inline-flex items-center gap-1 text-warning hover:underline"
                    >
                        <ClipboardCheck class="size-3" />
                        {{
                            t(':count to review', {
                                count: row.open_review_items_count,
                            })
                        }}
                    </Link>
                </div>
            </template>
            <template #cell-dob="{ row }">{{ formatDate(row.dob) }}</template>
            <template #cell-ga_weeks="{ row }">
                {{ formatGestationalAge(row.ga_weeks, row.ga_days) }}
            </template>
            <template #cell-birth_weight_g="{ row }">
                {{ formatNumber(row.birth_weight_g) }}
            </template>
            <template #cell-referring_doctor="{ row }">
                <bdi v-if="row.referring_doctor">
                    {{ row.referring_doctor }}
                </bdi>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-referral_date="{ row }">
                {{ formatDate(row.referral_date) }}
            </template>
            <template #cell-rop="{ row }">
                <PatientFlags :patient="row" show-rop />
            </template>
            <template #cell-last_visit_date="{ row }">
                {{ formatDate(row.last_visit_date) }}
            </template>
            <template #cell-next_appointment_date="{ row }">
                <template v-if="row.next_appointment_date">
                    <p class="tabular-nums">
                        {{ formatDate(row.next_appointment_date) }}
                    </p>
                    <p
                        class="text-xs"
                        :class="
                            (row.days_until_appointment ?? 0) < 0
                                ? 'text-warning'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ formatRelativeDays(row.days_until_appointment) }}
                    </p>
                </template>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-status="{ row }">
                <Pill :tone="statusTones[row.status] ?? 'neutral'">
                    {{ enumLabel('patient_status', row.status) }}
                </Pill>
            </template>
            <template #cell-actions="{ row }">
                <Button
                    v-if="row.deleted_at"
                    variant="outline"
                    size="sm"
                    class="no-print"
                    @click="restore(row)"
                >
                    <RotateCcw />
                    {{ t('Restore') }}
                </Button>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Users"
                    :title="
                        hasFilters
                            ? t('No patients match these filters')
                            : state.trashed
                              ? t('The recycle bin is empty')
                              : t('No patients yet')
                    "
                    :description="
                        hasFilters || state.trashed
                            ? undefined
                            : t(
                                  'Register the first patient to start the registry.',
                              )
                    "
                />
            </template>
        </DataTable>

        <div class="no-print flex items-center justify-between">
            <Pagination :paginator="patients" class="flex-1" />
            <div class="flex items-center gap-2 border-t px-4 py-3 text-sm">
                <span class="text-muted-foreground">{{ t('Rows') }}</span>
                <NativeSelect
                    class="w-20 max-md:w-[calc(50%-0.25rem)]"
                    size="sm"
                    :nullable="false"
                    :model-value="state.per_page"
                    :options="
                        [10, 25, 50, 100].map((value) => ({
                            value,
                            label: String(value),
                        }))
                    "
                    @update:model-value="setFilter('per_page', Number($event))"
                />
            </div>
        </div>
    </section>

    <Dialog v-model:open="doctorDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('Set referring doctor') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'For the :count selected patients. Leave it empty to clear the referring doctor.',
                            { count: selected.length },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <form id="bulk-doctor" @submit.prevent="applyDoctor">
                <SuggestInput
                    v-model="bulkDoctor"
                    v-focus
                    :options="doctorOptions"
                    :placeholder="t('Referring doctor')"
                    dir="auto"
                    data-keyboard="ar"
                />
            </form>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="doctorDialog = false">
                    {{ t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    form="bulk-doctor"
                    :disabled="bulkProcessing"
                >
                    {{ t('Apply') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
