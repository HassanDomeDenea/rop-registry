<script setup lang="ts">
import {
    ClipboardList,
    ExternalLink,
    FileClock,
    Images,
    NotebookPen,
    Stethoscope,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import AttachmentGallery from '@/components/registry/AttachmentGallery.vue';
import PatientDetails from '@/components/registry/PatientDetails.vue';
import PatientFlags from '@/components/registry/PatientFlags.vue';
import TreatmentCard from '@/components/registry/TreatmentCard.vue';
import VisitCard from '@/components/registry/VisitCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import type { PatientPanelTab } from '@/composables/usePatientPanel';
import { usePatientTimeline } from '@/composables/usePatientTimeline';
import patientRoutes from '@/routes/patients';
import type { Attachment, Patient, Treatment, Visit } from '@/types';

/**
 * The whole record of a patient beside a form: details and notes, the visits and
 * treatments so far, and every attachment.
 */
const props = defineProps<{
    patient: Patient;
    visits: Visit[];
    treatments: Treatment[];
    attachments: Attachment[];
    /** The visit being edited, highlighted in the timeline and the gallery. */
    currentVisitId?: number | null;
}>();

const tab = defineModel<PatientPanelTab>('tab', { required: true });

defineEmits<{ close: [] }>();

const { t, enumLabel, formatAge, formatDate, formatRelativeDays, formatWeeks } =
    useI18n();

const timeline = usePatientTimeline(
    () => props.visits,
    () => props.treatments,
);

/** The latest records first: they matter most while examining the baby again. */
const latestFirst = computed(() => [...timeline.value].reverse());

const tabs = computed(() => [
    {
        value: 'overview' as const,
        label: t('Overview'),
        icon: ClipboardList,
        count: null,
    },
    {
        value: 'timeline' as const,
        label: t('Visits & treatments'),
        icon: Stethoscope,
        count: props.visits.length + props.treatments.length,
    },
    {
        value: 'attachments' as const,
        label: t('Attachments'),
        icon: Images,
        count: props.attachments.length,
    },
]);

function attachmentsOf(visit: Visit) {
    return props.attachments.filter(
        (attachment) => attachment.visit_id === visit.id,
    ).length;
}
</script>

<template>
    <aside
        class="sticky top-6 flex max-h-[calc(100vh-3rem)] w-[26rem] shrink-0 flex-col overflow-hidden rounded-xl border bg-card shadow-xs max-md:fixed max-md:inset-0 max-md:z-50 max-md:max-h-none max-md:w-auto max-md:rounded-none 2xl:w-[32rem]"
        :aria-label="t('Patient record')"
    >
        <header class="space-y-3 border-b px-4 pt-3 pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <a
                        :href="patientRoutes.show.url(patient.id)"
                        target="_blank"
                        class="inline-flex max-w-full items-center gap-1.5 font-semibold hover:underline"
                        :title="t('Open in a new tab')"
                    >
                        <bdi class="truncate">{{ patient.name }}</bdi>
                        <ExternalLink
                            class="size-3.5 shrink-0 text-muted-foreground"
                        />
                    </a>
                    <div
                        class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span v-if="patient.file_number" class="tabular-nums">
                            #{{ patient.file_number }}
                        </span>
                        <span>
                            {{ enumLabel('patient_status', patient.status) }}
                        </span>
                        <PatientFlags :patient="patient" show-rop />
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="t('Close')"
                    @click="$emit('close')"
                >
                    <X />
                </Button>
            </div>
            <div
                class="flex gap-1 rounded-lg bg-muted p-1 max-md:overflow-x-auto"
                role="tablist"
            >
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.value"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1 text-xs transition-colors max-md:shrink-0 max-md:whitespace-nowrap"
                    :class="
                        tab === item.value
                            ? 'bg-card font-medium shadow-xs ring-1 ring-border'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="tab = item.value"
                >
                    <component :is="item.icon" class="size-3.5" />
                    {{ item.label }}
                    <span
                        v-if="item.count"
                        class="rounded-full bg-background/60 px-1.5 text-muted-foreground tabular-nums"
                    >
                        {{ item.count }}
                    </span>
                </button>
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div v-if="tab === 'overview'" class="space-y-4 p-4">
                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <div class="rounded-lg border px-3 py-2">
                        <dt class="text-xs text-muted-foreground">
                            {{ t('Age today') }}
                        </dt>
                        <dd class="font-semibold tabular-nums">
                            {{ formatAge(patient.age_days_today) }}
                        </dd>
                    </div>
                    <div class="rounded-lg border px-3 py-2">
                        <dt class="text-xs text-muted-foreground">
                            {{ t('PMA today') }}
                        </dt>
                        <dd class="font-semibold tabular-nums">
                            {{ formatWeeks(patient.pma_days_today) }}
                        </dd>
                    </div>
                    <div class="rounded-lg border px-3 py-2">
                        <dt class="text-xs text-muted-foreground">
                            {{ t('Next appointment') }}
                        </dt>
                        <dd class="font-semibold tabular-nums">
                            {{ formatDate(patient.next_appointment_date) }}
                            <span
                                v-if="patient.next_appointment_date"
                                class="block text-xs font-normal text-muted-foreground"
                            >
                                {{
                                    formatRelativeDays(
                                        patient.days_until_appointment,
                                    )
                                }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <section
                    v-if="patient.notes"
                    class="rounded-lg border border-warning/30 bg-warning/5 px-3 py-2.5"
                >
                    <h3
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <NotebookPen class="size-3.5" />
                        {{ t('Notes') }}
                    </h3>
                    <p class="mt-1 text-sm whitespace-pre-line" dir="auto">
                        {{ patient.notes }}
                    </p>
                </section>

                <section class="overflow-hidden rounded-lg border">
                    <h3
                        class="border-b bg-muted/40 px-5 py-2 text-xs font-medium text-muted-foreground"
                    >
                        {{ t('Patient details') }}
                    </h3>
                    <PatientDetails :patient="patient" />
                </section>

                <section
                    v-if="
                        patient.source_notes &&
                        !patient.source_notes_reviewed_at
                    "
                    class="rounded-lg border px-3 py-2.5"
                >
                    <h3
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <FileClock class="size-3.5" />
                        {{ t('Source records') }}
                    </h3>
                    <p
                        class="mt-1 text-xs whitespace-pre-line text-muted-foreground"
                        dir="auto"
                    >
                        {{ patient.source_notes }}
                    </p>
                </section>
            </div>

            <div v-else-if="tab === 'timeline'" class="space-y-3 p-4">
                <template v-for="entry in latestFirst" :key="entry.key">
                    <VisitCard
                        v-if="entry.kind === 'visit'"
                        :visit="entry.visit"
                        :number="entry.number"
                        :attachments-count="attachmentsOf(entry.visit)"
                        :highlighted="entry.visit.id === currentVisitId"
                        readonly
                    />
                    <TreatmentCard
                        v-else
                        :treatment="entry.treatment"
                        :show-source-reference="
                            !patient.source_notes_reviewed_at
                        "
                    />
                </template>
                <p
                    v-if="latestFirst.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('No visits recorded yet') }}
                </p>
            </div>

            <div v-else class="p-4">
                <AttachmentGallery
                    :patient-id="patient.id"
                    :attachments="attachments"
                    :visits="visits"
                    :focus-visit-id="currentVisitId"
                />
            </div>
        </div>
    </aside>
</template>
