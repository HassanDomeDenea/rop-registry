<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    CalendarX,
    Syringe,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import PatientList from '@/components/registry/PatientList.vue';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { PatientRow } from '@/types';

const props = defineProps<{
    today: PatientRow[];
    upcoming: PatientRow[];
    overdue: PatientRow[];
    treatmentPending: PatientRow[];
    injectionSurveillance: PatientRow[];
    withoutAppointment: PatientRow[];
    settings: { upcoming_days: number; injection_surveillance_weeks: number };
}>();

const { t, enumOptions, formatDate, formatRelativeDays } = useI18n();

type Key =
    | 'today'
    | 'upcoming'
    | 'overdue'
    | 'treatmentPending'
    | 'injectionSurveillance'
    | 'withoutAppointment';

type Group = {
    key: Key;
    label: string;
    description: string;
    icon: LucideIcon;
    tone: string;
    empty: string;
};

const groups = computed<Group[]>(() => [
    {
        key: 'today',
        label: t('Today'),
        description: t('Appointments planned for today'),
        icon: CalendarCheck,
        tone: 'text-primary',
        empty: t('No appointments planned for today'),
    },
    {
        key: 'upcoming',
        label: t('Upcoming'),
        description: t('Appointments within the next :count days', {
            count: props.settings.upcoming_days,
        }),
        icon: CalendarClock,
        tone: 'text-info',
        empty: t('No upcoming appointments'),
    },
    {
        key: 'overdue',
        label: t('Not yet recorded'),
        description: t(
            'The planned date has passed and no later visit is entered. The visit may have taken place without being recorded.',
        ),
        icon: CalendarX,
        tone: 'text-warning',
        empty: t('Nothing overdue'),
    },
    {
        key: 'treatmentPending',
        label: t('Treatment pending'),
        description: t(
            'The latest plan recommends an injection or laser that is not recorded as performed',
        ),
        icon: AlertTriangle,
        tone: 'text-destructive',
        empty: t('No pending treatments'),
    },
    {
        key: 'injectionSurveillance',
        label: t('After injection'),
        description: t(
            'Injected within the last :count weeks. ROP can reactivate late after anti-VEGF treatment, so follow-up continues until the retina is fully vascularised.',
            { count: props.settings.injection_surveillance_weeks },
        ),
        icon: Syringe,
        tone: 'text-eylea',
        empty: t('No patients under injection follow-up'),
    },
    {
        key: 'withoutAppointment',
        label: t('No appointment'),
        description: t(
            'Examined patients in active follow-up without a planned return date',
        ),
        icon: CalendarOff,
        tone: 'text-muted-foreground',
        empty: t('Every active patient has a planned appointment'),
    },
]);

const active = ref<Key>(
    (['today', 'overdue', 'treatmentPending', 'upcoming'] as const).find(
        (key) => props[key].length > 0,
    ) ?? 'today',
);

const activeGroup = computed(() =>
    groups.value.find((group) => group.key === active.value)!,
);

function updateStatus(patient: PatientRow, status: unknown) {
    router.patch(
        patientRoutes.status.url(patient.id),
        { status: String(status) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('Reminders')" />

    <PageHeader
        :title="t('Reminders')"
        :description="
            t(
                'Derived from planned return dates, management plans and recorded treatments',
            )
        "
    />

    <div class="grid grid-cols-6 gap-3">
        <button
            v-for="group in groups"
            :key="group.key"
            type="button"
            class="rounded-xl border bg-card p-4 text-start shadow-xs transition-colors"
            :class="
                active === group.key
                    ? 'border-primary ring-1 ring-primary'
                    : 'hover:bg-muted/50'
            "
            :aria-pressed="active === group.key"
            @click="active = group.key"
        >
            <component :is="group.icon" class="size-5" :class="group.tone" />
            <p class="mt-3 text-2xl font-semibold tabular-nums">
                {{ props[group.key].length }}
            </p>
            <p class="text-sm text-muted-foreground">{{ group.label }}</p>
        </button>
    </div>

    <section class="rounded-xl border bg-card shadow-xs">
        <header class="border-b px-5 py-3.5">
            <h2 class="text-sm font-semibold">{{ activeGroup.label }}</h2>
            <p class="mt-0.5 max-w-3xl text-xs text-muted-foreground">
                {{ activeGroup.description }}
            </p>
        </header>
        <PatientList
            :patients="props[active]"
            :empty-title="activeGroup.empty"
            :empty-icon="activeGroup.icon"
        >
            <template #meta="{ patient }">
                <div class="flex items-center gap-4">
                    <div>
                        <template v-if="active === 'injectionSurveillance'">
                            <p class="font-medium tabular-nums">
                                {{ t('Injected') }}
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
                        <template
                            v-else-if="
                                active === 'treatmentPending' ||
                                active === 'withoutAppointment'
                            "
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ t('Last visit') }}
                            </p>
                            <p class="font-medium tabular-nums">
                                {{ formatDate(patient.last_visit_date) }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="font-medium tabular-nums">
                                {{ formatDate(patient.next_appointment_date) }}
                            </p>
                            <p
                                class="text-xs"
                                :class="
                                    active === 'overdue'
                                        ? 'text-warning'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    formatRelativeDays(
                                        patient.days_until_appointment,
                                    )
                                }}
                            </p>
                        </template>
                    </div>
                    <!-- Closing a follow-up straight from the list keeps the reminders tidy. -->
                    <NativeSelect
                        class="w-40"
                        size="sm"
                        :nullable="false"
                        :model-value="patient.status"
                        :options="enumOptions('patient_status')"
                        @click.prevent.stop
                        @update:model-value="updateStatus(patient, $event)"
                    />
                </div>
            </template>
        </PatientList>
    </section>
</template>
