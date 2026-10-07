<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ClipboardCheck, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pagination from '@/components/registry/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import reviewRoutes from '@/routes/review';
import reviewItemRoutes from '@/routes/review-items';
import type { Paginated, ReviewItem } from '@/types';

type Row = ReviewItem & {
    patient_name: string;
    patient_file_number: string | null;
};

const props = defineProps<{
    items: Paginated<Row>;
    filters: { search: string };
}>();

const { t, formatNumber } = useI18n();

const search = ref(props.filters.search);

const columns = computed<Column[]>(() => [
    { key: 'patient_name', label: t('Patient'), class: 'w-56 align-top' },
    {
        key: 'field',
        label: t('Field'),
        class: 'w-48 align-top text-muted-foreground',
    },
    { key: 'issue', label: t('To check'), class: 'align-top' },
    { key: 'actions', label: '', align: 'end', class: 'align-top' },
]);

const reload = useDebounceFn(() => {
    router.get(
        reviewRoutes.index.url(),
        search.value ? { search: search.value } : {},
        { preserveState: true, replace: true },
    );
}, 300);

function resolve(item: Row) {
    router.patch(
        reviewItemRoutes.update.url(item.id),
        { resolved: true },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('Review queue')" />

    <PageHeader
        :title="t('Review queue')"
        :description="
            t(
                ':count open items: uncertain handwriting, conflicting sources and unlinked index entries',
                {
                    count: formatNumber(items.total),
                },
            )
        "
    />

    <section class="rounded-xl border bg-card shadow-xs">
        <div class="border-b p-3">
            <div class="relative max-w-md">
                <Search
                    class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    dir="auto"
                    :placeholder="t('Search by patient…')"
                    class="h-9 w-full rounded-md border border-input bg-transparent ps-8 pe-3 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    @input="reload"
                />
            </div>
        </div>

        <Pagination :paginator="items" top />

        <DataTable :columns="columns" :rows="items.data" dense>
            <template #cell-patient_name="{ row }">
                <Link
                    :href="
                        patientRoutes.show(row.patient_id, {
                            query: { tab: 'review' },
                        })
                    "
                    class="font-medium hover:underline"
                    dir="auto"
                >
                    {{ row.patient_name }}
                </Link>
                <p
                    v-if="row.patient_file_number"
                    class="text-xs text-muted-foreground tabular-nums"
                >
                    #{{ row.patient_file_number }}
                </p>
            </template>
            <template #cell-field="{ row }">
                <span class="text-xs">{{ row.field ?? '—' }}</span>
            </template>
            <template #cell-issue="{ row }">
                <p dir="auto">{{ row.issue }}</p>
                <p
                    v-if="row.source_reference"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ row.source_reference }}
                </p>
            </template>
            <template #cell-actions="{ row }">
                <Button variant="outline" size="sm" @click="resolve(row)">
                    <Check />
                    {{ t('Resolve') }}
                </Button>
            </template>
            <template #empty>
                <EmptyState
                    :icon="ClipboardCheck"
                    :title="t('Nothing to review')"
                />
            </template>
        </DataTable>

        <Pagination :paginator="items" />
    </section>
</template>
