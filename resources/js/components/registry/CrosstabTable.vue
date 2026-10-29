<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ShowMoreButton from '@/components/registry/ShowMoreButton.vue';
import { useI18n } from '@/composables/useI18n';
import { useShowMore } from '@/composables/useShowMore';

export type Crosstab = {
    title: string;
    rows: {
        label: string;
        patients: number;
        known: number;
        rop: number;
        rop_percent: number | null;
        type_one: number;
        treated: number;
    }[];
};

/**
 * Retinopathy and treatment within each group of patients. A group may link to
 * the patients it counts. Past ten groups, the rest are folded behind a button.
 */
const props = defineProps<{
    table: Crosstab;
    rowHref?: (label: string) => string | null;
}>();

const { t } = useI18n();

const { expanded, hiddenCount, isFolded, toggle } = useShowMore(
    () => props.table.rows.length,
);
</script>

<template>
    <section class="rounded-xl border bg-card shadow-xs">
        <header class="border-b px-5 py-3.5">
            <h2 class="text-sm font-semibold">{{ table.title }}</h2>
            <p class="text-xs text-muted-foreground">
                {{ t('ROP rate among patients with a known ROP status') }}
            </p>
        </header>
        <div class="max-md:overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b bg-muted/40 text-xs text-muted-foreground"
                    >
                        <th class="px-5 py-2 text-start font-medium">
                            {{ t('Group') }}
                        </th>
                        <th class="px-3 py-2 text-end font-medium">
                            {{ t('Patients') }}
                        </th>
                        <th class="px-3 py-2 text-end font-medium">
                            {{ t('ROP') }}
                        </th>
                        <th class="px-3 py-2 text-start font-medium">
                            {{ t('ROP rate') }}
                        </th>
                        <th class="px-3 py-2 text-end font-medium">
                            {{ t('Type 1') }}
                        </th>
                        <th class="px-5 py-2 text-end font-medium">
                            {{ t('Treated') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in table.rows"
                        :key="row.label"
                        class="border-b tabular-nums last:border-b-0"
                        :class="{ 'hidden print:table-row': isFolded(index) }"
                    >
                        <td class="px-5 py-2.5">
                            <Link
                                v-if="rowHref?.(row.label)"
                                :href="rowHref(row.label)!"
                                class="hover:underline"
                                dir="auto"
                            >
                                {{ row.label }}
                            </Link>
                            <span v-else dir="auto">{{ row.label }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-end">{{ row.patients }}</td>
                        <td class="px-3 py-2.5 text-end">
                            {{ row.rop }}
                            <span class="text-xs text-muted-foreground"
                                >/ {{ row.known }}</span
                            >
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-2 w-24 rounded-full bg-muted"
                                >
                                    <span
                                        class="h-full rounded-full bg-series-1"
                                        :style="{
                                            width: `${row.rop_percent ?? 0}%`,
                                        }"
                                    />
                                </span>
                                <span class="w-12 text-xs">
                                    {{
                                        row.rop_percent === null
                                            ? '—'
                                            : `${row.rop_percent}%`
                                    }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-end">{{ row.type_one }}</td>
                        <td class="px-5 py-2.5 text-end">{{ row.treated }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="hiddenCount" class="border-t px-3 py-1.5">
            <ShowMoreButton
                :expanded="expanded"
                :hidden-count="hiddenCount"
                @toggle="toggle"
            />
        </div>
    </section>
</template>
