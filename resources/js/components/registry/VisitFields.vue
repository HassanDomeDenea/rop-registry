<script setup lang="ts">
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
import DateInput from '@/components/registry/DateInput.vue';
import EyeFields from '@/components/registry/EyeFields.vue';
import EyeSummary from '@/components/registry/EyeSummary.vue';
import FormField from '@/components/registry/FormField.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import type { VisitFormData } from '@/composables/useVisitForm';
import { EYE_FIELDS, suggestFollowUp } from '@/lib/rop';
import { addDays, daysBetween, todayIso } from '@/lib/utils';
import type { Eye, Visit } from '@/types';

/**
 * The fields of one examination, as on the paper form: the examination itself, the
 * findings of both eyes, the assessment and plan, and the next appointment. Used by
 * the visit form and, for the first examination, by the patient form.
 */
const props = defineProps<{
    form: VisitFormData;
    errors: Record<string, string | undefined>;
    commonFees: number[];
    /** The latest earlier visit, offered for copying its findings. */
    previous?: Visit | null;
    /** Shown under the examination date instead of the default advice. */
    dateHint?: string;
}>();

const { t, enumOptions, enumLabel, formatDate, formatNumber } = useI18n();

/** The form object is a reactive Inertia form owned by the page. */
function setField(field: string, value: string | number | boolean | null) {
    // eslint-disable-next-line vue/no-mutating-props
    props.form[field] = value;
}

const intervals = [1, 2, 3, 4, 6, 8, 12];

const followUp = computed(() => suggestFollowUp(props.form));

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
    setField(
        'next_visit_date',
        addDays(
            (props.form.visit_date as string | null) ?? todayIso(),
            weeks * 7,
        ),
    );
}

const nextVisitHint = computed(() => {
    const next = props.form.next_visit_date as string | null;
    const date = props.form.visit_date as string | null;

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
        setField(`${to}_${field}`, props.form[`${from}_${field}`]);
    }
}

function copyPrevious() {
    const previous = props.previous as unknown as VisitFormData | null;

    if (!previous) {
        return;
    }

    for (const eye of ['right', 'left'] as const) {
        for (const field of EYE_FIELDS) {
            setField(`${eye}_${field}`, previous[`${eye}_${field}`]);
        }
    }
}

function markBothNormal() {
    for (const eye of ['right', 'left'] as const) {
        setField(`${eye}_rop_status`, 'no_rop');
        setField(`${eye}_plus`, 'none');
        setField(`${eye}_zone`, null);
        setField(`${eye}_stage`, null);
        setField(`${eye}_a_rop`, null);
        setField(`${eye}_rop_type`, null);
    }

    if (props.form.management_plan === null) {
        setField('management_plan', 'observe');
    }
}
</script>

<template>
    <div class="space-y-6">
        <SectionCard :title="t('Examination')" :icon="Stethoscope">
            <div
                class="grid grid-cols-[minmax(13rem,1fr)_minmax(0,1fr)_minmax(0,2fr)] gap-4"
            >
                <FormField
                    :label="t('Date of examination')"
                    :error="errors.visit_date"
                    :hint="
                        dateHint ??
                        t('Leave empty when the date is not documented')
                    "
                >
                    <DateInput v-model="form.visit_date" :max="todayIso()" />
                </FormField>
                <FormField :label="t('Record type')" :error="errors.kind">
                    <NativeSelect
                        v-model="form.kind"
                        :options="enumOptions('visit_kind')"
                        :nullable="false"
                    />
                </FormField>
                <FormField :label="t('Examiner')" :error="errors.examiner">
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
                    <EyeFields eye="right" :form="form" :errors="errors" />
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
                    <EyeFields eye="left" :form="form" :errors="errors" />
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
                        :error="errors.assessment"
                    >
                        <TextArea v-model="form.assessment" :rows="2" />
                    </FormField>
                    <FormField
                        :label="t('Management plan')"
                        :error="errors.management_plan"
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
                        :error="errors.management_notes"
                    >
                        <TextArea v-model="form.management_notes" :rows="2" />
                    </FormField>
                    <FormField
                        class="col-span-2"
                        :label="t('Notes')"
                        :error="errors.notes"
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
                            :error="errors.next_visit_date"
                            :hint="nextVisitHint"
                        >
                            <DateInput v-model="form.next_visit_date" />
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
                                @click="setField('next_visit_date', null)"
                            >
                                {{ t('Clear') }}
                            </button>
                        </div>
                        <FormField :label="t('Fee (IQD)')" :error="errors.fee">
                            <TextInput
                                v-model="form.fee"
                                type="number"
                                min="0"
                                step="1000"
                            />
                            <div
                                v-if="commonFees.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <button
                                    v-for="fee in commonFees"
                                    :key="fee"
                                    type="button"
                                    class="rounded-md border px-2.5 py-1 text-xs tabular-nums transition-colors hover:bg-accent hover:text-accent-foreground"
                                    :class="
                                        form.fee === fee
                                            ? 'border-primary/50 bg-primary/10 font-medium text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                    :aria-pressed="form.fee === fee"
                                    @click="setField('fee', fee)"
                                >
                                    {{ formatNumber(fee) }}
                                </button>
                            </div>
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
    </div>
</template>
