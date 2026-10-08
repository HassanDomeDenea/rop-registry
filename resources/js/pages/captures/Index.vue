<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    FileText,
    FolderInput,
    Printer,
    Trash2,
    UserRoundCheck,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import FormField from '@/components/registry/FormField.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCaptureStatus } from '@/composables/useCaptureStatus';
import { useI18n } from '@/composables/useI18n';
import captureRoutes from '@/routes/captures';
import patientRoutes from '@/routes/patients';

type Capture = {
    id: number;
    name: string;
    size: number;
    is_image: boolean;
    url: string;
};

type Batch = {
    id: string;
    source: 'printer' | 'export';
    received_at: string;
    captures: Capture[];
};

type PatientOption = {
    id: number;
    name: string;
    file_number: string | null;
    dob: string | null;
};

const props = defineProps<{
    batches: Batch[];
    suggestions: PatientOption[];
    folder: string | null;
    command: string;
}>();

const { t, formatDate, formatDateTime, formatBytes } = useI18n();
const { pending, refresh } = useCaptureStatus();

// New images appear without pressing reload.
watch(pending, (count) => {
    const shown = props.batches.reduce(
        (total, batch) => total + batch.captures.length,
        0,
    );

    if (count !== shown) {
        router.reload({ only: ['batches', 'suggestions'] });
    }
});

/** Images left out of an assignment; everything in a batch is included by default. */
const excluded = reactive(new Set<number>());

const chosen = (batch: Batch) =>
    batch.captures.filter((capture) => !excluded.has(capture.id));

function toggle(capture: Capture) {
    if (excluded.has(capture.id)) {
        excluded.delete(capture.id);
    } else {
        excluded.add(capture.id);
    }
}

// --- Assigning -------------------------------------------------------------
const assigning = ref<Batch | null>(null);
const search = ref('');
const matches = ref<PatientOption[]>([]);
const patient = ref<PatientOption | null>(null);
const todayVisit = ref(true);
const processing = ref(false);

const candidates = computed(() =>
    search.value.trim() === '' ? props.suggestions : matches.value,
);

const lookup = useDebounceFn(async () => {
    const term = search.value.trim();

    if (term === '') {
        matches.value = [];

        return;
    }

    const response = await fetch(
        patientRoutes.lookup.url({ query: { search: term } }),
        { headers: { Accept: 'application/json' } },
    );

    matches.value = response.ok ? (await response.json()).patients : [];
}, 250);

function openAssign(batch: Batch) {
    assigning.value = batch;
    search.value = '';
    matches.value = [];
    patient.value = null;
    todayVisit.value = true;
}

function assign() {
    if (!assigning.value || !patient.value) {
        return;
    }

    router.post(
        captureRoutes.assign.url(),
        {
            captures: chosen(assigning.value).map((capture) => capture.id),
            patient_id: patient.value.id,
            today_visit: todayVisit.value,
        },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => {
                assigning.value = null;
                void refresh();
            },
        },
    );
}

// --- Discarding ------------------------------------------------------------
const discarding = ref<Capture[] | null>(null);

function discard() {
    if (!discarding.value) {
        return;
    }

    router.post(
        captureRoutes.discard.url(),
        { captures: discarding.value.map((capture) => capture.id) },
        {
            preserveScroll: true,
            onSuccess: () => {
                discarding.value = null;
                void refresh();
            },
        },
    );
}

// --- Viewing and printing --------------------------------------------------
const viewing = ref<Capture | null>(null);

function open(capture: Capture) {
    if (capture.is_image) {
        viewing.value = capture;
    } else {
        window.open(capture.url, '_blank');
    }
}

function print(capture: Capture) {
    if (!capture.is_image) {
        window.open(capture.url, '_blank');

        return;
    }

    const frame = document.createElement('iframe');

    frame.style.cssText = 'position:fixed;width:0;height:0;border:0';
    frame.srcdoc = `<style>@page{margin:8mm}html,body{margin:0}img{max-width:100%;max-height:100vh;display:block;margin:auto}</style><img src="${capture.url}" onload="focus();print()">`;
    frame.onload = () => window.setTimeout(() => frame.remove(), 60_000);
    document.body.append(frame);
}

