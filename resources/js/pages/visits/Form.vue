<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Images, PanelRight } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import PatientPanel from '@/components/registry/PatientPanel.vue';
import UnsavedChanges from '@/components/registry/UnsavedChanges.vue';
import VisitFields from '@/components/registry/VisitFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { usePatientPanel } from '@/composables/usePatientPanel';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useVisitForm } from '@/composables/useVisitForm';
import { daysBetween } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import type { Attachment, Patient, Treatment, Visit } from '@/types';

const props = defineProps<{
    patient: Patient;
    visit: Visit | null;
    previous: Visit | null;
    visitNumber: number;
    commonFees: number[];
    visits: Visit[];
    treatments: Treatment[];
    attachments: Attachment[];
}>();

const {
    t,
    formatDate,
    formatWeeks,
    formatAge,
    formatGestationalAge,
    formatNumber,
} = useI18n();

const form = useVisitForm(props.visit, props.previous?.examiner ?? null);

const backHref = computed(() => patientRoutes.show.url(props.patient.id));

/** Age and postmenstrual age on the examination date, calculated while typing. */
const ages = computed(() => {
    const date = form.visit_date as string | null;

    if (!date || !props.patient.dob || date < props.patient.dob) {
        return null;
    }

    const age = daysBetween(props.patient.dob, date);
    const gestational =
        props.patient.ga_weeks === null
            ? null
            : props.patient.ga_weeks * 7 + (props.patient.ga_days ?? 0);

    return { age, pma: gestational === null ? null : gestational + age };
});

const unsaved = useUnsavedChanges(() => form.isDirty);

/** The rest of the record beside the form: details, notes, earlier visits and attachments. */
const { panel, show: showPanel, toggle: togglePanel } = usePatientPanel();

function submit() {
    const options = { onError: unsaved.stopSaving };

    unsaved.startSaving();

    if (props.visit) {
        form.put(
            patientRoutes.visits.update.url({
                patient: props.patient.id,
                visit: props.visit.id,
            }),
            options,
        );
    } else {
        form.post(patientRoutes.visits.store.url(props.patient.id), options);
    }
}
</script>

<template>
    <Head :title="visit ? t('Edit visit') : t('New visit')" />

    <div class="flex items-start gap-6">
        <form class="min-w-0 flex-1 space-y-6" @submit.prevent="submit">
            <PageHeader
                :title="visit ? t('Edit visit') : t('New visit')"
                :back-href="backHref"
            >
                <template #description>
                    <span dir="auto" class="font-medium text-foreground">
                        {{ patient.name }}
                    </span>
                    ·
                    {{
                        visitNumber === 1
                            ? t('Initial examination')
                            : t('Follow-up :number', {
                                  number: visitNumber - 1,
                              })
                    }}
                </template>
                <UnsavedChanges
                    v-model:confirming="unsaved.confirming.value"
                    :dirty="form.isDirty"
                    @leave="unsaved.leave"
                />
                <Button
                    type="button"
                    :variant="panel.open ? 'secondary' : 'outline'"
                    :aria-pressed="panel.open"
                    @click="togglePanel"
                >
                    <PanelRight />
                    {{ t('Patient record') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="showPanel('attachments')"
                >
                    <Images />
                    {{ t('Attachments') }}
                    <span
                        v-if="attachments.length"
                        class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums"
                    >
                        {{ attachments.length }}
                    </span>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="backHref">{{ t('Cancel') }}</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ visit ? t('Save changes') : t('Save visit') }}
                </Button>
            </PageHeader>

            <!-- Patient banner: the header of the paper form -->
            <div
                class="grid grid-cols-6 gap-4 rounded-xl border bg-card px-5 py-4 text-sm shadow-xs"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('File no.') }}
                    </p>
                    <p class="font-medium tabular-nums">
                        {{ patient.file_number ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Date of birth') }}
                    </p>
                    <p class="font-medium tabular-nums">
                        {{ formatDate(patient.dob) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Gestational age') }}
                    </p>
                    <p class="font-medium tabular-nums">
                        {{
                            formatGestationalAge(
                                patient.ga_weeks,
                                patient.ga_days,
                            )
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Birth weight (g)') }}
                    </p>
                    <p class="font-medium tabular-nums">
                        {{ formatNumber(patient.birth_weight_g) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Age at examination') }}
                    </p>
                    <p class="font-medium tabular-nums">
                        {{ formatAge(ages?.age) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('PMA at examination') }}
                    </p>
                    <p class="font-semibold text-primary tabular-nums">
                        {{ formatWeeks(ages?.pma) }}
                    </p>
                </div>
            </div>

            <VisitFields
                :form="form"
                :errors="form.errors"
                :previous="previous"
                :common-fees="commonFees"
            />
        </form>

        <PatientPanel
            v-if="panel.open"
            v-model:tab="panel.tab"
            :patient="patient"
            :visits="visits"
            :treatments="treatments"
            :attachments="attachments"
            :current-visit-id="visit?.id"
            @close="panel.open = false"
        />
    </div>
</template>
