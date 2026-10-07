<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Baby, HeartPulse, NotebookPen, Phone, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/registry/FormField.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import SegmentedControl from '@/components/registry/SegmentedControl.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { daysBetween, todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import type { Patient } from '@/types';

const props = defineProps<{ patient: Patient | null }>();

const { t, enumOptions, formatWeeks, formatAge } = useI18n();

const form = useForm<Record<string, string | number | null>>({
    file_number: props.patient?.file_number ?? null,
    name: props.patient?.name ?? '',
    dob: props.patient?.dob ?? null,
    sex: props.patient?.sex ?? 'unknown',
    birth_weight_g: props.patient?.birth_weight_g ?? null,
    ga_weeks: props.patient?.ga_weeks ?? null,
    ga_days: props.patient?.ga_days ?? null,
    multiplicity: props.patient?.multiplicity ?? null,
    delivery_mode: props.patient?.delivery_mode ?? null,
    referral_date: props.patient?.referral_date ?? null,
    referring_doctor: props.patient?.referring_doctor ?? null,
    nicu_days: props.patient?.nicu_days ?? null,
    respiratory_support: props.patient?.respiratory_support ?? null,
    support_days: props.patient?.support_days ?? null,
    o2_days: props.patient?.o2_days ?? null,
    cpap_days: props.patient?.cpap_days ?? null,
    systemic_illness: props.patient?.systemic_illness ?? null,
    phone: props.patient?.phone ?? null,
    phone_alt: props.patient?.phone_alt ?? null,
    address: props.patient?.address ?? null,
    notes: props.patient?.notes ?? null,
    status: props.patient?.status ?? 'active',
});

const backHref = computed(() =>
    props.patient
        ? patientRoutes.show.url(props.patient.id)
        : patientRoutes.index.url(),
);

/** Age and postmenstrual age today, shown while typing as a plausibility check. */
const ageToday = computed(() => {
    const dob = form.dob === null ? null : String(form.dob);

    if (!dob || dob > todayIso()) {
        return null;
    }

    const age = daysBetween(dob, todayIso());
    const gestational =
        form.ga_weeks === null || form.ga_weeks === undefined
            ? null
            : Number(form.ga_weeks) * 7 + Number(form.ga_days ?? 0);

    return { age, pma: gestational === null ? null : gestational + age };
});

function submit() {
    if (props.patient) {
        form.put(patientRoutes.update.url(props.patient.id));
    } else {
        form.post(patientRoutes.store.url());
    }
}
</script>