// --- Export folder ---------------------------------------------------------
const settings = useForm({ folder: props.folder });

function saveFolder() {
    settings.put(captureRoutes.settings.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Camera inbox')" />

    <PageHeader
        :title="t('Camera inbox')"
        :description="
            t('Images received from the camera that wait for a patient')
        "
    />

    <SectionCard v-if="batches.length === 0">
        <EmptyState
            :icon="Camera"
            :title="t('No images are waiting')"
            :description="
                t(
                    'Images printed to the virtual printer or exported into the camera folder appear here within a few seconds.',
                )
            "
        />
    </SectionCard>

    <SectionCard
        v-for="batch in batches"
        :key="batch.id"
        :icon="batch.source === 'printer' ? Printer : FolderInput"
        :title="
            t(':count image(s) · :time', {
                count: batch.captures.length,
                time: formatDateTime(batch.received_at),
            })
        "
        :description="
            batch.source === 'printer'
                ? t('Printed to the virtual printer')
                : t('Exported into the camera folder')
        "
    >
        <template #actions>
            <Button
                variant="ghost"
                size="sm"
                class="text-muted-foreground hover:text-destructive"
                :disabled="chosen(batch).length === 0"
                @click="discarding = chosen(batch)"
            >
                <Trash2 />
                {{ t('Discard') }}
            </Button>
            <Button
                size="sm"
                :disabled="chosen(batch).length === 0"
                @click="openAssign(batch)"
            >
                <UserRoundCheck />
                {{
                    t('Assign :count to a patient', {
                        count: chosen(batch).length,
                    })
                }}
            </Button>
        </template>

        <ul class="grid grid-cols-4 gap-4">
            <li
                v-for="capture in batch.captures"
                :key="capture.id"
                class="group relative overflow-hidden rounded-lg border bg-muted/30 transition-opacity"
                :class="excluded.has(capture.id) ? 'opacity-45' : ''"
            >
                <button
                    type="button"
                    class="block aspect-4/3 w-full"
                    :title="t('View')"
                    @click="open(capture)"
                >
                    <img
                        v-if="capture.is_image"
                        :src="capture.url"
                        :alt="capture.name"
                        loading="lazy"
                        class="size-full object-contain"
                    />
                    <span
                        v-else
                        class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
                    >
                        <FileText class="size-10" />
                        PDF
                    </span>
                </button>

                <button
                    type="button"
                    role="checkbox"
                    :aria-checked="!excluded.has(capture.id)"
                    :title="t('Include in the assignment')"
                    class="absolute start-2 top-2 flex size-6 items-center justify-center rounded-md border shadow-sm"
                    :class="
                        excluded.has(capture.id)
                            ? 'border-input bg-card'
                            : 'border-primary bg-primary text-primary-foreground'
                    "
                    @click="toggle(capture)"
                >
                    <Check v-if="!excluded.has(capture.id)" class="size-4" />
                </button>

                <div
                    class="flex items-center justify-between gap-2 border-t bg-card px-2.5 py-1.5 text-xs text-muted-foreground"
                >
                    <span class="truncate" dir="ltr">{{ capture.name }}</span>
                    <span class="flex shrink-0 items-center gap-0.5">
                        <span class="me-1 tabular-nums">
                            {{ formatBytes(capture.size) }}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :title="t('Print')"
                            @click="print(capture)"
                        >
                            <Printer />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="hover:text-destructive"
                            :title="t('Discard')"
                            @click="discarding = [capture]"
                        >
                            <Trash2 />
                        </Button>
                    </span>
                </div>
            </li>
        </ul>
    </SectionCard>

    <SectionCard
        :title="t('How images get here')"
        :icon="FolderInput"
        :description="t('Two ways; both can be used at the same time')"
    >
        <div class="grid grid-cols-2 gap-8 text-sm">
            <div class="space-y-2">
                <h3 class="font-semibold">
                    {{ t('1. Virtual printer') }}
                </h3>
                <p class="text-muted-foreground">
                    {{
                        t(
                            'Set the virtual printer to save each page as PNG and to run this file after printing, with the saved file paths as parameters:',
                        )
                    }}
                </p>
                <code
                    class="block rounded-md bg-muted px-3 py-2 text-xs break-all select-all"
                    dir="ltr"
                >
                    {{ command }}
                </code>
            </div>

            <form class="space-y-2" @submit.prevent="saveFolder">
                <h3 class="font-semibold">{{ t('2. Camera folder') }}</h3>
                <p class="text-muted-foreground">
                    {{
                        t(
                            'Export or save images from the camera software into this folder. The registry takes them over and empties the folder.',
                        )
                    }}
                </p>
                <FormField
                    :label="t('Folder path')"
                    for="folder"
                    :error="settings.errors.folder"
                    :hint="t('Leave empty to switch the folder off')"
                >
                    <div class="flex gap-2">
                        <TextInput
                            id="folder"
                            v-model="settings.folder"
                            dir="ltr"
                            placeholder="C:\ROP-Camera"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="settings.processing"
                        >
                            {{ t('Save') }}
                        </Button>
                    </div>
                </FormField>
            </form>
        </div>
    </SectionCard>

    <!-- Assign -->
    <Dialog
        :open="assigning !== null"
        @update:open="(value) => !value && (assigning = null)"
    >
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('Assign to a patient') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            ':count image(s) will be moved to the attachments of the patient',
                            {
                                count: assigning ? chosen(assigning).length : 0,
                            },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <TextInput
                v-model="search"
                v-focus
                type="search"
                :placeholder="t('Search patients by name, file no. or phone…')"
                @input="lookup"
            />

            <p
                v-if="search.trim() === '' && suggestions.length"
                class="-mb-2 text-xs text-muted-foreground"
            >
                {{ t('Seen or expected today') }}
            </p>

            <ul class="max-h-64 space-y-1 overflow-auto">
                <li v-for="option in candidates" :key="option.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-start text-sm"
                        :class="
                            patient?.id === option.id
                                ? 'border-primary bg-primary/10'
                                : 'border-transparent hover:bg-muted'
                        "
                        @click="patient = option"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">
                                <bdi>{{ option.name }}</bdi>
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ formatDate(option.dob) }}
                            </span>
                        </span>
                        <span
                            v-if="option.file_number"
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            #{{ option.file_number }}
                        </span>
                    </button>
                </li>
                <li
                    v-if="candidates.length === 0"
                    class="px-3 py-4 text-center text-sm text-muted-foreground"
                >
                    {{
                        search.trim() === ''
                            ? t('Type a name to find the patient')
                            : t('No patient matches')
                    }}
                </li>
            </ul>

            <label class="flex items-start gap-2.5 text-sm">
                <input
                    v-model="todayVisit"
                    type="checkbox"
                    class="mt-1 size-4 accent-primary"
                />
                <span>
                    {{ t('Attach to the visit of today') }}
                    <span class="block text-xs text-muted-foreground">
                        {{
                            t(
                                'When the patient has no visit dated today, the images are attached to the patient only.',
                            )
                        }}
                    </span>
                </span>
            </label>

            <DialogFooter>
                <Button variant="outline" @click="assigning = null">
                    {{ t('Cancel') }}
                </Button>
                <Button :disabled="!patient || processing" @click="assign">
                    {{ t('Assign') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- View -->
    <Dialog
        :open="viewing !== null"
        @update:open="(value) => !value && (viewing = null)"
    >
        <DialogContent class="max-w-[92vw] sm:max-w-[92vw]">
            <DialogHeader>
                <DialogTitle dir="ltr" class="text-start">
                    {{ viewing?.name }}
                </DialogTitle>
            </DialogHeader>
            <img
                v-if="viewing"
                :src="viewing.url"
                :alt="viewing.name"
                class="mx-auto max-h-[78vh] max-w-full object-contain"
            />
            <DialogFooter>
                <Button variant="outline" @click="viewing && print(viewing)">
                    <Printer />
                    {{ t('Print') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="discarding !== null"
        :title="t('Discard these images?')"
        :description="
            t(
                ':count image(s) will be deleted for good. This cannot be undone.',
                { count: discarding?.length ?? 0 },
            )
        "
        :confirm-label="t('Discard')"
        @update:open="(value: boolean) => !value && (discarding = null)"
        @confirm="discard"
    />
</template>
