<script setup lang="ts">
import { Syringe, Zap } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import type { Treatment } from '@/types';

/** An injection or laser session in the timeline of a patient. */
defineProps<{ treatment: Treatment }>();

const { t, enumLabel, formatDate, formatWeeks } = useI18n();
</script>

<template>
    <article
        class="flex items-start justify-between gap-4 rounded-xl border px-5 py-4 shadow-xs"
        :class="
            treatment.type === 'laser'
                ? 'border-laser/30 bg-laser/[0.07]'
                : 'border-eylea/30 bg-eylea/[0.07]'
        "
    >
        <div class="flex items-start gap-3">
            <span
                class="flex size-7 shrink-0 items-center justify-center rounded-full text-white"
                :class="treatment.type === 'laser' ? 'bg-laser' : 'bg-eylea'"
            >
                <component
                    :is="treatment.type === 'laser' ? Zap : Syringe"
                    class="size-3.5"
                />
            </span>
            <div class="space-y-1">
                <p class="text-sm font-semibold">
                    {{ enumLabel('treatment_type', treatment.type) }}
                    ·
                    {{ enumLabel('eye_side', treatment.eye) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        treatment.performed_date
                            ? formatDate(treatment.performed_date)
                            : t('Date not documented')
                    }}
                    <template v-if="treatment.pma_days !== null">
                        · {{ t('PMA') }}
                        {{ formatWeeks(treatment.pma_days) }}
                    </template>
                    <template v-if="treatment.agent">
                        ·
                        <span dir="auto">{{ treatment.agent }}</span>
                    </template>
                    <template v-if="treatment.performed_by">
                        ·
                        <span dir="auto">{{ treatment.performed_by }}</span>
                    </template>
                    <template v-if="treatment.location">
                        ·
                        <span dir="auto">{{ treatment.location }}</span>
                    </template>
                </p>
                <p v-if="treatment.notes" class="text-sm" dir="auto">
                    {{ treatment.notes }}
                </p>
                <p
                    v-if="treatment.source_reference"
                    class="text-xs text-muted-foreground"
                    dir="auto"
                >
                    {{ treatment.source_reference }}
                </p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-1">
            <slot name="actions" />
        </div>
    </article>
</template>
