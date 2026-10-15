<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
    FileText,
    ImagePlus,
    Trash2,
    UploadCloud,
} from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import NativeSelect from '@/components/registry/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import attachmentRoutes from '@/routes/attachments';
import patientRoutes from '@/routes/patients';
import type { Attachment, Visit } from '@/types';

/**
 * Pictures and PDF documents of a patient. Files can be chosen from the device,
 * dropped onto the panel, or pasted from the clipboard (e.g. a screenshot).
 */
const props = defineProps<{
    patientId: number;
    attachments: Attachment[];
    visits: Visit[];
    /** The visit being worked on: shown first and preselected for uploads. */
    focusVisitId?: number | null;
}>();

const { t, formatBytes, formatDate, isRtl } = useI18n();

const ACCEPTED = /^(image\/(jpeg|png|webp|gif|bmp)|application\/pdf)$/;

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const uploading = ref(false);
const progress = ref(0);
const error = ref<string | null>(null);
const visitId = ref<string | number | boolean | null>(
    props.focusVisitId ?? null,
);
const viewerIndex = ref<number | null>(null);
const deleting = ref<Attachment | null>(null);

const visitOptions = computed(() =>
    props.visits.map((visit, index) => ({
        value: visit.id,
        label: `${t('Visit')} ${index + 1} · ${visit.visit_date ? formatDate(visit.visit_date) : t('Date not documented')}`,
    })),
);

/** Which attachments are listed: all of them, those of no visit, or those of one visit. */
const filter = ref<string | number | boolean | null>(
    props.focusVisitId &&
        props.attachments.some(
            (attachment) => attachment.visit_id === props.focusVisitId,
        )
        ? String(props.focusVisitId)
        : 'all',
);

function matches(attachment: Attachment, value: unknown) {
    if (value === 'all') {
        return true;
    }

    return value === 'none'
        ? attachment.visit_id === null
        : String(attachment.visit_id) === String(value);
}

const filterOptions = computed(() => {
    const count = (value: string) =>
        props.attachments.filter((attachment) => matches(attachment, value))
            .length;

    return [
        {
            value: 'all',
            label: `${t('All attachments')} (${props.attachments.length})`,
        },
        ...visitOptions.value.map((option) => ({
            value: String(option.value),
            label: `${option.label}${option.value === props.focusVisitId ? ` · ${t('this visit')}` : ''} (${count(String(option.value))})`,
        })),
        {
            value: 'none',
            label: `${t('Not linked to a visit')} (${count('none')})`,
        },
    ];
});

const shown = computed(() =>
    props.attachments.filter((attachment) => matches(attachment, filter.value)),
);

const viewed = computed(() =>
    viewerIndex.value === null ? null : shown.value[viewerIndex.value],
);

function upload(files: File[]) {
    const accepted = files.filter((file) => ACCEPTED.test(file.type));

    error.value =
        accepted.length < files.length
            ? t('Only pictures and PDF documents can be attached.')
            : null;

    if (accepted.length === 0) {
        return;
    }

    router.post(
        patientRoutes.attachments.store.url(props.patientId),
        {
            files: accepted,
            visit_id: visitId.value === null ? null : Number(visitId.value),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                uploading.value = true;
                progress.value = 0;
            },
            onProgress: (event) => (progress.value = event?.percentage ?? 0),
            onError: (errors) =>
                (error.value = Object.values(errors)[0] ?? null),
            onFinish: () => (uploading.value = false),
        },
    );
}

function onPick(event: Event) {
    const target = event.target as HTMLInputElement;

    upload(Array.from(target.files ?? []));
    target.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    upload(Array.from(event.dataTransfer?.files ?? []));
}

// Pasting anywhere on the page while this panel is shown attaches the clipboard files.
useEventListener(document, 'paste', (event: ClipboardEvent) => {
    const files = Array.from(event.clipboardData?.files ?? []);

    if (
        files.length === 0 ||
        (event.target as HTMLElement).closest('input, textarea')
    ) {
        return;
    }

    event.preventDefault();

    // Clipboard images arrive as "image.png"; a timestamp keeps them distinguishable.
    upload(
        files.map((file) =>
            file.name === 'image.png'
                ? new File([file], `pasted-${Date.now()}.png`, {
                      type: file.type,
                  })
                : file,
        ),
    );
});

