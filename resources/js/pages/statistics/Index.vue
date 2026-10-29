<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarRange, Printer, X } from '@lucide/vue';
import { computed, reactive } from 'vue';
import BarList from '@/components/charts/BarList.vue';
import FeeSummary from '@/components/charts/FeeSummary.vue';
import type { Fees } from '@/components/charts/FeeSummary.vue';
import MonthlyChart from '@/components/charts/MonthlyChart.vue';
import DateInput from '@/components/registry/DateInput.vue';
import FormField from '@/components/registry/FormField.vue';
import CrosstabTable from '@/components/registry/CrosstabTable.vue';
import type { Crosstab } from '@/components/registry/CrosstabTable.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import StatCard from '@/components/registry/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import statisticsRoutes from '@/routes/statistics';
import type { ChartData } from '@/types';

type Numeric = {
    label: string;
    n: number;
    missing: number;
    mean: number | null;
    median: number | null;
    min: number | null;
    max: number | null;
};

const props = defineProps<{
    filters: { from: string | null; to: string | null; unverified: boolean };
    statistics: {
        cohort: {
            patients: number;
            registry_total: number;
            unverified: number;
            excluded_undated: number;
        };
        kpis: {
            key: string;
            label: string;
            value: number | string;
            hint: string | null;
        }[];
        numeric: Numeric[];
        charts: Record<string, ChartData>;
        crosstabs: Crosstab[];
        doctors: Crosstab;
        fees: Fees;
        monthly: {
            month: string;
            new_patients: number;
            examinations: number;
            treatments: number;
        }[];
    };
}>();

const { t, formatNumber, formatDate } = useI18n();

const range = reactive<{
    from: string | number | boolean | null;
    to: string | number | boolean | null;
    unverified: boolean;
}>({
    from: props.filters.from,
    to: props.filters.to,
    unverified: props.filters.unverified,
});

const sections = computed(() => [
    {
        title: t('Demographics and neonatal course'),
        charts: [
            'sex',
            'gestational_age',
            'birth_weight',
            'multiplicity',
            'delivery_mode',
            'respiratory_support',
            'nicu_stay',
            'status',
        ],
    },
    {
        title: t('Retinopathy findings'),
        charts: [
            'rop',
            'highest_stage',
            'laterality',
            'eye_rop',
            'eye_stage',
            'eye_zone',
            'eye_plus',
        ],
    },
    {
        title: t('Other eye findings'),
        charts: ['finding_patients', 'finding_eyes'],
    },
    {
        title: t('Management and treatment'),
        charts: [
            'treated_patients',
            'plan_vs_performed',
            'treatment_sessions',
            'treated_eyes',
            'management_plans',
            'follow_up',
        ],
    },
]);

function apply() {
    router.get(
        statisticsRoutes.index.url(),
        Object.fromEntries(
            Object.entries(range)
                .filter(([, value]) => value)
                .map(([key, value]) => [key, value === true ? 1 : value]),
        ) as Record<string, string>,
        { preserveState: true, preserveScroll: true },
    );
}

/** The patients of a referring doctor, or those without one. */
function doctorPatients(label: string) {
    return patientRoutes.index.url({
        query: { doctor: label === t('Not recorded') ? 'none' : label },
    });
}

function preset(months: number | null) {
    if (months === null) {
        range.from = `${new Date().getFullYear()}-01-01`;
    } else {
        const start = new Date();
        start.setMonth(start.getMonth() - months);
        range.from = start.toISOString().slice(0, 10);
    }

    range.to = todayIso();
    apply();
}

function clear() {
    range.from = null;
    range.to = null;
    apply();
}

function printPage() {
    window.print();
}

function number(value: number | null) {
    return value === null ? '—' : formatNumber(value);
}
</script>

