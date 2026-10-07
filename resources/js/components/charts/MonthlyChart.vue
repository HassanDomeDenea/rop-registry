<script setup lang="ts">
import { BarChart3, Table2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

type Month = {
    month: string;
    new_patients: number;
    examinations: number;
    treatments: number;
};

/**
 * Monthly activity as grouped columns: three count series on one shared axis,
 * with a legend, a hover read-out per month, and a table view of the same data.
 */
const props = defineProps<{ months: Month[] }>();

const { t, formatMonth, formatNumber, isRtl } = useI18n();

const series = computed(() => [
    {
        key: 'new_patients' as const,
        label: t('New patients'),
        color: 'var(--series-1)',
    },
    {
        key: 'examinations' as const,
        label: t('Examinations'),
        color: 'var(--series-2)',
    },
    {
        key: 'treatments' as const,
        label: t('Treatments'),
        color: 'var(--series-3)',
    },
]);

const view = ref<'chart' | 'table'>('chart');
const hovered = ref<number | null>(null);

const WIDTH = 960;
const HEIGHT = 260;
const PAD = { top: 12, right: 8, bottom: 28, left: 36 };

const plotWidth = WIDTH - PAD.left - PAD.right;
const plotHeight = HEIGHT - PAD.top - PAD.bottom;

/** A clean axis maximum: 1, 2 or 5 times a power of ten. */
const axisMax = computed(() => {
    const peak = Math.max(
        1,
        ...props.months.flatMap((month) => [
            month.new_patients,
            month.examinations,
            month.treatments,
        ]),
    );
    const magnitude = 10 ** Math.floor(Math.log10(peak));
    const step =
        [1, 2, 5, 10].find((factor) => factor * magnitude >= peak / 4)! *
        magnitude;

    return Math.ceil(peak / step) * step;
});

const ticks = computed(() =>
    [0, 0.25, 0.5, 0.75, 1]
        .map((fraction) => axisMax.value * fraction)
        .filter((value) => Number.isInteger(value)),
);

const band = computed(() => plotWidth / Math.max(1, props.months.length));
const barWidth = computed(() => Math.min(18, (band.value - 14) / 3));

function y(value: number) {
    return PAD.top + plotHeight - (value / axisMax.value) * plotHeight;
}

/** Columns run right-to-left in Arabic, matching the reading direction. */
function bandStart(index: number) {
    const position = isRtl.value ? props.months.length - 1 - index : index;

    return PAD.left + position * band.value;
}

function barX(monthIndex: number, seriesIndex: number) {
    const groupWidth = barWidth.value * 3 + 4;
    const order = isRtl.value ? 2 - seriesIndex : seriesIndex;

    return (
        bandStart(monthIndex) +
        (band.value - groupWidth) / 2 +
        order * (barWidth.value + 2)
    );
}

/** A column with a rounded data-end and a square baseline. */
function column(x: number, value: number) {
    const top = y(value);
    const bottom = y(0);
    const radius = Math.min(4, barWidth.value / 2, bottom - top);

    return `M${x},${bottom} V${top + radius} Q${x},${top} ${x + radius},${top} H${x + barWidth.value - radius} Q${x + barWidth.value},${top} ${x + barWidth.value},${top + radius} V${bottom} Z`;
}

const labelEvery = computed(() => Math.ceil(props.months.length / 14));
</script>

<template>
    <figure class="rounded-xl border bg-card p-5 shadow-xs">
        <figcaption class="mb-3 flex items-start justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold">
                    {{ t('Monthly activity') }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{
                        t(
                            'Counts per calendar month; undated records are not shown',
                        )
                    }}
                </p>
            </div>
            <div class="flex items-center gap-4">
                <ul
                    class="flex items-center gap-4 text-xs text-muted-foreground"
                >
                    <li
                        v-for="item in series"
                        :key="item.key"
                        class="flex items-center gap-1.5"
                    >
                        <span
                            class="size-2.5 rounded-[2px]"
                            :style="{ background: item.color }"
                        />
                        {{ item.label }}
                    </li>
                </ul>
                <Button
                    variant="outline"
                    size="sm"
                    class="no-print"
                    @click="view = view === 'chart' ? 'table' : 'chart'"
                >
                    <component :is="view === 'chart' ? Table2 : BarChart3" />
                    {{ view === 'chart' ? t('Table') : t('Chart') }}
                </Button>
            </div>
        </figcaption>

        <p
            v-if="months.length === 0"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            {{ t('No dated records in this period') }}
        </p>

        <div v-else-if="view === 'chart'" class="relative">
            <svg
                :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
                class="h-auto w-full"
                role="img"
                :aria-label="t('Monthly activity')"
                @mouseleave="hovered = null"
            >
                <g v-for="tick in ticks" :key="tick">
                    <line
                        :x1="PAD.left"
                        :x2="WIDTH - PAD.right"
                        :y1="y(tick)"
                        :y2="y(tick)"
                        stroke="var(--border)"
                        stroke-width="1"
                    />
                    <text
                        :x="isRtl ? WIDTH - PAD.right : PAD.left - 8"
                        :y="y(tick) + 4"
                        :text-anchor="isRtl ? 'start' : 'end'"
                        class="fill-muted-foreground text-[11px] tabular-nums"
                        :dy="isRtl ? -6 : 0"
                    >
                        {{ formatNumber(tick) }}
                    </text>
                </g>

                <g v-for="(month, index) in months" :key="month.month">
                    <rect
                        :x="bandStart(index)"
                        :y="PAD.top"
                        :width="band"
                        :height="plotHeight"
                        :fill="
                            hovered === index ? 'var(--muted)' : 'transparent'
                        "
                        @mouseenter="hovered = index"
                    />
                    <path
                        v-for="(item, seriesIndex) in series"
                        :key="item.key"
                        :d="column(barX(index, seriesIndex), month[item.key])"
                        :fill="item.color"
                        class="pointer-events-none"
                    />
                    <text
                        v-if="index % labelEvery === 0"
                        :x="bandStart(index) + band / 2"
                        :y="HEIGHT - 8"
                        text-anchor="middle"
                        class="pointer-events-none fill-muted-foreground text-[11px]"
                    >
                        {{ formatMonth(month.month) }}
                    </text>
                </g>
            </svg>

            <div
                v-if="hovered !== null"
                class="pointer-events-none absolute top-2 z-10 min-w-40 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                :style="{
                    left: `${Math.min(78, Math.max(2, ((bandStart(hovered) + band / 2) / WIDTH) * 100 - 8))}%`,
                }"
            >
                <p class="mb-1 font-medium">
                    {{ formatMonth(months[hovered].month) }}
                </p>
                <p
                    v-for="item in series"
                    :key="item.key"
                    class="flex items-center justify-between gap-4 text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <span
                            class="size-2 rounded-[2px]"
                            :style="{ background: item.color }"
                        />
                        {{ item.label }}
                    </span>
                    <span class="font-medium text-foreground tabular-nums">
                        {{ formatNumber(months[hovered][item.key]) }}
                    </span>
                </p>
            </div>
        </div>

        <div v-else class="max-h-72 overflow-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 bg-muted text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 text-start font-medium">
                            {{ t('Month') }}
                        </th>
                        <th
                            v-for="item in series"
                            :key="item.key"
                            class="px-3 py-2 text-end font-medium"
                        >
                            {{ item.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="month in months"
                        :key="month.month"
                        class="border-t tabular-nums"
                    >
                        <td class="px-3 py-1.5">
                            {{ formatMonth(month.month) }}
                        </td>
                        <td
                            v-for="item in series"
                            :key="item.key"
                            class="px-3 py-1.5 text-end"
                        >
                            {{ formatNumber(month[item.key]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </figure>
</template>
