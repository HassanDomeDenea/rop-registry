<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Printer, Search, Stethoscope, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import EyeSummary from '@/components/registry/EyeSummary.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pagination from '@/components/registry/Pagination.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { daysBetween, todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import visitRoutes from '@/routes/visits';
import type { Paginated, Visit } from '@/types';

type Row = Visit & {
    pma_days: number | null;
    age_days: number | null;
    patient: {
        id: number;
        name: string;
        file_number: string | null;
        ga_weeks: number | null;
        ga_days: number | null;
        birth_weight_g: number | null;
        unverified: boolean;
    };
};

type Filters = {
    search: string;
    kind: string;
    plan: string;
    finding: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

const props = defineProps<{ visits: Paginated<Row>; filters: Filters }>();

const {
    t,
    enumOptions,
    enumLabel,
    formatDate,
    formatNumber,
    formatWeeks,
    formatAge,
    formatGestationalAge,
    formatRelativeDays,
} = useI18n();

const state = reactive<Filters>({ ...props.filters });

const defaults: Filters = {
    search: '',
    kind: '',
    plan: '',
    finding: '',
    from: '',
    to: '',
    sort: 'visit_date',
    direction: 'desc',
    per_page: 25,
};

const columns = computed<Column[]>(() => [
    { key: 'visit_date', label: t('Date'), sortable: true, class: 'w-40' },
    { key: 'patient', label: t('Patient'), sortable: true },
    { key: 'right', label: t('Right eye') },
    { key: 'left', label: t('Left eye') },
    { key: 'management_plan', label: t('Plan'), sortable: true },
    {
        key: 'next_visit_date',
        label: t('Next appointment'),
        sortable: true,
        class: 'w-36',
    },
    { key: 'actions', label: '', align: 'end' },
]);

const findingOptions = computed(() => [
    { value: 'rop', label: 'ROP present' },
    { value: 'plus', label: 'Plus disease' },
    { value: 'type_1', label: 'Type 1' },
    { value: 'no_rop', label: t('No ROP in both eyes') },
]);

const hasFilters = computed(() =>
    (['search', 'kind', 'plan', 'finding', 'from', 'to'] as const).some(
        (key) => state[key] !== '',
    ),
);

const planTones: Record<string, PillTone> = {
    eylea: 'eylea',
    eylea_laser: 'eylea',
    laser: 'laser',
    discharge: 'success',
    referred: 'info',
};

function reload() {
    router.get(
        visitRoutes.index.url(),
        Object.fromEntries(
            Object.entries(state).filter(
                ([key, value]) => value !== defaults[key as keyof Filters],
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
        kind: '',
        plan: '',
        finding: '',
        from: '',
        to: '',
    });
    reload();
}

function openPatient(row: Row) {
    router.get(patientRoutes.show.url(row.patient.id));
}
</script>

<template>
    <Head :title="t('Visits')" />

    <PageHeader
        :title="t('Visits')"
        :description="
            t(':count visits of all patients', {
                count: formatNumber(visits.total),
            })
        "
    />

    <section class="rounded-xl border bg-card shadow-xs">
        <div class="flex flex-wrap items-center gap-2 border-b p-3">
            <div class="relative min-w-64 flex-1">
                <Search
                    class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="state.search"
                    type="search"
                    :placeholder="
                        t(
                            'Search by patient, file no., examiner or assessment…',
                        )
                    "
                    class="h-9 w-full rounded-md border border-input bg-transparent ps-8 pe-3 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    @input="reloadDebounced"
                />
            </div>
            <NativeSelect
                class="w-44"
                :model-value="state.finding || null"
                :options="findingOptions"
                :placeholder="t('Any finding')"
                @update:model-value="setFilter('finding', $event as string)"
            />
            <NativeSelect
                class="w-48"
                :model-value="state.plan || null"
                :options="enumOptions('management_plan')"
                :placeholder="t('Any plan')"
                @update:model-value="setFilter('plan', $event as string)"
            />
            <NativeSelect
                class="w-44"
                :model-value="state.kind || null"
                :options="enumOptions('visit_kind')"
                :placeholder="t('Any record type')"
                @update:model-value="setFilter('kind', $event as string)"
            />
            <div
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                {{ t('From') }}
                <TextInput
                    class="w-38"
                    type="date"
                    :model-value="state.from || null"
                    @update:model-value="setFilter('from', $event as string)"
                />
                {{ t('To') }}
                <TextInput
                    class="w-38"
                    type="date"
                    :model-value="state.to || null"
                    @update:model-value="setFilter('to', $event as string)"
                />
            </div>
            <Button v-if="hasFilters" variant="ghost" size="sm" @click="reset">
                <X />
                {{ t('Clear') }}
            </Button>
        </div>

        <Pagination :paginator="visits" top />

        <DataTable
            :columns="columns"
            :rows="visits.data"
            :sort="state.sort"
            :direction="state.direction"
            clickable
            @sort="sortBy"
            @row-click="openPatient"
        >
            <template #cell-visit_date="{ row }">
                <p class="font-medium tabular-nums">
                    {{ formatDate(row.visit_date, t('Undated')) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="row.pma_days !== null">
                        {{ t('PMA') }} {{ formatWeeks(row.pma_days) }}
                    </template>
                    <template v-else-if="row.age_days !== null">
                        {{ formatAge(row.age_days) }}
                    </template>
                </p>
                <Pill v-if="row.kind !== 'examination'" class="mt-1">
                    {{ enumLabel('visit_kind', row.kind) }}
                </Pill>
            </template>

            <template #cell-patient="{ row }">
                <Link
                    :href="patientRoutes.show(row.patient.id)"
                    class="font-medium hover:underline"
                >
                    <bdi>{{ row.patient.name }}</bdi>
                </Link>
                <p class="text-xs text-muted-foreground tabular-nums">
                    <template v-if="row.patient.file_number">
                        #{{ row.patient.file_number }} ·
                    </template>
                    {{ t('GA') }}
                    {{
                        formatGestationalAge(
                            row.patient.ga_weeks,
                            row.patient.ga_days,
                        )
                    }}
                    <template v-if="row.patient.birth_weight_g">
                        · {{ formatNumber(row.patient.birth_weight_g) }} g
                    </template>
                    <template v-if="row.patient.unverified">
                        · {{ t('Unverified') }}
                    </template>
                </p>
            </template>

            <template #cell-right="{ row }">
                <EyeSummary :visit="row" eye="right" hide-label />
            </template>
            <template #cell-left="{ row }">
                <EyeSummary :visit="row" eye="left" hide-label />
            </template>

            <template #cell-management_plan="{ row }">
                <Pill
                    v-if="row.management_plan"
                    :tone="planTones[row.management_plan] ?? 'neutral'"
                >
                    {{ enumLabel('management_plan', row.management_plan) }}
                </Pill>
                <span v-else class="text-muted-foreground">—</span>
                <p
                    v-if="row.assessment"
                    class="mt-1 line-clamp-2 max-w-64 text-xs text-muted-foreground"
                    dir="auto"
                >
                    {{ row.assessment }}
                </p>
            </template>

            <template #cell-next_visit_date="{ row }">
                <template v-if="row.next_visit_date">
                    <p class="tabular-nums">
                        {{ formatDate(row.next_visit_date) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            formatRelativeDays(
                                daysBetween(todayIso(), row.next_visit_date),
                            )
                        }}
                    </p>
                </template>
                <span v-else class="text-muted-foreground">—</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-0.5">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon-sm"
                        :title="t('Print')"
                    >
                        <a
                            :href="
                                patientRoutes.visits.print.url({
                                    patient: row.patient.id,
                                    visit: row.id,
                                })
                            "
                            target="_blank"
                        >
                            <Printer />
                        </a>
                    </Button>
                    <Button
                        as-child
                        variant="ghost"
                        size="icon-sm"
                        :title="t('Edit')"
                    >
                        <Link
                            :href="
                                patientRoutes.visits.edit({
                                    patient: row.patient.id,
                                    visit: row.id,
                                })
                            "
                        >
                            <Pencil />
                        </Link>
                    </Button>
                </div>
            </template>

            <template #empty>
                <EmptyState
                    :icon="Stethoscope"
                    :title="
                        hasFilters
                            ? t('No visit matches these filters')
                            : t('No visits recorded yet')
                    "
                />
            </template>
        </DataTable>

        <div class="flex items-center justify-between">
            <Pagination :paginator="visits" class="flex-1" />
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
