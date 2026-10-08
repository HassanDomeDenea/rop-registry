<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CornerDownLeft, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';

/**
 * The search box of the top bar: matching patients appear while typing,
 * Enter opens the full patient list filtered by the same text.
 */
type Match = {
    id: number;
    name: string;
    file_number: string | null;
    dob: string | null;
    phone: string | null;
    unverified: boolean;
};

const { t, formatDate } = useI18n();

const search = ref('');
const open = ref(false);
const loading = ref(false);
const matches = ref<Match[]>([]);
const total = ref(0);
const active = ref(-1);

let latest = 0;

const lookup = useDebounceFn(async () => {
    const term = search.value.trim();
    const request = ++latest;

    if (term === '') {
        matches.value = [];
        total.value = 0;
        loading.value = false;

        return;
    }

    const response = await fetch(
        patientRoutes.lookup.url({ query: { search: term } }),
        { headers: { Accept: 'application/json' } },
    );

    // A slower, older answer must not replace a newer one.
    if (request !== latest) {
        return;
    }

    const result = response.ok
        ? ((await response.json()) as { total: number; patients: Match[] })
        : { total: 0, patients: [] };

    matches.value = result.patients;
    total.value = result.total;
    loading.value = false;
}, 250);

function onInput() {
    open.value = true;
    active.value = -1;
    loading.value = search.value.trim() !== '';
    void lookup();
}

function close() {
    open.value = false;
    active.value = -1;
}

function visit(match: Match) {
    close();
    search.value = '';
    matches.value = [];
    router.get(patientRoutes.show.url(match.id));
}

function showAll() {
    close();
    router.get(patientRoutes.index.url(), { search: search.value.trim() });
}

function submit() {
    const match = matches.value[active.value];

    if (match) {
        visit(match);
    } else if (search.value.trim() !== '') {
        showAll();
    }
}

function move(step: number) {
    const count = matches.value.length;

    open.value = true;
    active.value = count ? (active.value + step + count) % count : -1;
}
</script>

<template>
    <form class="relative w-full max-w-sm" @submit.prevent="submit">
        <Search
            class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            v-model="search"
            type="search"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open && search.trim() !== ''"
            :placeholder="t('Search patients by name, file no. or phone…')"
            class="h-9 w-full rounded-md border border-transparent bg-muted ps-8 pe-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50"
            @input="onInput"
            @focus="open = true"
            @blur="close"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.esc="close"
        />

        <div
            v-if="open && search.trim() !== ''"
            class="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-lg border bg-popover text-sm text-popover-foreground shadow-lg"
            @mousedown.prevent
        >
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
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-md px-2.5 py-1.5"
                    :class="index === active ? 'bg-accent' : 'hover:bg-muted'"
                    @mousemove="active = index"
                    @click="visit(match)"
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
                    <span
                        v-if="match.file_number"
                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                    >
                        #{{ match.file_number }}
                    </span>
                </li>
            </ul>
            <button
                v-if="matches.length"
                type="submit"
                class="flex w-full items-center justify-between gap-3 border-t bg-muted/40 px-3 py-2 text-xs text-muted-foreground hover:text-foreground"
            >
                {{ t('Show all :count results', { count: total }) }}
                <kbd
                    class="inline-flex items-center gap-1 rounded border bg-card px-1.5 py-0.5 font-sans"
                >
                    <CornerDownLeft class="size-3" />
                    Enter
                </kbd>
            </button>
        </div>
    </form>
</template>
