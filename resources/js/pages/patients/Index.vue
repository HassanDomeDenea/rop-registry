<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleHelp,
    ClipboardCheck,
    Download,
    FileSpreadsheet,
    FileText,
    Printer,
    RotateCcw,
    Search,
    Trash2,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pagination from '@/components/registry/Pagination.vue';
import PatientFlags from '@/components/registry/PatientFlags.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { Paginated, PatientRow } from '@/types';

type Filters = {
    search: string;
    status: string;
    sex: string;
    rop: string;
    treatment: string;
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
    trashedCount: number;
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

const columns = computed<Column[]>(() => [
    {
        key: 'file_number',
        label: t('File no.'),
        sortable: true,
        class: 'w-20 tabular-nums text-muted-foreground',
    },
    { key: 'name', label: t('Patient'), sortable: true },
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

const hasFilters = computed(
    () =>
        state.search !== '' ||
        state.status !== '' ||
        state.sex !== '' ||
        state.rop !== '' ||
        state.treatment !== '' ||
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
        review: false,
        unverified: false,
        trashed: false,
        sort: 'created_at',
        direction: 'desc',
        per_page: 25,
    };

    return Object.fromEntries(
        Object.entries(state)
            .filter(([key, value]) => value !== defaults[key as keyof Filters])
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
        review: false,
        unverified: false,
    });
    reload();
}

function toggleTrashed() {
    state.trashed = !state.trashed;
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
        <Button
            v-if="trashedCount > 0 || state.trashed"
            variant="ghost"
            @click="toggleTrashed"
        >
            <component :is="state.trashed ? Users : Trash2" />
            {{
                state.trashed
                    ? t('Back to patients')
                    : t('Recycle bin (:count)', { count: trashedCount })
            }}
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
            <div class="relative min-w-64 flex-1">
                <Search
                    class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="state.search"
                    type="search"
                    dir="auto"
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
                class="w-44"
                :model-value="state.status || null"
                :options="enumOptions('patient_status')"
                :placeholder="t('Any status')"
                @update:model-value="setFilter('status', $event as string)"
            />
            <NativeSelect
                class="w-32"
                :model-value="state.sex || null"
                :options="enumOptions('sex')"
                :placeholder="t('Any sex')"
                @update:model-value="setFilter('sex', $event as string)"
            />
            <NativeSelect
                class="w-48"
                :model-value="state.rop || null"
                :options="ropOptions"
                :placeholder="t('Any ROP status')"
                @update:model-value="setFilter('rop', $event as string)"
            />
            <NativeSelect
                class="w-48"
                :model-value="state.treatment || null"
                :options="treatmentOptions"
                :placeholder="t('Any treatment')"
                @update:model-value="setFilter('treatment', $event as string)"
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
        </div>

        <DataTable
            :columns="columns"
            :rows="patients.data"
            :sort="state.sort"
            :direction="state.direction"
            :row-class="rowClass"
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
                    class="w-20"
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
</template>
