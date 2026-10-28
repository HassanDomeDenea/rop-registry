<script setup lang="ts">
import { computed } from 'vue';
import ShowMoreButton from '@/components/registry/ShowMoreButton.vue';
import { useI18n } from '@/composables/useI18n';
import { useShowMore } from '@/composables/useShowMore';
import type { ChartData } from '@/types';

/**
 * A single-series horizontal bar chart for a distribution. Every bar carries its
 * value and share at the tip, and the footer states the denominator and how many
 * values are unknown, so no count is read without its context.
 * Past ten bars, the rest are folded behind a button.
 */
const props = defineProps<{ chart: ChartData }>();

const { t, formatNumber } = useI18n();

const { expanded, hiddenCount, isFolded, toggle } = useShowMore(
    () => props.chart.items.length,
);

const known = computed(() =>
    props.chart.items.reduce((sum, item) => sum + item.value, 0),
);

const max = computed(() =>
    Math.max(1, ...props.chart.items.map((item) => item.value)),
);

function share(value: number) {
    // Shares are of the known values only when the categories partition the total.
    const base =
        props.chart.total > 0 ? props.chart.total - props.chart.unknown : 0;

    if (base <= 0 || known.value > base) {
        return null;
    }

    return `${((value / base) * 100).toFixed(value === base || value === 0 ? 0 : 1)}%`;
}

/**
 * Labels without Arabic letters, such as "28–29" or "< 1000", are numbers and symbols
 * only. Read in a right-to-left line they come out reversed ("29–28"), so they keep
 * their left-to-right order.
 */
function labelDirection(label: string) {
    return /\p{Script=Arabic}/u.test(label) ? undefined : 'ltr';
}
</script>

<template>
    <figure
        class="flex h-full flex-col rounded-xl border bg-card p-5 shadow-xs"
    >
        <figcaption class="mb-4">
            <h3 class="text-sm font-semibold">{{ chart.title }}</h3>
            <p class="text-xs text-muted-foreground">
                {{ t('Number of :unit', { unit: chart.unit }) }}
            </p>
        </figcaption>

        <ul v-if="known > 0" class="flex-1 space-y-2.5">
            <li
                v-for="(item, index) in chart.items"
                :key="item.label"
                class="group grid grid-cols-[minmax(0,9.5rem)_1fr_auto] items-center gap-3 text-sm"
                :class="{ 'hidden print:grid': isFolded(index) }"
                :title="`${item.label}: ${formatNumber(item.value)} ${chart.unit}${share(item.value) ? ` (${share(item.value)})` : ''}`"
            >
                <span
                    class="truncate text-muted-foreground group-hover:text-foreground"
                >
                    <bdi :dir="labelDirection(item.label)">{{
                        item.label
                    }}</bdi>
                </span>
                <span class="flex h-3.5 items-center">
                    <span
                        class="h-full min-w-px rounded-e-[4px] bg-series-1 transition-opacity group-hover:opacity-80"
                        :style="{ width: `${(item.value / max) * 100}%` }"
                    />
                </span>
                <!-- Separate boxes keep the count and the share from running together as one number. -->
                <span
                    class="flex min-w-16 items-baseline justify-end gap-1.5 tabular-nums"
                >
                    <span class="font-medium">{{
                        formatNumber(item.value)
                    }}</span>
                    <span
                        v-if="share(item.value)"
                        class="text-xs text-muted-foreground"
                    >
                        {{ share(item.value) }}
                    </span>
                </span>
            </li>
        </ul>
        <ShowMoreButton
            class="-ms-2 mt-2 self-start"
            :expanded="expanded"
            :hidden-count="hiddenCount"
            @toggle="toggle"
        />
        <p
            v-if="known === 0"
            class="flex-1 py-6 text-center text-sm text-muted-foreground"
        >
            {{ t('No data recorded') }}
        </p>

        <p
            v-if="chart.total > 0"
            class="mt-4 border-t pt-3 text-xs text-muted-foreground tabular-nums"
        >
            {{
                t('Out of :total :unit', {
                    total: formatNumber(chart.total),
                    unit: chart.unit,
                })
            }}
            <template v-if="chart.unknown > 0">
                ·
                {{
                    t(':count not recorded', {
                        count: formatNumber(chart.unknown),
                    })
                }}
            </template>
        </p>
    </figure>
</template>
