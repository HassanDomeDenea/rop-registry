<script setup lang="ts">
import { computed } from 'vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import { useI18n } from '@/composables/useI18n';
import type { EnumName, Eye, Visit } from '@/types';

/** A read-only, one-glance summary of the findings of one eye at a visit. */
const props = defineProps<{
    visit: Visit;
    eye: Eye;
    english?: boolean;
    hideLabel?: boolean;
}>();

const { t, enumLabel: translatedLabel, englishLabel } = useI18n();

const enumLabel = (name: EnumName, value: string) =>
    props.english ? englishLabel(name, value) : translatedLabel(name, value);

const value = (field: string) =>
    (props.visit as unknown as Record<string, string | boolean | null>)[
        `${props.eye}_${field}`
    ];

const statusTone = computed<PillTone>(() => {
    const status = value('rop_status');

    if (status === 'present') {
        return 'warning';
    }

    if (status === 'regressing' || status === 'regressed') {
        return 'info';
    }

    return status === null || status === 'not_assessable'
        ? 'neutral'
        : 'success';
});

const plusTone = computed<PillTone>(() => {
    const plus = value('plus');

    return plus === 'plus'
        ? 'danger'
        : plus === 'pre_plus'
          ? 'warning'
          : 'neutral';
});

const type = computed(
    () => value('rop_type') ?? props.visit[`${props.eye}_suggested_type`],
);

const details = computed(() =>
    [
        value('dilatation')
            ? `${t('Dilatation')}: ${value('dilatation')}`
            : null,
        value('lens') ? `${t('Lens')}: ${value('lens')}` : null,
        value('notes'),
    ].filter(Boolean),
);

const isEmpty = computed(
    () =>
        !value('rop_status') &&
        !value('zone') &&
        !value('stage') &&
        !value('plus') &&
        value('a_rop') === null &&
        !type.value &&
        details.value.length === 0,
);
</script>

<template>
    <div class="min-w-0">
        <p
            v-if="!hideLabel"
            class="mb-1.5 text-xs font-medium text-muted-foreground"
        >
            {{ eye === 'right' ? t('Right eye') : t('Left eye') }}
        </p>
        <p v-if="isEmpty" class="text-sm text-muted-foreground">
            {{ t('Not recorded') }}
        </p>
        <div v-else class="flex flex-wrap items-center gap-1">
            <Pill v-if="value('rop_status')" :tone="statusTone">
                {{ enumLabel('rop_status', value('rop_status') as string) }}
            </Pill>
            <Pill v-if="value('zone')">
                {{ enumLabel('zone', value('zone') as string) }}
            </Pill>
            <Pill v-if="value('stage')">
                {{ enumLabel('stage', value('stage') as string) }}
            </Pill>
            <Pill v-if="value('plus')" :tone="plusTone">
                {{ enumLabel('plus_disease', value('plus') as string) }}
            </Pill>
            <Pill v-if="value('a_rop') === true" tone="danger">A-ROP</Pill>
            <Pill v-if="type" :tone="type === 'type_1' ? 'danger' : 'warning'">
                {{ enumLabel('rop_type', type as string) }}
            </Pill>
        </div>
        <p
            v-for="detail in details"
            :key="String(detail)"
            class="mt-1 text-xs text-muted-foreground"
            dir="auto"
        >
            {{ detail }}
        </p>
    </div>
</template>