useEventListener(document, 'keydown', (event: KeyboardEvent) => {
    if (viewerIndex.value === null) {
        return;
    }

    if (event.key === 'ArrowRight') {
        step(isRtl.value ? -1 : 1);
    } else if (event.key === 'ArrowLeft') {
        step(isRtl.value ? 1 : -1);
    }
});

function step(offset: number) {
    if (viewerIndex.value === null) {
        return;
    }

    const count = shown.value.length;

    viewerIndex.value = (viewerIndex.value + offset + count) % count;
}

function saveCaption(attachment: Attachment, caption: string) {
    if ((attachment.caption ?? '') === caption) {
        return;
    }

    router.patch(
        attachmentRoutes.update.url(attachment.id),
        { caption: caption || null, visit_id: attachment.visit_id },
        { preserveScroll: true, preserveState: true },
    );
}

/** Link the attachment to another visit of the patient, or to none. */
function link(attachment: Attachment, value: unknown) {
    const visit = value === null || value === '' ? null : Number(value);

    if (visit === attachment.visit_id) {
        return;
    }

    router.patch(
        attachmentRoutes.update.url(attachment.id),
        { caption: attachment.caption, visit_id: visit },
        { preserveScroll: true, preserveState: true },
    );
}

function destroy() {
    if (!deleting.value) {
        return;
    }

    router.delete(attachmentRoutes.destroy.url(deleting.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            deleting.value = null;
            viewerIndex.value = null;
        },
    });
}
</script>

