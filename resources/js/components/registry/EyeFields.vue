<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/registry/FormField.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import SegmentedControl from '@/components/registry/SegmentedControl.vue';
import TextArea from '@/components/registry/TextArea.vue';
import TextInput from '@/components/registry/TextInput.vue';
import ZoneDiagram from '@/components/registry/ZoneDiagram.vue';
import { useI18n } from '@/composables/useI18n';
import { classifyRop } from '@/lib/rop';
import type { Eye } from '@/types';

/**
 * The findings of one eye, in the order of the paper examination form:
 * dilatation, lens, plus disease, zone, stage, A-ROP, type of ROP.
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

const zoneOptions = computed(() =>
    englishOptions('zone').filter(
        (option) => option.value !== 'not_applicable',
    ),
);

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

        <div class="grid grid-cols-2 gap-4">
            <FormField label="Dilatation" :error="errors[key('dilatation')]">
                <TextInput v-model="form[key('dilatation')]" dir="auto" />
            </FormField>
            <FormField label="Lens" :error="errors[key('lens')]">
                <TextInput v-model="form[key('lens')]" dir="auto" />
            </FormField>
        </div>

        <FormField label="Plus disease" :error="errors[key('plus')]">
            <SegmentedControl
                v-model="form[key('plus')]"
                :options="englishOptions('plus_disease')"
            />
        </FormField>

        <div class="grid grid-cols-2 gap-4">
            <FormField label="Zone" :error="errors[key('zone')]">
                <NativeSelect
                    v-model="form[key('zone')]"
                    :options="zoneOptions"
                    placeholder="Not recorded"
                />
            </FormField>
            <FormField label="Stage" :error="errors[key('stage')]">
                <NativeSelect
                    v-model="form[key('stage')]"
                    :options="englishOptions('stage')"
                    placeholder="Not recorded"
                />
            </FormField>
        </div>

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
            <NativeSelect
                v-model="form[key('rop_status')]"
                :options="englishOptions('rop_status')"
                placeholder="Not recorded"
            />
        </FormField>

        <FormField label="Notes for this eye" :error="errors[key('notes')]">
            <TextArea v-model="form[key('notes')]" :rows="2" />
        </FormField>
    </div>
</template>
