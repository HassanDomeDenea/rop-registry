<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/registry/FormField.vue';
import SegmentedControl from '@/components/registry/SegmentedControl.vue';
import TextArea from '@/components/registry/TextArea.vue';
import ZoneDiagram from '@/components/registry/ZoneDiagram.vue';
import { useI18n } from '@/composables/useI18n';
import { classifyRop } from '@/lib/rop';
import type { Eye } from '@/types';

/**
 * The findings of one eye, in the order of the paper examination form:
 * plus disease, zone, stage, A-ROP, type of ROP.
 */
const props = defineProps<{
    eye: Eye;
    form: Record<string, string | number | boolean | null>;
    errors: Record<string, string | undefined>;
}>();

// Zone, stage, plus and type are written in English in clinical practice,
// so this block is not translated.
const { englishOptions, englishLabel } = useI18n();

const key = (field: string) => `${props.eye}_${field}`;

const suggestedType = computed(() =>
    classifyRop(
        props.form[key('zone')],
        props.form[key('stage')],
        props.form[key('plus')],
        props.form[key('a_rop')],
    ),
);

/**
 * Zone and stage as buttons with the short notation of the examination form; the full
 * name shows on hover. "Not applicable" is left out for the zone, as before.
 */
const shortLabels: Record<'zone' | 'stage', Record<string, string>> = {
    zone: {
        zone_1: 'I',
        posterior_zone_2: 'Post. II',
        zone_2: 'II',
        zone_3: 'III',
    },
    stage: {
        stage_0: '0',
        stage_1: '1',
        stage_2: '2',
        stage_3: '3',
        stage_4a: '4A',
        stage_4b: '4B',
        stage_5: '5',
        not_applicable: 'N/A',
    },
};

function shortOptions(name: 'zone' | 'stage') {
    return englishOptions(name)
        .filter(
            (option) => name !== 'zone' || option.value !== 'not_applicable',
        )
        .map((option) => ({
            value: option.value,
            label: shortLabels[name][option.value] ?? option.label,
            title: option.label,
        }));
}

const zoneOptions = computed(() => shortOptions('zone'));
const stageOptions = computed(() => shortOptions('stage'));

const yesNo = computed(() => [
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
]);

const aggressive = computed({
    get: () =>
        props.form[key('a_rop')] === null
            ? null
            : props.form[key('a_rop')]
              ? 'yes'
              : 'no',
    set: (value) => {
        // The form object is a reactive Inertia form owned by the page.
        // eslint-disable-next-line vue/no-mutating-props
        props.form[key('a_rop')] = value === null ? null : value === 'yes';
    },
});
</script>

<template>
    <div class="space-y-4" dir="ltr" lang="en">
        <div class="flex justify-center">
            <ZoneDiagram v-model="form[key('zone')]" :eye="eye" />
        </div>

        <FormField label="Plus disease" :error="errors[key('plus')]">
            <SegmentedControl
                v-model="form[key('plus')]"
                :options="englishOptions('plus_disease')"
            />
        </FormField>

        <FormField label="Zone" :error="errors[key('zone')]">
            <SegmentedControl
                v-model="form[key('zone')]"
                :options="zoneOptions"
            />
        </FormField>

        <FormField label="Stage" :error="errors[key('stage')]">
            <SegmentedControl
                v-model="form[key('stage')]"
                :options="stageOptions"
            />
        </FormField>

        <div class="grid grid-cols-2 gap-4">
            <FormField label="A-ROP (aggressive)" :error="errors[key('a_rop')]">
                <SegmentedControl v-model="aggressive" :options="yesNo" />
            </FormField>
            <FormField label="Type of ROP" :error="errors[key('rop_type')]">
                <SegmentedControl
                    v-model="form[key('rop_type')]"
                    :options="englishOptions('rop_type')"
                />
            </FormField>
        </div>

        <button
            v-if="suggestedType && suggestedType !== form[key('rop_type')]"
            type="button"
            class="flex w-full items-center gap-2 rounded-md border border-dashed border-info/40 bg-info/5 px-3 py-2 text-start text-xs text-info transition-colors hover:bg-info/10"
            @click="form[key('rop_type')] = suggestedType"
        >
            <Sparkles class="size-3.5 shrink-0" />
            ETROP criteria suggest
            {{ englishLabel('rop_type', suggestedType) }} — click to apply
        </button>

        <FormField label="ROP status" :error="errors[key('rop_status')]">
            <SegmentedControl
                v-model="form[key('rop_status')]"
                :options="englishOptions('rop_status')"
            />
        </FormField>

        <FormField label="Notes for this eye" :error="errors[key('notes')]">
            <TextArea v-model="form[key('notes')]" :rows="2" />
        </FormField>
    </div>
</template>