<template>
    <div class="@container space-y-5">
        <div
            class="rounded-xl border-2 border-dashed px-4 py-5 text-center transition-colors @xl:px-6 @xl:py-7"
            :class="
                dragging
                    ? 'border-primary bg-accent'
                    : 'border-border bg-muted/30'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <UploadCloud class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-2 text-sm font-medium">
                {{ t('Drop pictures or PDF files here') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ t('or paste from the clipboard with Ctrl+V') }}
            </p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <NativeSelect
                    v-if="visits.length"
                    v-model="visitId"
                    class="w-56"
                    size="sm"
                    :options="visitOptions"
                    :placeholder="t('Not linked to a visit')"
                />
                <Button size="sm" :disabled="uploading" @click="input?.click()">
                    <ImagePlus />
                    {{ t('Choose files') }}
                </Button>
            </div>
            <input
                ref="input"
                type="file"
                class="hidden"
                multiple
                accept="image/jpeg,image/png,image/webp,image/gif,image/bmp,application/pdf"
                @change="onPick"
            />
            <div
                v-if="uploading"
                class="mx-auto mt-4 h-1.5 max-w-xs overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full bg-primary transition-[width]"
                    :style="{ width: `${progress}%` }"
                />
            </div>
            <p v-if="error" class="mt-3 text-sm text-destructive">
                {{ error }}
            </p>
        </div>

        <div
            v-if="attachments.length && visits.length"
            class="flex items-center justify-between gap-3"
        >
            <NativeSelect
                v-model="filter"
                class="w-72 max-w-full"
                size="sm"
                :options="filterOptions"
                :nullable="false"
                :aria-label="t('Show')"
            />
            <span class="text-xs text-muted-foreground tabular-nums">
                {{ shown.length }} / {{ attachments.length }}
            </span>
        </div>

        <div
            v-if="shown.length"
            class="grid grid-cols-2 gap-4 @2xl:grid-cols-3 @4xl:grid-cols-4"
        >
            <figure
                v-for="(attachment, index) in shown"
                :key="attachment.id"
                class="group overflow-hidden rounded-lg border bg-card"
            >
                <button
                    type="button"
                    class="relative block aspect-[4/3] w-full overflow-hidden bg-muted"
                    @click="viewerIndex = index"
                >
                    <img
                        v-if="attachment.is_image"
                        :src="attachment.url"
                        :alt="attachment.caption ?? attachment.original_name"
                        loading="lazy"
                        class="size-full object-cover transition-transform duration-200 group-hover:scale-[1.03]"
                    />
                    <span
                        v-else
                        class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
                    >
                        <FileText class="size-10" />
                        <span class="text-xs font-medium">PDF</span>
                    </span>
                </button>
                <figcaption class="space-y-1 p-2.5">
                    <input
                        :value="attachment.caption ?? ''"
                        :placeholder="attachment.original_name"
                        dir="auto"
                        class="w-full truncate rounded-sm bg-transparent text-sm outline-none placeholder:text-foreground/80 focus:bg-muted focus:px-1"
                        :aria-label="t('Caption')"
                        @change="
                            saveCaption(
                                attachment,
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                    <div
                        class="flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span class="truncate">
                            {{ formatDate(attachment.created_at) }} ·
                            {{ formatBytes(attachment.size) }}
                        </span>
                        <span class="flex shrink-0 items-center">
                            <a
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener"
                                class="rounded-sm p-1 hover:text-foreground"
                                :title="t('Open in a new tab')"
                            >
                                <ExternalLink class="size-3.5" />
                            </a>
                            <a
                                :href="`${attachment.url}?download=1`"
                                class="rounded-sm p-1 hover:text-foreground"
                                :title="t('Download')"
                            >
                                <Download class="size-3.5" />
                            </a>
                            <button
                                type="button"
                                class="rounded-sm p-1 hover:text-destructive"
                                :title="t('Delete')"
                                @click="deleting = attachment"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </span>
                    </div>
                    <NativeSelect
                        v-if="visits.length"
                        size="sm"
                        :model-value="attachment.visit_id"
                        :options="visitOptions"
                        :placeholder="t('Not linked to a visit')"
                        :aria-label="t('Linked visit')"
                        :title="t('Linked visit')"
                        @update:model-value="link(attachment, $event)"
                    />
                </figcaption>
            </figure>
        </div>

        <p
            v-else-if="attachments.length"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            {{ t('No attachments in this selection.') }}
        </p>

        <Dialog
            :open="viewed !== null && viewed !== undefined"
            @update:open="viewerIndex = null"
        >
            <DialogContent
                v-if="viewed"
                class="flex h-[92vh] max-w-[94vw] flex-col gap-3 p-4 sm:max-w-[94vw]"
            >
                <DialogTitle
                    class="truncate pe-8 text-sm font-medium"
                    dir="auto"
                >
                    {{ viewed.caption ?? viewed.original_name }}
                </DialogTitle>
                <div
                    class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted"
                >
                    <img
                        v-if="viewed.is_image"
                        :src="viewed.url"
                        :alt="viewed.caption ?? viewed.original_name"
                        class="max-h-full max-w-full object-contain"
                    />
                    <iframe
                        v-else
                        :src="viewed.url"
                        :title="viewed.original_name"
                        class="size-full"
                    />
                    <template v-if="shown.length > 1">
                        <Button
                            variant="secondary"
                            size="icon"
                            class="absolute start-3 top-1/2 -translate-y-1/2 rounded-full shadow"
                            :aria-label="t('Previous')"
                            @click="step(-1)"
                        >
                            <component
                                :is="isRtl ? ChevronRight : ChevronLeft"
                            />
                        </Button>
                        <Button
                            variant="secondary"
                            size="icon"
                            class="absolute end-3 top-1/2 -translate-y-1/2 rounded-full shadow"
                            :aria-label="t('Next')"
                            @click="step(1)"
                        >
                            <component
                                :is="isRtl ? ChevronLeft : ChevronRight"
                            />
                        </Button>
                    </template>
                </div>
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>
                        {{ (viewerIndex ?? 0) + 1 }} / {{ shown.length }} ·
                        {{ formatDate(viewed.created_at) }} ·
                        {{ formatBytes(viewed.size) }}
                    </span>
                    <span class="flex items-center gap-2">
                        <Button as-child variant="outline" size="sm">
                            <a
                                :href="viewed.url"
                                target="_blank"
                                rel="noopener"
                            >
                                <ExternalLink />{{ t('Open in a new tab') }}
                            </a>
                        </Button>
                        <Button as-child variant="outline" size="sm">
                            <a :href="`${viewed.url}?download=1`">
                                <Download />{{ t('Download') }}
                            </a>
                        </Button>
                    </span>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleting !== null"
            :title="t('Delete this attachment?')"
            :description="deleting?.caption ?? deleting?.original_name"
            :confirm-label="t('Delete')"
            @update:open="deleting = null"
            @confirm="destroy"
        />
    </div>
</template>
