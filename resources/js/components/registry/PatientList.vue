<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import PatientFlags from '@/components/registry/PatientFlags.vue';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { PatientRow } from '@/types';

/**
 * A compact list of patients for the dashboard and reminder panels. The trailing
 * `meta` slot shows what makes each patient relevant to the panel.
 */
defineProps<{
    patients: PatientRow[];
    emptyTitle: string;
    emptyIcon?: LucideIcon;
}>();

const { t, formatGestationalAge } = useI18n();
</script>

<template>
    <ul v-if="patients.length" class="divide-y">
        <li v-for="patient in patients" :key="patient.id">
            <Link
                :href="patientRoutes.show(patient.id)"
                class="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-muted/50 max-md:flex-wrap max-md:gap-y-2 max-md:px-4"
            >
                <div class="min-w-0 max-md:min-w-40 max-md:flex-1">
                    <p class="truncate text-sm font-medium">
                        <bdi>{{ patient.name }}</bdi>
                    </p>
                    <div
                        class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span v-if="patient.file_number" class="tabular-nums">
                            #{{ patient.file_number }}
                        </span>
                        <span v-if="patient.ga_weeks !== null">
                            {{ t('GA') }}
                            {{
                                formatGestationalAge(
                                    patient.ga_weeks,
                                    patient.ga_days,
                                )
                            }}
                        </span>
                        <span v-if="patient.phone" dir="ltr">
                            {{ patient.phone }}
                        </span>
                        <PatientFlags :patient="patient" />
                    </div>
                </div>
                <div class="shrink-0 text-end text-sm max-md:ms-auto">
                    <slot name="meta" :patient="patient" />
                </div>
            </Link>
        </li>
    </ul>
    <EmptyState v-else :title="emptyTitle" :icon="emptyIcon" />
</template>
