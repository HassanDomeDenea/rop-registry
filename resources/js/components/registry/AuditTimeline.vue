<script setup lang="ts">
import { History } from '@lucide/vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import { useI18n } from '@/composables/useI18n';
import type { Audit, EnumName } from '@/types';

/** The recorded changes of one or more records, newest first. */
defineProps<{ audits: Audit[]; showPatient?: boolean }>();

const { t, enumLabel, formatDateTime } = useI18n();

const eventTones: Record<Audit['event'], PillTone> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'warning',
};

const eventLabels: Record<Audit['event'], string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    restored: 'Restored',
};

const typeLabels: Record<string, string> = {
    patient: 'Patient',
    visit: 'Visit',
    treatment: 'Treatment',
    attachment: 'Attachment',
};

/** Attributes whose stored value is an enum, mapped to the enum that labels them. */
const enumFields: Record<string, EnumName> = {
    sex: 'sex',
    multiplicity: 'multiplicity',
    delivery_mode: 'delivery_mode',
    respiratory_support: 'respiratory_support',
    status: 'patient_status',
    kind: 'visit_kind',
    management_plan: 'management_plan',
    type: 'treatment_type',
    eye: 'eye_side',
    right_zone: 'zone',
    left_zone: 'zone',
    right_stage: 'stage',
    left_stage: 'stage',
    right_plus: 'plus_disease',
    left_plus: 'plus_disease',
    right_rop_status: 'rop_status',
    left_rop_status: 'rop_status',
    right_rop_type: 'rop_type',
    left_rop_type: 'rop_type',
};

function fieldLabel(field: string) {
    const text = field.replaceAll('_', ' ');

    return t(text.charAt(0).toUpperCase() + text.slice(1));
}

function display(field: string, value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean' || value === 0 || value === 1) {
        if (field.endsWith('a_rop')) {
            return value ? t('Yes') : t('No');
        }
    }

    if (field === 'illnesses') {
        try {
            return (JSON.parse(String(value)) as string[]).join('; ');
        } catch {
            return String(value);
        }
    }

    if (enumFields[field]) {
        return enumLabel(enumFields[field], String(value));
    }

    return String(value).replace(/ 00:00:00$/, '');
}

function changes(audit: Audit) {
    const fields = Object.keys(audit.new_values ?? audit.old_values ?? {});

    return fields
        .map((field) => ({
            field,
            from: audit.old_values?.[field],
            to: audit.new_values?.[field],
        }))
        .filter(
            (change) =>
                audit.event === 'updated' ||
                (change.to ?? change.from ?? null) !== null,
        );
}
</script>

<template>
    <ol v-if="audits.length" class="space-y-3">
        <li
            v-for="audit in audits"
            :key="audit.id"
            class="rounded-lg border bg-card p-4"
        >
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <Pill :tone="eventTones[audit.event]">
                    {{ t(eventLabels[audit.event]) }}
                </Pill>
                <span class="font-medium">
                    {{
                        t(
                            typeLabels[audit.auditable_type] ??
                                audit.auditable_type,
                        )
                    }}
                </span>
                <span
                    v-if="audit.label"
                    class="text-muted-foreground"
                    dir="auto"
                >
                    {{ audit.label }}
                </span>
                <span
                    class="ms-auto text-xs text-muted-foreground tabular-nums"
                >
                    {{ audit.user ?? t('System') }} ·
                    {{ formatDateTime(audit.created_at) }}
                </span>
            </div>

            <dl
                v-if="audit.event !== 'restored' && changes(audit).length"
                class="mt-3 grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-4 gap-y-1 text-xs"
            >
                <template v-for="change in changes(audit)" :key="change.field">
                    <dt class="text-muted-foreground">
                        {{ fieldLabel(change.field) }}
                    </dt>
                    <dd class="min-w-0 break-words" dir="auto">
                        <template v-if="audit.event === 'updated'">
                            <span class="text-muted-foreground line-through">
                                {{ display(change.field, change.from) }}
                            </span>
                            <span class="mx-1.5 text-muted-foreground">→</span>
                            <span class="font-medium">
                                {{ display(change.field, change.to) }}
                            </span>
                        </template>
                        <template v-else>
                            {{
                                display(change.field, change.to ?? change.from)
                            }}
                        </template>
                    </dd>
                </template>
            </dl>
        </li>
    </ol>
    <EmptyState v-else :icon="History" :title="t('No changes recorded yet')" />
</template>
