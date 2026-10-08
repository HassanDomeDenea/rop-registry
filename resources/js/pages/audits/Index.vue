<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import AuditTimeline from '@/components/registry/AuditTimeline.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pagination from '@/components/registry/Pagination.vue';
import { useI18n } from '@/composables/useI18n';
import auditRoutes from '@/routes/audits';
import type { Audit, Paginated } from '@/types';

const props = defineProps<{
    audits: Paginated<Audit>;
    filters: { event: string; type: string; search: string };
}>();

const { t } = useI18n();

const state = reactive<Record<string, string | number | boolean | null>>({
    event: props.filters.event || null,
    type: props.filters.type || null,
    search: props.filters.search,
});

const events = computed(() => [
    { value: 'created', label: t('Created') },
    { value: 'updated', label: t('Updated') },
    { value: 'deleted', label: t('Deleted') },
    { value: 'restored', label: t('Restored') },
]);

const types = computed(() => [
    { value: 'patient', label: t('Patient') },
    { value: 'visit', label: t('Visit') },
    { value: 'treatment', label: t('Treatment') },
    { value: 'attachment', label: t('Attachment') },
]);

function reload() {
    router.get(
        auditRoutes.index.url(),
        Object.fromEntries(
            Object.entries(state).filter(([, value]) => value),
        ) as Record<string, string>,
        { preserveState: true, replace: true },
    );
}

const reloadDebounced = useDebounceFn(reload, 300);
</script>

<template>
    <Head :title="t('Audit log')" />

    <PageHeader
        :title="t('Audit log')"
        :description="
            t(
                'Every creation, change and deletion in the registry, newest first',
            )
        "
    />

    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 flex-1">
            <Search
                class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                v-model="state.search"
                type="search"
                :placeholder="t('Search by patient or record…')"
                class="h-9 w-full rounded-md border border-input bg-card ps-8 pe-3 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                @input="reloadDebounced"
            />
        </div>
        <NativeSelect
            v-model="state.event"
            class="w-44 bg-card"
            :options="events"
            :placeholder="t('Any action')"
            @update:model-value="reload"
        />
        <NativeSelect
            v-model="state.type"
            class="w-44 bg-card"
            :options="types"
            :placeholder="t('Any record')"
            @update:model-value="reload"
        />
    </div>

    <div v-if="audits.last_page > 1" class="rounded-xl border bg-card">
        <Pagination :paginator="audits" top class="border-b-0" />
    </div>

    <AuditTimeline :audits="audits.data" />

    <div v-if="audits.total > 0" class="rounded-xl border bg-card">
        <Pagination :paginator="audits" class="border-t-0" />
    </div>
</template>