<template>
    <Head :title="patient ? t('Edit patient') : t('New patient')" />

    <form class="space-y-6" @submit.prevent="submit">
        <PageHeader
            :title="patient ? t('Edit patient') : t('New patient')"
            :description="
                patient
                    ? patient.name
                    : t('Fields left empty are stored as not recorded')
            "
            :back-href="backHref"
        >
            <Button as-child variant="outline">
                <Link :href="backHref">{{ t('Cancel') }}</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ patient ? t('Save changes') : t('Register patient') }}
            </Button>
        </PageHeader>

        <div class="grid grid-cols-3 gap-6">
            <div class="col-span-2 space-y-6">
                <SectionCard :title="t('Identity')" :icon="UserRound">
                    <div class="grid grid-cols-6 gap-4">
                        <FormField
                            class="col-span-4"
                            :label="t('Baby name')"
                            for="name"
                            :error="form.errors.name"
                            required
                        >
                            <TextInput
                                id="name"
                                v-model="form.name"
                                v-focus="!patient"
                                dir="auto"
                                required
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('Clinic file no.')"
                            for="file_number"
                            :error="form.errors.file_number"
                        >
                            <TextInput
                                id="file_number"
                                v-model="form.file_number"
                                dir="ltr"
                            />
                        </FormField>
                        <FormField
                            class="col-span-3"
                            :label="t('Sex')"
                            :error="form.errors.sex"
                        >
                            <SegmentedControl
                                v-model="form.sex"
                                :options="enumOptions('sex')"
                                :nullable="false"
                            />
                        </FormField>
                        <FormField
                            class="col-span-3"
                            :label="t('Date of birth')"
                            for="dob"
                            :error="form.errors.dob"
                            :hint="
                                ageToday
                                    ? t('Age today: :age', {
                                          age: formatAge(ageToday.age),
                                      }) +
                                      (ageToday.pma !== null
                                          ? ` · ${t('PMA')} ${formatWeeks(ageToday.pma)}`
                                          : '')
                                    : undefined
                            "
                        >
                            <TextInput
                                id="dob"
                                v-model="form.dob"
                                type="date"
                                :max="todayIso()"
                            />
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard :title="t('Birth history')" :icon="Baby">
                    <div class="grid grid-cols-6 gap-4">
                        <FormField
                            class="col-span-2"
                            :label="t('Gestational age (weeks)')"
                            for="ga_weeks"
                            :error="form.errors.ga_weeks"
                        >
                            <TextInput
                                id="ga_weeks"
                                v-model="form.ga_weeks"
                                type="number"
                                min="20"
                                max="44"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('+ days')"
                            for="ga_days"
                            :error="form.errors.ga_days"
                        >
                            <TextInput
                                id="ga_days"
                                v-model="form.ga_days"
                                type="number"
                                min="0"
                                max="6"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('Birth weight (g)')"
                            for="birth_weight_g"
                            :error="form.errors.birth_weight_g"
                        >
                            <TextInput
                                id="birth_weight_g"
                                v-model="form.birth_weight_g"
                                type="number"
                                min="200"
                                max="7000"
                                step="10"
                            />
                        </FormField>
                        <FormField
                            class="col-span-3"
                            :label="t('Multiple births')"
                            :error="form.errors.multiplicity"
                        >
                            <SegmentedControl
                                v-model="form.multiplicity"
                                :options="
                                    enumOptions('multiplicity').filter(
                                        (option) => option.value !== 'unknown',
                                    )
                                "
                            />
                        </FormField>
                        <FormField
                            class="col-span-3"
                            :label="t('Delivery mode')"
                            :error="form.errors.delivery_mode"
                        >
                            <NativeSelect
                                v-model="form.delivery_mode"
                                :options="enumOptions('delivery_mode')"
                                :placeholder="t('Not recorded')"
                            />
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard :title="t('Neonatal course')" :icon="HeartPulse">
                    <div class="grid grid-cols-6 gap-4">
                        <FormField
                            class="col-span-2"
                            :label="t('NICU stay (days)')"
                            for="nicu_days"
                            :error="form.errors.nicu_days"
                        >
                            <TextInput
                                id="nicu_days"
                                v-model="form.nicu_days"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('Respiratory support')"
                            :error="form.errors.respiratory_support"
                        >
                            <NativeSelect
                                v-model="form.respiratory_support"
                                :options="enumOptions('respiratory_support')"
                                :placeholder="t('Not recorded')"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('Support duration (days)')"
                            for="support_days"
                            :error="form.errors.support_days"
                        >
                            <TextInput
                                id="support_days"
                                v-model="form.support_days"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('O₂ days')"
                            for="o2_days"
                            :error="form.errors.o2_days"
                        >
                            <TextInput
                                id="o2_days"
                                v-model="form.o2_days"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            class="col-span-2"
                            :label="t('CPAP days')"
                            for="cpap_days"
                            :error="form.errors.cpap_days"
                        >
                            <TextInput
                                id="cpap_days"
                                v-model="form.cpap_days"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            class="col-span-6"
                            :label="t('Associated systemic illness')"
                            :error="form.errors.systemic_illness"
                        >
                            <TextArea
                                v-model="form.systemic_illness"
                                :rows="2"
                            />
                        </FormField>
                    </div>
                </SectionCard>
            </div>

            <div class="space-y-6">
                <SectionCard :title="t('Referral & contact')" :icon="Phone">
                    <div class="grid gap-4">
                        <FormField
                            :label="t('Referral date')"
                            for="referral_date"
                            :error="form.errors.referral_date"
                        >
                            <TextInput
                                id="referral_date"
                                v-model="form.referral_date"
                                type="date"
                            />
                        </FormField>
                        <FormField
                            :label="t('Referring doctor')"
                            for="referring_doctor"
                            :error="form.errors.referring_doctor"
                        >
                            <TextInput
                                id="referring_doctor"
                                v-model="form.referring_doctor"
                                dir="auto"
                            />
                        </FormField>
                        <FormField
                            :label="t('Parent phone')"
                            for="phone"
                            :error="form.errors.phone"
                        >
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                dir="ltr"
                            />
                        </FormField>
                        <FormField
                            :label="t('Second phone')"
                            for="phone_alt"
                            :error="form.errors.phone_alt"
                        >
                            <TextInput
                                id="phone_alt"
                                v-model="form.phone_alt"
                                type="tel"
                                dir="ltr"
                            />
                        </FormField>
                        <FormField
                            :label="t('Address')"
                            for="address"
                            :error="form.errors.address"
                        >
                            <TextInput
                                id="address"
                                v-model="form.address"
                                dir="auto"
                            />
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard :title="t('Follow-up')" :icon="NotebookPen">
                    <div class="grid gap-4">
                        <FormField
                            :label="t('Status')"
                            :error="form.errors.status"
                        >
                            <NativeSelect
                                v-model="form.status"
                                :options="enumOptions('patient_status')"
                                :nullable="false"
                            />
                        </FormField>
                        <FormField
                            :label="t('Notes')"
                            :error="form.errors.notes"
                        >
                            <TextArea v-model="form.notes" :rows="5" />
                        </FormField>
                    </div>
                </SectionCard>
            </div>
        </div>
    </form>
</template>