<template>
    <Head :title="t('Statistics')" />

    <PageHeader :title="t('Statistics')">
        <template #description>
            {{
                filters.from || filters.to
                    ? t('Patients first seen :from – :to', {
                          from: formatDate(filters.from, '…'),
                          to: formatDate(filters.to, '…'),
                      })
                    : t('All patients in the registry')
            }}
            ·
            {{
                t(':count of :total patients', {
                    count: statistics.cohort.patients,
                    total: statistics.cohort.registry_total,
                })
            }}
        </template>
        <Button variant="outline" class="no-print" @click="printPage">
            <Printer />
            {{ t('Print') }}
        </Button>
    </PageHeader>

    <!-- Filters sit in one row above every chart they scope. -->
    <form
        class="no-print flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4 shadow-xs"
        @submit.prevent="apply"
    >
        <CalendarRange
            class="mb-2.5 size-4 text-muted-foreground max-md:hidden"
        />
        <FormField
            :label="t('First seen from')"
            class="w-52 max-md:min-w-0 max-md:flex-1"
        >
            <DateInput v-model="range.from" />
        </FormField>
        <FormField :label="t('To')" class="w-52 max-md:min-w-0 max-md:flex-1">
            <DateInput v-model="range.to" />
        </FormField>
        <Button type="submit">{{ t('Apply') }}</Button>
        <div class="flex flex-wrap items-center gap-1.5">
            <Button type="button" variant="ghost" size="sm" @click="preset(1)">
                {{ t('Last month') }}
            </Button>
            <Button type="button" variant="ghost" size="sm" @click="preset(3)">
                {{ t('Last 3 months') }}
            </Button>
            <Button type="button" variant="ghost" size="sm" @click="preset(6)">
                {{ t('Last 6 months') }}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="preset(null)"
            >
                {{ t('This year') }}
            </Button>
            <Button
                v-if="filters.from || filters.to"
                type="button"
                variant="ghost"
                size="sm"
                @click="clear"
            >
                <X />
                {{ t('All time') }}
            </Button>
        </div>
        <Button
            v-if="statistics.cohort.unverified > 0"
            type="button"
            size="sm"
            class="ms-auto"
            :variant="range.unverified ? 'default' : 'outline'"
            @click="
                range.unverified = !range.unverified;
                apply();
            "
        >
            {{
                t('Include :count unverified', {
                    count: statistics.cohort.unverified,
                })
            }}
        </Button>
        <p
            v-if="statistics.cohort.excluded_undated > 0"
            class="basis-full text-xs text-muted-foreground"
        >
            {{
                t(
                    ':count patients have no examination, referral or birth date and cannot be placed in a date range.',
                    { count: statistics.cohort.excluded_undated },
                )
            }}
        </p>
    </form>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        <StatCard
            v-for="kpi in statistics.kpis"
            :key="kpi.key"
            :label="kpi.label"
            :value="
                typeof kpi.value === 'number'
                    ? formatNumber(kpi.value)
                    : kpi.value
            "
            :hint="kpi.hint"
        />
    </div>

    <MonthlyChart :months="statistics.monthly" />

    <section class="rounded-xl border bg-card shadow-xs">
        <header class="border-b px-5 py-3.5">
            <h2 class="text-sm font-semibold">{{ t('Numeric summaries') }}</h2>
            <p class="text-xs text-muted-foreground">
                {{
                    t(
                        'Calculated from recorded values only; missing values are counted separately',
                    )
                }}
            </p>
        </header>
        <div class="max-md:overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b bg-muted/40 text-xs text-muted-foreground"
                    >
                        <th class="px-5 py-2 text-start font-medium">
                            {{ t('Measure') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ t('Recorded') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ t('Missing') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ t('Mean') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ t('Median') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ t('Minimum') }}
                        </th>
                        <th class="px-5 py-2 text-end font-medium">
                            {{ t('Maximum') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in statistics.numeric"
                        :key="row.label"
                        class="border-b tabular-nums last:border-b-0"
                    >
                        <td class="px-5 py-2.5">{{ row.label }}</td>
                        <td class="px-4 py-2.5 text-end">
                            {{ formatNumber(row.n) }}
                        </td>
                        <td class="px-4 py-2.5 text-end text-muted-foreground">
                            {{ formatNumber(row.missing) }}
                        </td>
                        <td class="px-4 py-2.5 text-end font-medium">
                            {{ number(row.mean) }}
                        </td>
                        <td class="px-4 py-2.5 text-end">
                            {{ number(row.median) }}
                        </td>
                        <td class="px-4 py-2.5 text-end">
                            {{ number(row.min) }}
                        </td>
                        <td class="px-5 py-2.5 text-end">
                            {{ number(row.max) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 md:grid-cols-2">
        <CrosstabTable
            v-for="table in statistics.crosstabs"
            :key="table.title"
            :table="table"
        />
    </div>

    <CrosstabTable :table="statistics.doctors" :row-href="doctorPatients" />

    <section v-for="section in sections" :key="section.title" class="space-y-4">
        <h2 class="text-base font-semibold tracking-tight">
            {{ section.title }}
        </h2>
        <div class="grid gap-4 md:grid-cols-3">
            <BarList
                v-for="key in section.charts.filter(
                    (name) => statistics.charts[name],
                )"
                :key="key"
                :chart="statistics.charts[key]"
            />
        </div>
    </section>

    <FeeSummary :fees="statistics.fees" />

    <p class="text-xs text-muted-foreground">
        {{
            t(
                'Patient counts, eye counts and treatment-session counts are reported separately. A recommended treatment is counted as performed only when a treatment record exists.',
            )
        }}
    </p>
</template>
