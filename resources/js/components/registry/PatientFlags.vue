<script setup lang="ts">
import { AlertTriangle, CircleHelp, Syringe, Zap } from '@lucide/vue';
import Pill from '@/components/registry/Pill.vue';
import { useI18n } from '@/composables/useI18n';
import type { PatientRow } from '@/types';

defineProps<{ patient: PatientRow; showRop?: boolean }>();

const { t, enumLabel } = useI18n();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <template v-if="showRop">
            <Pill v-if="patient.any_rop === true" tone="warning">
                {{
                    patient.highest_stage
                        ? enumLabel('stage', patient.highest_stage)
                        : t('ROP')
                }}
            </Pill>
            <Pill v-else-if="patient.any_rop === false" tone="success">
                {{ t('No ROP') }}
            </Pill>
            <Pill v-else>{{ t('Not documented') }}</Pill>
        </template>
        <Pill v-if="patient.type_one" tone="danger">{{ t('Type 1') }}</Pill>
        <Pill v-if="patient.had_injection" tone="eylea">
            <Syringe />{{ t('Injection') }}
        </Pill>
        <Pill v-if="patient.had_laser" tone="laser">
            <Zap />{{ t('Laser') }}
        </Pill>
        <Pill v-if="patient.unverified" tone="neutral">
            <CircleHelp />{{ t('Unverified') }}
        </Pill>
        <Pill v-if="patient.treatment_pending" tone="danger">
            <AlertTriangle />{{ t('Treatment pending') }}
        </Pill>
    </div>
</template>
