<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CornerDownLeft, Search } from '@lucide/vue';
import { ref } from 'vue';
import PatientLookupResults from '@/components/registry/PatientLookupResults.vue';
import { useI18n } from '@/composables/useI18n';
import { usePatientLookup } from '@/composables/usePatientLookup';
import type { PatientMatch } from '@/composables/usePatientLookup';
import patientRoutes from '@/routes/patients';

/**
 * The search box of the top bar: matching patients appear while typing,
 * Enter opens the full patient list filtered by the same text.
 */
const { t } = useI18n();

const search = ref('');
const open = ref(false);
const active = ref(-1);

const { loading, matches, total, lookup, clear } = usePatientLookup();

function onInput() {
    open.value = true;
    active.value = -1;
    lookup(search.value);
}

function close() {
    open.value = false;
    active.value = -1;
}

function visit(match: PatientMatch) {
    close();
    search.value = '';
    clear();
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
    <form class="relative w-full max-w-sm min-w-0" @submit.prevent="submit">
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
            <PatientLookupResults
                v-model:active="active"
                :matches="matches"
                :loading="loading"
                @select="visit"
            />
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
