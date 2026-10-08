<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarDays,
    ClipboardList,
    Copy,
    Eye as EyeIcon,
    Lightbulb,
    Stethoscope,
} from '@lucide/vue';
import { computed } from 'vue';
import EyeFields from '@/components/registry/EyeFields.vue';
import EyeSummary from '@/components/registry/EyeSummary.vue';
import FormField from '@/components/registry/FormField.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import UnsavedChanges from '@/components/registry/UnsavedChanges.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { EYE_FIELDS, suggestFollowUp } from '@/lib/rop';
import { addDays, daysBetween, todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import type { Eye, Patient, Visit } from '@/types';

const props = defineProps<{
    patient: Patient;
    visit: Visit | null;
    previous: Visit | null;
    visitNumber: number;
}>();

const {
    t,
    enumOptions,
    enumLabel,
    formatDate,
    formatWeeks,
    formatAge,
    formatGestationalAge,
    formatNumber,
} = useI18n();

type FormData = Record<string, string | number | boolean | null>;

const source = props.visit as unknown as FormData | null;

const eyeDefaults = (eye: Eye): FormData =>
    Object.fromEntries(
        EYE_FIELDS.map((field) => [
            `${eye}_${field}`,
            source?.[`${eye}_${field}`] ?? null,
        ]),
    );

const form = useForm<FormData>({
    kind: props.visit?.kind ?? 'examination',
    visit_date: props.visit ? props.visit.visit_date : todayIso(),
    examiner: props.visit?.examiner ?? props.previous?.examiner ?? null,
    ...eyeDefaults('right'),
    ...eyeDefaults('left'),
    assessment: props.visit?.assessment ?? null,
    management_plan: props.visit?.management_plan ?? null,
    management_notes: props.visit?.management_notes ?? null,
    next_visit_date: props.visit?.next_visit_date ?? null,
    fee: props.visit?.fee ?? null,
    notes: props.visit?.notes ?? null,
});

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

const intervals = [1, 2, 3, 4, 6, 8, 12];

const followUp = computed(() => suggestFollowUp(form.data()));

const followUpLabel = computed(() => {
    const suggestion = followUp.value;

    if (!suggestion) {
        return null;
    }

    if (suggestion.treat) {
        return t(
            'Type 1 ROP: treatment is indicated, ideally within 72 hours.',
        );
    }

    return suggestion.minWeeks === suggestion.maxWeeks
        ? t('Guideline: re-examine within :count week(s)', {
              count: suggestion.maxWeeks,
          })
        : t('Guideline: re-examine in :min–:max weeks', {
              min: suggestion.minWeeks,
              max: suggestion.maxWeeks,
          });
});

function planNextVisit(weeks: number) {
    form.next_visit_date = addDays(
        (form.visit_date as string | null) ?? todayIso(),
        weeks * 7,
    );
}

const nextVisitHint = computed(() => {
    const next = form.next_visit_date as string | null;
    const date = form.visit_date as string | null;

    if (!next || !date) {
        return undefined;
    }

    const days = daysBetween(date, next);

    return days % 7 === 0
        ? t(':count weeks after this visit', { count: days / 7 })
        : t(':count days after this visit', { count: days });
});

function copyEye(from: Eye, to: Eye) {
    for (const field of EYE_FIELDS) {
        form[`${to}_${field}`] = form[`${from}_${field}`];
    }
}

function copyPrevious() {
    const previous = props.previous as unknown as FormData | null;

    if (!previous) {
        return;
    }

    for (const eye of ['right', 'left'] as const) {
        for (const field of EYE_FIELDS) {
            form[`${eye}_${field}`] = previous[`${eye}_${field}`];
        }
    }
}

function markBothNormal() {
    for (const eye of ['right', 'left'] as const) {
        form[`${eye}_rop_status`] = 'no_rop';
        form[`${eye}_plus`] = 'none';
        form[`${eye}_zone`] = null;
        form[`${eye}_stage`] = null;
        form[`${eye}_a_rop`] = null;
        form[`${eye}_rop_type`] = null;
    }

    form.management_plan ??= 'observe';
}

const unsaved = useUnsavedChanges(() => form.isDirty);

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

    <form class="space-y-6" @submit.prevent="submit">
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
                        : t('Follow-up :number', { number: visitNumber - 1 })
                }}
            </template>
            <UnsavedChanges
                v-model:confirming="unsaved.confirming.value"
                :dirty="form.isDirty"
                @leave="unsaved.leave"
            />
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
                <p class="text-xs text-muted-foreground">{{ t('File no.') }}</p>
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
                        formatGestationalAge(patient.ga_weeks, patient.ga_days)
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

        <SectionCard :title="t('Examination')" :icon="Stethoscope">
            <div class="grid grid-cols-4 gap-4">
                <FormField
                    :label="t('Date of examination')"
                    :error="form.errors.visit_date"
                    :hint="t('Leave empty when the date is not documented')"
                >
                    <TextInput
                        v-model="form.visit_date"
                        type="date"
                        :max="todayIso()"
                    />
                </FormField>
                <FormField :label="t('Record type')" :error="form.errors.kind">
                    <NativeSelect
                        v-model="form.kind"
                        :options="enumOptions('visit_kind')"
                        :nullable="false"
                    />
                </FormField>
                <FormField
                    class="col-span-2"
                    :label="t('Examiner')"
                    :error="form.errors.examiner"
                >
                    <TextInput v-model="form.examiner" dir="auto" />
                </FormField>
            </div>
        </SectionCard>

        <!-- Clinical classification is always written in English. -->
        <SectionCard
            title="Fundus examination and staging of ROP"
            :icon="EyeIcon"
            dir="ltr"
            lang="en"
        >
            <template #actions>
                <Button
                    v-if="previous"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="copyPrevious"
                >
                    <Copy />
                    Copy previous findings
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="markBothNormal"
                >
                    Both eyes: no ROP, no plus
                </Button>
            </template>

            <div class="grid grid-cols-[1fr_auto_1fr] gap-6">
                <div>
                    <h3 class="mb-3 text-center text-sm font-semibold">
                        Right eye
                        <span class="font-normal text-muted-foreground"
                            >OD</span
                        >
                    </h3>
                    <EyeFields eye="right" :form="form" :errors="form.errors" />
                </div>

                <div class="flex flex-col items-center gap-2 pt-24">
                    <div class="w-px flex-1 bg-border" />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        title="Copy right eye to left eye"
                        @click="copyEye('right', 'left')"
                    >
                        <ArrowLeftRight />
                    </Button>
                    <div class="w-px flex-1 bg-border" />
                </div>

                <div>
                    <h3 class="mb-3 text-center text-sm font-semibold">
                        Left eye
                        <span class="font-normal text-muted-foreground"
                            >OS</span
                        >
                    </h3>
                    <EyeFields eye="left" :form="form" :errors="form.errors" />
                </div>
            </div>
        </SectionCard>

        <div class="grid grid-cols-3 gap-6">
            <SectionCard
                class="col-span-2"
                :title="t('Assessment and management plan')"
                :icon="ClipboardList"
            >
                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        class="col-span-2"
                        :label="t('Overall assessment')"
                        :error="form.errors.assessment"
                    >
                        <TextArea v-model="form.assessment" :rows="2" />
                    </FormField>
                    <FormField
                        :label="t('Management plan')"
                        :error="form.errors.management_plan"
                        :hint="
                            t(
                                'A plan is a recommendation. Record the injection or laser itself under Treatments once it is performed.',
                            )
                        "
                    >
                        <NativeSelect
                            v-model="form.management_plan"
                            :options="enumOptions('management_plan')"
                            :placeholder="t('Not recorded')"
                        />
                    </FormField>
                    <FormField
                        :label="t('Plan details')"
                        :error="form.errors.management_notes"
                    >
                        <TextArea v-model="form.management_notes" :rows="2" />
                    </FormField>
                    <FormField
                        class="col-span-2"
                        :label="t('Notes')"
                        :error="form.errors.notes"
                    >
                        <TextArea v-model="form.notes" :rows="2" />
                    </FormField>
                </div>
            </SectionCard>

            <div class="space-y-6">
                <SectionCard
                    :title="t('Next appointment')"
                    :icon="CalendarDays"
                >
                    <div class="grid gap-4">
                        <FormField
                            :label="t('Next visit date')"
                            :error="form.errors.next_visit_date"
                            :hint="nextVisitHint"
                        >
                            <TextInput
                                v-model="form.next_visit_date"
                                type="date"
                            />
                        </FormField>
                        <div
                            v-if="followUp && followUpLabel"
                            class="rounded-md border border-dashed px-3 py-2 text-xs"
                            :class="
                                followUp.treat
                                    ? 'border-destructive/40 bg-destructive/5 text-destructive'
                                    : 'border-info/40 bg-info/5 text-info'
                            "
                        >
                            <p class="flex items-start gap-2 font-medium">
                                <Lightbulb class="mt-0.5 size-3.5 shrink-0" />
                                {{ followUpLabel }}
                            </p>
                            <div
                                v-if="!followUp.treat"
                                class="mt-1.5 flex flex-wrap gap-1.5 ps-5"
                            >
                                <button
                                    v-for="weeks in [
                                        ...new Set([
                                            followUp.minWeeks,
                                            followUp.maxWeeks,
                                        ]),
                                    ]"
                                    :key="weeks"
                                    type="button"
                                    class="rounded-md bg-info/15 px-2 py-0.5 font-medium hover:bg-info/25"
                                    @click="planNextVisit(weeks)"
                                >
                                    {{ t('Use :count w', { count: weeks }) }}
                                </button>
                            </div>
                            <p class="mt-1.5 ps-5 text-muted-foreground">
                                {{
                                    t(
                                        'From the AAP/AAO 2018 screening schedule, based on the zone, stage and plus entered. A reminder only — the examiner decides.',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="weeks in intervals"
                                :key="weeks"
                                type="button"
                                class="rounded-md border px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                @click="planNextVisit(weeks)"
                            >
                                {{ t('+:count w', { count: weeks }) }}
                            </button>
                            <button
                                v-if="form.next_visit_date"
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs text-muted-foreground hover:text-foreground"
                                @click="form.next_visit_date = null"
                            >
                                {{ t('Clear') }}
                            </button>
                        </div>
                        <FormField
                            :label="t('Fee (IQD)')"
                            :error="form.errors.fee"
                        >
                            <TextInput
                                v-model="form.fee"
                                type="number"
                                min="0"
                                step="1000"
                            />
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard
                    v-if="previous"
                    :title="t('Previous visit')"
                    :description="formatDate(previous.visit_date)"
                >
                    <div class="space-y-3">
                        <EyeSummary :visit="previous" eye="right" english />
                        <EyeSummary :visit="previous" eye="left" english />
                        <p
                            v-if="previous.management_plan"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('Plan') }}:
                            {{
                                enumLabel(
                                    'management_plan',
                                    previous.management_plan,
                                )
                            }}
                        </p>
                    </div>
                </SectionCard>
            </div>
        </div>
    </form>
</template>
