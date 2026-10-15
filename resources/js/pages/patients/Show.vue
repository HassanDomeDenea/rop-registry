<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarClock,
    Camera,
    Check,
    CircleHelp,
    ClipboardCheck,
    FileClock,
    History,
    Images,
    Merge,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Stethoscope,
    Syringe,
    Trash2,
    Undo2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AttachmentGallery from '@/components/registry/AttachmentGallery.vue';
import AuditTimeline from '@/components/registry/AuditTimeline.vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import PatientDetails from '@/components/registry/PatientDetails.vue';
import PatientFlags from '@/components/registry/PatientFlags.vue';
import Pill from '@/components/registry/Pill.vue';
import ReviewPanel from '@/components/registry/ReviewPanel.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TreatmentCard from '@/components/registry/TreatmentCard.vue';
import TreatmentDialog from '@/components/registry/TreatmentDialog.vue';
import VisitCard from '@/components/registry/VisitCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCaptureStatus } from '@/composables/useCaptureStatus';
import { useI18n } from '@/composables/useI18n';
import { usePatientTimeline } from '@/composables/usePatientTimeline';
import captureRoutes from '@/routes/captures';
import patientRoutes from '@/routes/patients';
import type {
    Attachment,
    Audit,
    Patient,
    ReviewItem,
    Treatment,
    Visit,
} from '@/types';

const props = defineProps<{
    patient: Patient;
    visits: Visit[];
    treatments: Treatment[];
    attachments: Attachment[];
    reviewItems: ReviewItem[];
    audits?: Audit[];
}>();

const {
    t,
    enumLabel,
    enumOptions,
    formatDate,
    formatWeeks,
    formatAge,
    formatGestationalAge,
    formatNumber,
    formatRelativeDays,
} = useI18n();

const { target: captureTarget, refresh: refreshCaptures } = useCaptureStatus();

const receiving = computed(
    () => captureTarget.value?.patient_id === props.patient.id,
);

function toggleReceiving() {
    const options = {
        preserveScroll: true,
        onSuccess: () => void refreshCaptures(),
    };

    if (receiving.value) {
        router.delete(captureRoutes.stop.url(), options);
    } else {
        router.post(captureRoutes.receive.url(props.patient.id), {}, options);
    }
}

// Images that arrive for this patient show up in the gallery by themselves.
watch(
    () => (receiving.value ? captureTarget.value?.received : undefined),
    (received, before) => {
        if (
            received !== undefined &&
            before !== undefined &&
            received > before
        ) {
            router.reload({ only: ['attachments'] });
        }
    },
);

type Tab = 'timeline' | 'attachments' | 'review' | 'history';

const initialTab = new URLSearchParams(usePage().url.split('?')[1] ?? '').get(
    'tab',
);
const tab = ref<Tab>(
    (['timeline', 'attachments', 'review', 'history'] as const).find(
        (value) => value === initialTab,
    ) ?? 'timeline',
);

const openReviewCount = computed(
    () => props.reviewItems.filter((item) => !item.resolved_at).length,
);

const tabs = computed(() => [
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
    {
        value: 'review' as const,
        label: t('Review'),
        icon: ClipboardCheck,
        count: openReviewCount.value,
    },
    {
        value: 'history' as const,
        label: t('History'),
        icon: History,
        count: null,
    },
]);

const timeline = usePatientTimeline(
    () => props.visits,
    () => props.treatments,
);

const treatmentDialogOpen = ref(false);
const editingTreatment = ref<Treatment | null>(null);
const deletingVisit = ref<Visit | null>(null);
const deletingTreatment = ref<Treatment | null>(null);
const deletingPatient = ref(false);

function openTreatmentDialog(treatment: Treatment | null = null) {
    editingTreatment.value = treatment;
    treatmentDialogOpen.value = true;
}

function attachmentsOf(visit: Visit) {
    return props.attachments.filter(
        (attachment) => attachment.visit_id === visit.id,
    ).length;
}

function destroyVisit() {
    if (!deletingVisit.value) {
        return;
    }

    router.delete(
        patientRoutes.visits.destroy.url({
            patient: props.patient.id,
            visit: deletingVisit.value.id,
        }),
        { preserveScroll: true, onSuccess: () => (deletingVisit.value = null) },
    );
}

