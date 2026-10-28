<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import type { PatientMatch } from '@/composables/usePatientLookup';
import patientRoutes from '@/routes/patients';

/**
 * The patients found while typing, under a search field: choosing one opens it here,
 * its new-tab button opens it beside the current page.
 */
const active = defineModel<number>('active', { default: -1 });

defineProps<{ matches: PatientMatch[]; loading: boolean }>();

defineEmits<{ select: [match: PatientMatch] }>();

const { t, formatDate } = useI18n();
</script>

<template>
    <div>
        <p
            v-if="loading && matches.length === 0"
            class="flex items-center gap-2 px-3 py-3 text-muted-foreground"
        >
            <Spinner />
            {{ t('Searching…') }}
        </p>
        <p
            v-else-if="matches.length === 0"
            class="px-3 py-3 text-muted-foreground"
        >
            {{ t('No patient matches') }}
        </p>
        <ul v-else class="max-h-96 overflow-auto p-1" role="listbox">
            <li
                v-for="(match, index) in matches"
                :key="match.id"
                role="option"
                :aria-selected="index === active"
                class="flex cursor-pointer items-center justify-between gap-3 rounded-md py-1.5 ps-2.5 pe-1"
                :class="index === active ? 'bg-accent' : 'hover:bg-muted'"
                @mousemove="active = index"
                @click="$emit('select', match)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">
                        <bdi>{{ match.name }}</bdi>
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ formatDate(match.dob) }}
                        <template v-if="match.phone">
                            · <bdi dir="ltr">{{ match.phone }}</bdi>
                        </template>
                        <template v-if="match.unverified">
                            · {{ t('Unverified') }}
                        </template>
                    </span>
                </span>
                <span class="flex shrink-0 items-center gap-1">
                    <span
                        v-if="match.file_number"
                        class="text-xs text-muted-foreground tabular-nums"
                    >
                        #{{ match.file_number }}
                    </span>
                    <a
                        :href="patientRoutes.show.url(match.id)"
                        target="_blank"
                        rel="noopener"
                        :title="t('Open in a new tab')"
                        :aria-label="t('Open in a new tab')"
                        class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-background hover:text-foreground"
                        @click.stop
                    >
                        <ExternalLink class="size-3.5" />
                    </a>
                </span>
            </li>
        </ul>
    </div>
</template>
