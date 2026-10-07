<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/registry/FormField.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import SegmentedControl from '@/components/registry/SegmentedControl.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { todayIso } from '@/lib/utils';
import patientRoutes from '@/routes/patients';
import type { Treatment, Visit } from '@/types';

/** Records a treatment that was actually performed, as opposed to a management plan. */
const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    patientId: number;
    treatment: Treatment | null;
    visits: Visit[];
}>();

const { t, enumOptions, formatDate } = useI18n();

const form = useForm<Record<string, string | number | boolean | null>>({
    type: 'eylea',
    eye: 'both',
    performed_date: todayIso(),
    visit_id: null,
    agent: null,
    performed_by: null,
    location: null,
    notes: null,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.type = props.treatment?.type ?? 'eylea';
    form.eye = props.treatment?.eye ?? 'both';
    form.performed_date = props.treatment
        ? props.treatment.performed_date
        : todayIso();
    form.visit_id = props.treatment?.visit_id ?? null;
    form.agent = props.treatment?.agent ?? null;
    form.performed_by = props.treatment?.performed_by ?? null;
    form.location = props.treatment?.location ?? null;
    form.notes = props.treatment?.notes ?? null;
});

const visitOptions = computed(() =>
    props.visits.map((visit, index) => ({
        value: visit.id,
        label: `${index + 1} · ${visit.visit_date ? formatDate(visit.visit_date) : t('Date not documented')}`,
    })),
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    // The select element yields text; the server expects the numeric visit id.
    form.transform((data) => ({
        ...data,
        visit_id: data.visit_id === null ? null : Number(data.visit_id),
    }));

    if (props.treatment) {
        form.put(
            patientRoutes.treatments.update.url({
                patient: props.patientId,
                treatment: props.treatment.id,
            }),
            options,
        );
    } else {
        form.post(patientRoutes.treatments.store.url(props.patientId), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        treatment ? t('Edit treatment') : t('Record treatment')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'Record only treatment that was actually performed. Recommendations belong to the management plan of a visit.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid grid-cols-2 gap-4" @submit.prevent="submit">
                <FormField
                    class="col-span-2"
                    :label="t('Treatment')"
                    :error="form.errors.type"
                >
                    <NativeSelect
                        v-model="form.type"
                        :options="enumOptions('treatment_type')"
                        :nullable="false"
                    />
                </FormField>
                <FormField
                    class="col-span-2"
                    :label="t('Eye')"
                    :error="form.errors.eye"
                >
                    <SegmentedControl
                        v-model="form.eye"
                        :options="enumOptions('eye_side')"
                        :nullable="false"
                    />
                </FormField>
                <FormField
                    :label="t('Date performed')"
                    :error="form.errors.performed_date"
                >
                    <TextInput
                        v-model="form.performed_date"
                        type="date"
                        :max="todayIso()"
                    />
                </FormField>
                <FormField
                    :label="t('Related visit')"
                    :error="form.errors.visit_id"
                >
                    <NativeSelect
                        v-model="form.visit_id"
                        :options="visitOptions"
                        :placeholder="t('None')"
                    />
                </FormField>
                <FormField
                    :label="t('Agent / dose')"
                    :error="form.errors.agent"
                    :hint="t('e.g. aflibercept 0.4 mg')"
                >
                    <TextInput v-model="form.agent" dir="auto" />
                </FormField>
                <FormField
                    :label="t('Performed by')"
                    :error="form.errors.performed_by"
                >
                    <TextInput v-model="form.performed_by" dir="auto" />
                </FormField>
                <FormField
                    class="col-span-2"
                    :label="t('Place')"
                    :error="form.errors.location"
                >
                    <TextInput v-model="form.location" dir="auto" />
                </FormField>
                <FormField
                    class="col-span-2"
                    :label="t('Notes')"
                    :error="form.errors.notes"
                >
                    <TextArea v-model="form.notes" :rows="2" />
                </FormField>

                <DialogFooter class="col-span-2 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t('Save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