function destroyTreatment() {
    if (!deletingTreatment.value) {
        return;
    }

    router.delete(
        patientRoutes.treatments.destroy.url({
            patient: props.patient.id,
            treatment: deletingTreatment.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => (deletingTreatment.value = null),
        },
    );
}

function destroyPatient() {
    router.delete(patientRoutes.destroy.url(props.patient.id));
}

function confirmIdentity() {
    router.patch(
        patientRoutes.verify.url(props.patient.id),
        {},
        { preserveScroll: true },
    );
}

/** Reviewed source records stay folded away unless asked for on this visit of the page. */
const showReviewedSourceNotes = ref(false);

function markSourceNotesReviewed(reviewed: boolean) {
    router.patch(
        patientRoutes.sourceNotes.url(props.patient.id),
        { reviewed },
        {
            preserveScroll: true,
            onSuccess: () => (showReviewedSourceNotes.value = false),
        },
    );
}

function restorePatient() {
    router.post(patientRoutes.restore.url(props.patient.id));
}

function updateStatus(status: unknown) {
    router.patch(
        patientRoutes.status.url(props.patient.id),
        { status: String(status) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="patient.name" />

    <div
        v-if="patient.deleted_at"
        class="flex items-center justify-between rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning"
    >
        {{ t('This patient is in the recycle bin.') }}
        <Button size="sm" variant="outline" @click="restorePatient">
            <RotateCcw />
            {{ t('Restore') }}
        </Button>
    </div>

    <div
        v-if="patient.unverified"
        class="flex items-center justify-between gap-4 rounded-lg border bg-muted px-4 py-3 text-sm"
    >
        <span class="flex items-start gap-2">
            <CircleHelp class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span>
                <span class="font-medium">
                    {{ t('Identity not confirmed') }}
                </span>
                <span class="block text-xs text-muted-foreground">
                    {{
                        t(
                            'This record comes from the index notebook only and may duplicate another patient. It is left out of the statistics and reminders until confirmed.',
                        )
                    }}
                </span>
            </span>
        </span>
        <span class="flex shrink-0 items-center gap-2">
            <Button as-child size="sm" variant="outline">
                <Link :href="patientRoutes.merge.create.url(patient.id)">
                    <Merge />
                    {{ t('Merge with another record') }}
                </Link>
            </Button>
            <Button size="sm" variant="outline" @click="confirmIdentity">
                <BadgeCheck />
                {{ t('Confirm identity') }}
            </Button>
        </span>
    </div>

    <PageHeader :title="patient.name" :back-href="patientRoutes.index.url()">
        <template #description>
            <span class="flex flex-wrap items-center gap-2">
                <span v-if="patient.file_number" class="tabular-nums">
                    #{{ patient.file_number }}
                </span>
                <span>{{ enumLabel('sex', patient.sex) }}</span>
                <PatientFlags :patient="patient" show-rop />
            </span>
        </template>
        <template v-if="!patient.deleted_at">
            <Button as-child variant="outline">
                <a :href="patientRoutes.print.url(patient.id)" target="_blank">
                    <Printer />
                    {{ t('Print') }}
                </a>
            </Button>
            <Button as-child variant="outline">
                <Link :href="patientRoutes.edit(patient.id)">
                    <Pencil />
                    {{ t('Edit') }}
                </Link>
            </Button>
            <Button
                v-if="$page.props.features.captures"
                :variant="receiving ? 'default' : 'outline'"
                :title="
                    t(
                        'Images that arrive from the camera in the next minutes are attached to this patient',
                    )
                "
                @click="toggleReceiving"
            >
                <Camera />
                {{
                    receiving
                        ? t('Receiving camera images…')
                        : t('Receive camera images')
                }}
            </Button>
            <Button variant="outline" @click="openTreatmentDialog()">
                <Syringe />
                {{ t('Record treatment') }}
            </Button>
            <Button as-child>
                <Link :href="patientRoutes.visits.create(patient.id)">
                    <Plus />
                    {{ t('New visit') }}
                </Link>
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-5 gap-4">
        <div class="rounded-xl border bg-card px-4 py-3 shadow-xs">
            <p class="text-xs text-muted-foreground">{{ t('Age today') }}</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                {{ formatAge(patient.age_days_today) }}
            </p>
        </div>
        <div class="rounded-xl border bg-card px-4 py-3 shadow-xs">
            <p class="text-xs text-muted-foreground">{{ t('PMA today') }}</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                {{ formatWeeks(patient.pma_days_today) }}
            </p>
        </div>
        <div class="rounded-xl border bg-card px-4 py-3 shadow-xs">
            <p class="text-xs text-muted-foreground">
                {{ t('Gestational age') }} · {{ t('Weight (g)') }}
            </p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                {{ formatGestationalAge(patient.ga_weeks, patient.ga_days) }}
                <span class="font-normal text-muted-foreground">·</span>
                {{ formatNumber(patient.birth_weight_g) }}
            </p>
        </div>
        <div class="rounded-xl border bg-card px-4 py-3 shadow-xs">
            <p class="text-xs text-muted-foreground">{{ t('Examinations') }}</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                {{ patient.exams_count }}
                <span
                    v-if="patient.last_visit_date"
                    class="text-xs font-normal text-muted-foreground"
                >
                    · {{ t('Last visit') }}
                    {{ formatDate(patient.last_visit_date) }}
                </span>
            </p>
        </div>
        <div
            class="rounded-xl border px-4 py-3 shadow-xs"
            :class="
                patient.next_appointment_date &&
                (patient.days_until_appointment ?? 0) < 0
                    ? 'border-warning/40 bg-warning/10'
                    : 'bg-card'
            "
        >
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <CalendarClock class="size-3.5" />
                {{ t('Next appointment') }}
            </p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums">
                {{ formatDate(patient.next_appointment_date) }}
                <span
                    v-if="patient.next_appointment_date"
                    class="text-xs font-normal text-muted-foreground"
                >
                    · {{ formatRelativeDays(patient.days_until_appointment) }}
                </span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-3 items-start gap-6">
        <div class="col-span-2 space-y-4">
            <div class="flex gap-1 rounded-lg bg-muted p-1" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.value"
                    class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="
                        tab === item.value
                            ? 'bg-card font-medium shadow-xs ring-1 ring-border'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="tab = item.value"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                    <span
                        v-if="item.count"
                        class="rounded-full bg-muted px-1.5 text-xs tabular-nums"
                        :class="
                            item.value === 'review'
                                ? 'bg-warning/15 text-warning'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ item.count }}
                    </span>
                </button>
            </div>

            <div v-if="tab === 'timeline'" class="space-y-4">
                <template v-for="entry in timeline" :key="entry.key">
                    <VisitCard
                        v-if="entry.kind === 'visit'"
                        :visit="entry.visit"
                        :number="entry.number"
                        :attachments-count="attachmentsOf(entry.visit)"
                        @delete="deletingVisit = $event"
                    />
                    <TreatmentCard v-else :treatment="entry.treatment">
                        <template #actions>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :title="t('Edit')"
                                @click="openTreatmentDialog(entry.treatment)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-destructive"
                                :title="t('Delete')"
                                @click="deletingTreatment = entry.treatment"
                            >
                                <Trash2 />
                            </Button>
                        </template>
                    </TreatmentCard>
                </template>

                <div
                    v-if="timeline.length === 0"
                    class="rounded-xl border bg-card"
                >
                    <EmptyState
                        :icon="Stethoscope"
                        :title="t('No visits recorded yet')"
                        :description="
                            t(
                                'Record the initial examination to start the follow-up.',
                            )
                        "
                    >
                        <Button as-child>
                            <Link
                                :href="patientRoutes.visits.create(patient.id)"
                            >
                                <Plus />
                                {{ t('New visit') }}
                            </Link>
                        </Button>
                    </EmptyState>
                </div>
            </div>

            <AttachmentGallery
                v-else-if="tab === 'attachments'"
                :patient-id="patient.id"
                :attachments="attachments"
                :visits="visits"
            />

            <ReviewPanel
                v-else-if="tab === 'review'"
                :patient-id="patient.id"
                :items="reviewItems"
            />

            <Deferred v-else data="audits">
                <template #fallback>
                    <div class="space-y-3">
                        <Skeleton
                            v-for="n in 3"
                            :key="n"
                            class="h-20 rounded-lg"
                        />
                    </div>
                </template>
                <AuditTimeline :audits="audits ?? []" />
            </Deferred>
        </div>

        <div class="space-y-6">
            <SectionCard :title="t('Follow-up status')">
                <NativeSelect
                    :model-value="patient.status"
                    :options="enumOptions('patient_status')"
                    :nullable="false"
                    @update:model-value="updateStatus"
                />
                <p
                    v-if="patient.had_injection"
                    class="mt-3 flex items-start gap-2 text-xs text-muted-foreground"
                >
                    <Syringe class="mt-0.5 size-3.5 shrink-0 text-eylea" />
                    <span>
                        {{
                            t('Last injection :date (:relative)', {
                                date: formatDate(patient.last_injection_date),
                                relative: formatRelativeDays(
                                    patient.days_since_injection === null
                                        ? null
                                        : -patient.days_since_injection,
                                    '—',
                                ),
                            })
                        }}
                    </span>
                </p>
            </SectionCard>

            <SectionCard :title="t('Patient details')" flush>
                <PatientDetails :patient="patient" />
            </SectionCard>

            <SectionCard v-if="patient.notes" :title="t('Notes')">
                <p class="text-sm whitespace-pre-line" dir="auto">
                    {{ patient.notes }}
                </p>
            </SectionCard>

            <Button
                v-if="
                    patient.source_notes &&
                    patient.source_notes_reviewed_at &&
                    !showReviewedSourceNotes
                "
                variant="ghost"
                class="w-full text-muted-foreground"
                @click="showReviewedSourceNotes = true"
            >
                <FileClock />
                {{ t('Show the reviewed source records') }}
            </Button>

            <SectionCard
                v-else-if="patient.source_notes"
                :title="t('Source records')"
                :description="
                    patient.source_notes_reviewed_at
                        ? t('Reviewed :date', {
                              date: formatDate(
                                  patient.source_notes_reviewed_at,
                              ),
                          })
                        : undefined
                "
                :icon="FileClock"
            >
                <template #actions>
                    <Button
                        v-if="patient.source_notes_reviewed_at"
                        variant="ghost"
                        size="sm"
                        :title="t('Keep the source records in view')"
                        @click="markSourceNotesReviewed(false)"
                    >
                        <Undo2 />
                        {{ t('Not reviewed') }}
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        size="sm"
                        :title="
                            t(
                                'Hide the source records of this patient. They can be shown again at any time.',
                            )
                        "
                        @click="markSourceNotesReviewed(true)"
                    >
                        <Check />
                        {{ t('Done reviewing') }}
                    </Button>
                </template>
                <p
                    class="text-xs whitespace-pre-line text-muted-foreground"
                    dir="auto"
                >
                    {{ patient.source_notes }}
                </p>
            </SectionCard>

            <div v-if="!patient.deleted_at" class="space-y-1">
                <Button
                    as-child
                    variant="ghost"
                    class="w-full text-muted-foreground"
                >
                    <Link :href="patientRoutes.merge.create.url(patient.id)">
                        <Merge />
                        {{ t('Merge with another record') }}
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    class="w-full text-muted-foreground hover:text-destructive"
                    @click="deletingPatient = true"
                >
                    <Trash2 />
                    {{ t('Move patient to the recycle bin') }}
                </Button>
            </div>
        </div>
    </div>

    <TreatmentDialog
        v-model:open="treatmentDialogOpen"
        :patient-id="patient.id"
        :treatment="editingTreatment"
        :visits="visits"
    />

    <ConfirmDialog
        :open="deletingVisit !== null"
        :title="t('Delete this visit?')"
        :description="
            t('The deletion is recorded in the history of the patient.')
        "
        :confirm-label="t('Delete')"
        @update:open="deletingVisit = null"
        @confirm="destroyVisit"
    />
    <ConfirmDialog
        :open="deletingTreatment !== null"
        :title="t('Delete this treatment?')"
        :confirm-label="t('Delete')"
        @update:open="deletingTreatment = null"
        @confirm="destroyTreatment"
    />
    <ConfirmDialog
        v-model:open="deletingPatient"
        :title="t('Move this patient to the recycle bin?')"
        :description="
            t(
                'The patient and all visits can be restored from the recycle bin at any time.',
            )
        "
        :confirm-label="t('Move to recycle bin')"
        @confirm="destroyPatient"
    />
</template>
