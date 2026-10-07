<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    CalendarX,
    Eye,
    Syringe,
    UserPlus,
    Users,
} from '@lucide/vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import PatientList from '@/components/registry/PatientList.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import StatCard from '@/components/registry/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { todayIso } from '@/lib/utils';
import patients from '@/routes/patients';
import reminders from '@/routes/reminders';
import type { PatientRow } from '@/types';

defineProps<{
    figures: {
        patients: number;
        active: number;
        with_rop: number;
        treated: number;
        visits_this_month: number;
        new_this_month: number;
        treatments_this_month: number;
    };
    today: PatientRow[];
    upcoming: PatientRow[];
    overdue: PatientRow[];
    treatmentPending: PatientRow[];
    injectionSurveillance: PatientRow[];
    recent: PatientRow[];
}>();

const { t, formatDate, formatRelativeDays, formatNumber } = useI18n();

const todayLabel = formatDate(todayIso());
</script>

<template>
    <Head :title="t('Dashboard')" />

    <PageHeader :title="t('Dashboard')" :description="todayLabel">
        <Button as-child variant="outline">
            <Link :href="reminders.index()">
                <CalendarClock />
                {{ t('All reminders') }}
            </Link>
        </Button>
    </PageHeader>

    <div class="grid grid-cols-4 gap-4">
        <StatCard
            :label="t('Patients')"
            :value="formatNumber(figures.patients)"
            :hint="t(':count in active follow-up', { count: figures.active })"
            :icon="Users"
        />
        <StatCard
            :label="t('Patients with documented ROP')"
            :value="formatNumber(figures.with_rop)"
            :hint="t(':count treated', { count: figures.treated })"
            :icon="Eye"
        />
        <StatCard
            :label="t('Examinations this month')"
            :value="formatNumber(figures.visits_this_month)"
            :hint="
                t(':count treatments this month', {
                    count: figures.treatments_this_month,
                })
            "
            :icon="Activity"
        />
        <StatCard
            :label="t('New patients this month')"
            :value="formatNumber(figures.new_this_month)"
            :icon="UserPlus"
        />
    </div>

    <div class="grid grid-cols-2 gap-6">
        <SectionCard
            :title="t('Appointments today')"
            :description="t(':count patients', { count: today.length })"
            :icon="CalendarCheck"
            flush
        >
            <PatientList
                :patients="today"
                :empty-title="t('No appointments planned for today')"
                :empty-icon="CalendarCheck"
            >
                <template #meta="{ patient }">
                    <span class="text-xs text-muted-foreground">
                        {{ t('Last visit') }}
                        {{ formatDate(patient.last_visit_date) }}
                    </span>
                </template>
            </PatientList>
        </SectionCard>

        <SectionCard
            :title="t('Upcoming appointments')"
            :icon="CalendarClock"
            flush
        >
            <PatientList
                :patients="upcoming"
                :empty-title="t('No upcoming appointments')"
                :empty-icon="CalendarClock"
            >
                <template #meta="{ patient }">
                    <p class="font-medium tabular-nums">
                        {{ formatDate(patient.next_appointment_date) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatRelativeDays(patient.days_until_appointment) }}
                    </p>
                </template>
            </PatientList>
        </SectionCard>

        <SectionCard
            :title="t('Planned visits not yet recorded')"
            :description="
                t('The planned date has passed and no later visit is entered')
            "
            :icon="CalendarX"
            flush
        >
            <PatientList
                :patients="overdue"
                :empty-title="t('Nothing overdue')"
                :empty-icon="CalendarX"
            >
                <template #meta="{ patient }">
                    <p class="font-medium text-warning tabular-nums">
                        {{ formatDate(patient.next_appointment_date) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatRelativeDays(patient.days_until_appointment) }}
                    </p>
                </template>
            </PatientList>
        </SectionCard>

        <SectionCard
            :title="t('Intravitreal injections')"
            :description="
                t('Pending treatments and post-injection surveillance')
            "
            :icon="Syringe"
            flush
        >
            <PatientList
                :patients="[...treatmentPending, ...injectionSurveillance]"
                :empty-title="t('No patients under injection follow-up')"
                :empty-icon="Syringe"
            >
                <template #meta="{ patient }">
                    <template v-if="patient.treatment_pending">
                        <p
                            class="inline-flex items-center gap-1 font-medium text-destructive"
                        >
                            <AlertTriangle class="size-3.5" />
                            {{ t('Not yet performed') }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="font-medium tabular-nums">
                            {{ formatDate(patient.last_injection_date) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                patient.next_appointment_date
                                    ? t('Next: :date', {
                                          date: formatDate(
                                              patient.next_appointment_date,
                                          ),
                                      })
                                    : t('No follow-up planned')
                            }}
                        </p>
                    </template>
                </template>
            </PatientList>
        </SectionCard>
    </div>

    <SectionCard :title="t('Recently added patients')" :icon="Users" flush>
        <template #actions>
            <Button as-child variant="ghost" size="sm">
                <Link :href="patients.index()">{{ t('View all') }}</Link>
            </Button>
        </template>
        <PatientList
            :patients="recent"
            :empty-title="t('No patients yet')"
            :empty-icon="Users"
        >
            <template #meta="{ patient }">
                <span class="text-xs text-muted-foreground">
                    {{
                        t(':count examinations', { count: patient.exams_count })
                    }}
                </span>
            </template>
        </PatientList>
    </SectionCard>
</template>
