<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarClock, Paperclip, Pencil, Printer, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import EyeSummary from '@/components/registry/EyeSummary.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import type { Visit } from '@/types';

const props = defineProps<{
    visit: Visit;
    number: number;
    attachmentsCount: number;
}>();

defineEmits<{ delete: [visit: Visit] }>();

const { t, enumLabel, formatDate, formatWeeks, formatAge, formatNumber } =
    useI18n();

const planTone = computed<PillTone>(() => {
    switch (props.visit.management_plan) {
        case 'eylea':
        case 'eylea_laser':
            return 'eylea';
        case 'laser':
            return 'laser';
        case 'referred':
            return 'warning';
        case 'discharge':
            return 'success';
        default:
            return 'info';
    }
});

const routeArgs = computed(() => ({
    patient: props.visit.patient_id,
    visit: props.visit.id,
}));
</script>

<template>
    <article class="rounded-xl border bg-card shadow-xs">
        <header
            class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3"
        >
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span
                    class="flex size-7 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums"
                >
                    {{ number }}
                </span>
                <div>
                    <p class="text-sm font-semibold">
                        {{
                            visit.visit_date
                                ? formatDate(visit.visit_date)
                                : t('Date not documented')
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            number === 1
                                ? t('Initial examination')
                                : t('Follow-up :number', { number: number - 1 })
                        }}
                        <template v-if="visit.pma_days !== null">
                            · {{ t('PMA') }} {{ formatWeeks(visit.pma_days) }}
                        </template>
                        <template v-if="visit.age_days !== null">
                            · {{ t('Age') }} {{ formatAge(visit.age_days) }}
                        </template>
                    </p>
                </div>
                <Pill v-if="visit.kind !== 'examination'" tone="warning">
                    {{ enumLabel('visit_kind', visit.kind) }}
                </Pill>
            </div>
            <div class="no-print flex items-center gap-1">
                <span
                    v-if="attachmentsCount"
                    class="me-2 inline-flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Paperclip class="size-3.5" />{{ attachmentsCount }}
                </span>
                <Button
                    as-child
                    variant="ghost"
                    size="icon-sm"
                    :title="t('Print')"
                >
                    <a
                        :href="patientRoutes.visits.print.url(routeArgs)"
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
                    <Link :href="patientRoutes.visits.edit(routeArgs)">
                        <Pencil />
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :title="t('Delete')"
                    class="text-muted-foreground hover:text-destructive"
                    @click="$emit('delete', visit)"
                >
                    <Trash2 />
                </Button>
            </div>
        </header>

        <div class="space-y-4 px-5 py-4">
            <div class="grid grid-cols-2 gap-6">
                <EyeSummary :visit="visit" eye="right" />
                <EyeSummary :visit="visit" eye="left" />
            </div>

            <p v-if="visit.assessment" class="text-sm" dir="auto">
                <span class="text-xs font-medium text-muted-foreground">
                    {{ t('Assessment') }}:
                </span>
                {{ visit.assessment }}
            </p>

            <div
                v-if="
                    visit.management_plan || visit.next_visit_date || visit.fee
                "
                class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"
            >
                <Pill v-if="visit.management_plan" :tone="planTone">
                    {{ enumLabel('management_plan', visit.management_plan) }}
                </Pill>
                <span
                    v-if="visit.next_visit_date"
                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                >
                    <CalendarClock class="size-3.5" />
                    {{ t('Next visit') }}:
                    <span class="font-medium text-foreground tabular-nums">
                        {{ formatDate(visit.next_visit_date) }}
                    </span>
                </span>
                <span
                    v-if="visit.fee"
                    class="text-muted-foreground tabular-nums"
                >
                    {{ t('Fee') }}: {{ formatNumber(visit.fee) }}
                </span>
            </div>

            <p
                v-if="visit.management_notes"
                class="text-sm text-muted-foreground"
                dir="auto"
            >
                {{ visit.management_notes }}
            </p>
            <p
                v-if="visit.notes"
                class="rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground"
                dir="auto"
            >
                {{ visit.notes }}
            </p>
            <p v-if="visit.examiner" class="text-xs text-muted-foreground">
                {{ t('Examiner') }}:
                <span dir="auto">{{ visit.examiner }}</span>
            </p>
        </div>
    </article>
</template>
