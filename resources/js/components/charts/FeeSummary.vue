<script setup lang="ts">
import { computed } from 'vue';
import ShowMoreButton from '@/components/registry/ShowMoreButton.vue';
import StatCard from '@/components/registry/StatCard.vue';
import { useI18n } from '@/composables/useI18n';
import { useShowMore } from '@/composables/useShowMore';

export type Fees = {
    total: number;
    visits: number;
    recorded: number;
    free: number;
    amounts: { fee: number; visits: number; total: number }[];
    months: { month: string | null; visits: number; total: number }[];
};

/**
 * The visit fees: the total collected, how many visits were charged each amount
 * (the regular fee, a discount, free), and the total of each month.
 */
const props = defineProps<{ fees: Fees }>();

const { t, formatNumber, formatMonth } = useI18n();

const notRecorded = computed(() => props.fees.visits - props.fees.recorded);

const busiest = computed(() =>
    Math.max(1, ...props.fees.amounts.map((amount) => amount.visits)),
);

function share(visits: number) {
    return props.fees.recorded > 0
        ? `${((visits / props.fees.recorded) * 100).toFixed(1)}%`
        : '—';
}

const months = useShowMore(() => props.fees.months.length, 6);
const amounts = useShowMore(() => props.fees.amounts.length);
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-base font-semibold tracking-tight">
            {{ t('Visit fees') }}
        </h2>

        <div class="grid grid-cols-4 gap-4">
            <StatCard
                :label="t('Fees recorded (IQD)')"
                :value="formatNumber(fees.total)"
                :hint="
                    fees.recorded > 0
                        ? t('Average :amount per charged visit', {
                              amount: formatNumber(
                                  Math.round(fees.total / fees.recorded),
                              ),
                          })
                        : null
                "
            />
            <StatCard
                :label="t('Visits with a fee')"
                :value="formatNumber(fees.recorded)"
                :hint="t('of :count visits', { count: fees.visits })"
            />
            <StatCard
                :label="t('Free visits (fee 0)')"
                :value="formatNumber(fees.free)"
            />
            <StatCard
                :label="t('No fee recorded')"
                :value="formatNumber(notRecorded)"
                :hint="t('Unknown, not counted as free')"
            />
        </div>

        <div class="grid grid-cols-2 gap-6">
            <section class="rounded-xl border bg-card shadow-xs">
                <header class="border-b px-5 py-3.5">
                    <h3 class="text-sm font-semibold">
                        {{ t('Visits by fee') }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Each amount charged, most frequent first') }}
                    </p>
                </header>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-muted/40 text-xs text-muted-foreground"
                        >
                            <th class="px-5 py-2 text-start font-medium">
                                {{ t('Fee (IQD)') }}
                            </th>
                            <th class="px-3 py-2 text-start font-medium">
                                {{ t('Visits') }}
                            </th>
                            <th class="px-5 py-2 text-end font-medium">
                                {{ t('Total (IQD)') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(amount, index) in fees.amounts"
                            :key="amount.fee"
                            class="border-b tabular-nums last:border-b-0"
                            :class="{
                                'hidden print:table-row':
                                    amounts.isFolded(index),
                            }"
                        >
                            <td class="px-5 py-2.5 font-medium">
                                {{
                                    amount.fee === 0
                                        ? t('Free')
                                        : formatNumber(amount.fee)
                                }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-2 w-24 rounded-full">
                                        <span
                                            class="h-full min-w-px rounded-full bg-series-1"
                                            :style="{
                                                width: `${(amount.visits / busiest) * 100}%`,
                                            }"
                                        />
                                    </span>
                                    <span>{{
                                        formatNumber(amount.visits)
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ share(amount.visits) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-end">
                                {{ formatNumber(amount.total) }}
                            </td>
                        </tr>
                        <tr v-if="fees.amounts.length === 0">
                            <td
                                colspan="3"
                                class="px-5 py-6 text-center text-muted-foreground"
                            >
                                {{ t('No data recorded') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="amounts.hiddenCount.value"
                    class="border-t px-3 py-1.5"
                >
                    <ShowMoreButton
                        :expanded="amounts.expanded.value"
                        :hidden-count="amounts.hiddenCount.value"
                        @toggle="amounts.toggle"
                    />
                </div>
            </section>

            <section class="rounded-xl border bg-card shadow-xs">
                <header class="border-b px-5 py-3.5">
                    <h3 class="text-sm font-semibold">
                        {{ t('Fees by month') }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ t('Newest first') }}
                    </p>
                </header>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-muted/40 text-xs text-muted-foreground"
                        >
                            <th class="px-5 py-2 text-start font-medium">
                                {{ t('Month') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium">
                                {{ t('Visits with a fee') }}
                            </th>
                            <th class="px-5 py-2 text-end font-medium">
                                {{ t('Total (IQD)') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(month, index) in fees.months"
                            :key="month.month ?? 'undated'"
                            class="border-b tabular-nums last:border-b-0"
                            :class="{
                                'hidden print:table-row':
                                    months.isFolded(index),
                            }"
                        >
                            <td class="px-5 py-2.5">
                                {{
                                    month.month
                                        ? formatMonth(month.month)
                                        : t('No date')
                                }}
                            </td>
                            <td class="px-3 py-2.5 text-end">
                                {{ formatNumber(month.visits) }}
                            </td>
                            <td class="px-5 py-2.5 text-end">
                                {{ formatNumber(month.total) }}
                            </td>
                        </tr>
                        <tr v-if="fees.months.length === 0">
                            <td
                                colspan="3"
                                class="px-5 py-6 text-center text-muted-foreground"
                            >
                                {{ t('No data recorded') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="months.hiddenCount.value"
                    class="border-t px-3 py-1.5"
                >
                    <ShowMoreButton
                        :expanded="months.expanded.value"
                        :hidden-count="months.hiddenCount.value"
                        @toggle="months.toggle"
                    />
                </div>
            </section>
        </div>
    </section>
</template>
