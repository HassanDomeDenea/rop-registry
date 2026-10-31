<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Crop,
    Loader2,
    RotateCcw,
    RotateCw,
    ScanLine,
    SlidersHorizontal,
    Undo2,
} from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { detectPage } from '@/lib/detectPage';
import type { Adjustments, Point, Quad } from '@/lib/scanImage';
import {
    adjust,
    canvasToBlob,
    createCanvas,
    flatten,
    fullQuad,
    isConvexQuad,
    renderFrame,
    turnQuadClockwise,
    turnQuadCounterclockwise,
} from '@/lib/scanImage';
import attachmentRoutes from '@/routes/attachments';
import type { Attachment } from '@/types';
import type { FlashToast } from '@/types/ui';

/**
 * Crops, straightens and cleans up a picture like a document scanner. The work happens in
 * the browser; the server only receives the finished picture and keeps the first version.
 */
const props = defineProps<{
    attachment: Attachment;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const PREVIEW_EDGE = 1400;
const RESULT_EDGE = 5000;
const JPEG_QUALITY = 0.92;
const STAGE_MARGIN = 16;

const NEUTRAL: Adjustments = {
    whiten: 0,
    brightness: 0,
    contrast: 0,
    mode: 'color',
};

const presets = computed<{ label: string; values: Adjustments }[]>(() => [
    { label: t('Original colours'), values: NEUTRAL },
    {
        label: t('Brighter'),
        values: { whiten: 0, brightness: 8, contrast: 20, mode: 'color' },
    },
    {
        label: t('White paper'),
        values: { whiten: 100, brightness: 0, contrast: 20, mode: 'color' },
    },
    {
        label: t('Grey'),
        values: { whiten: 100, brightness: 0, contrast: 20, mode: 'gray' },
    },
    {
        label: t('Black and white'),
        values: { whiten: 100, brightness: 0, contrast: 0, mode: 'bw' },
    },
]);

const sliders = computed<
    {
        key: 'whiten' | 'brightness' | 'contrast';
        label: string;
        min: number;
    }[]
>(() => [
    { key: 'whiten', label: t('Whiten paper'), min: 0 },
    { key: 'brightness', label: t('Brightness'), min: -100 },
    { key: 'contrast', label: t('Contrast'), min: -100 },
]);

const tool = ref<'crop' | 'enhance'>('crop');
const quarterTurns = ref(0);
const straighten = ref(0);
const quad = ref<Quad>(fullQuad());
const adjustments = reactive<Adjustments>({ ...NEUTRAL });

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const confirmingRestore = ref(false);
const detecting = ref(false);
const detectionNotice = ref<string | null>(null);

const stage = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const overlay = ref<SVGSVGElement | null>(null);
const { width: stageWidth, height: stageHeight } = useElementSize(stage);
const pictureSize = ref({ width: 0, height: 0 });

const fit = computed(() => {
    const { width, height } = pictureSize.value;

    if (!width || !height || !stageWidth.value || !stageHeight.value) {
        return { width: 0, height: 0 };
    }

    // Leave room around the picture so the handles on its edges stay in full view.
    const margin = tool.value === 'crop' ? STAGE_MARGIN : 0;
    const scale = Math.min(
        (stageWidth.value - margin * 2) / width,
        (stageHeight.value - margin * 2) / height,
    );

    return { width: width * scale, height: height * scale };
});

const corners = computed(() =>
    quad.value.map(({ x, y }) => ({
        x: x * fit.value.width,
        y: y * fit.value.height,
    })),
);

const edges = computed(() =>
    corners.value.map((corner, index) => {
        const next = corners.value[(index + 1) % 4];

        return { x: (corner.x + next.x) / 2, y: (corner.y + next.y) / 2 };
    }),
);

const outline = computed(() =>
    corners.value.map(({ x, y }) => `${x},${y}`).join(' '),
);

const shade = computed(() => {
    const { width, height } = fit.value;
    const [first, ...others] = corners.value;

    return (
        `M0 0H${width}V${height}H0Z` +
        `M${first.x} ${first.y}` +
        others.map(({ x, y }) => `L${x} ${y}`).join('') +
        'Z'
    );
});

const isChanged = computed(
    () =>
        quarterTurns.value !== 0 ||
        straighten.value !== 0 ||
        JSON.stringify(quad.value) !== JSON.stringify(fullQuad()) ||
        adjustments.whiten !== 0 ||
        adjustments.brightness !== 0 ||
        adjustments.contrast !== 0 ||
        adjustments.mode !== 'color',
);

const totalDegrees = computed(() => quarterTurns.value * 90 + straighten.value);

let picture: HTMLImageElement | null = null;
let frame: HTMLCanvasElement | null = null;
let frameDegrees: number | null = null;
let flattened: HTMLCanvasElement | null = null;
let flattenedKey: string | null = null;
let pendingDraw = 0;

/** Show the picture at preview size, reusing the steps whose inputs did not change. */
function draw() {
    if (!picture || !canvas.value) {
        return;
    }

    if (!frame || frameDegrees !== totalDegrees.value) {
        frame = renderFrame(
            picture,
            picture.naturalWidth,
            picture.naturalHeight,
            totalDegrees.value,
            PREVIEW_EDGE,
        );
        frameDegrees = totalDegrees.value;
        flattenedKey = null;
    }

    let shown = frame;

    if (tool.value === 'enhance') {
        const key = JSON.stringify(quad.value);

        if (!flattened || flattenedKey !== key) {
            flattened = flatten(frame, quad.value);
            flattenedKey = key;
        }

        shown = createCanvas(flattened.width, flattened.height);
        shown.getContext('2d')?.drawImage(flattened, 0, 0);
        adjust(shown, adjustments);
    }

    canvas.value.width = shown.width;
    canvas.value.height = shown.height;
    canvas.value.getContext('2d')?.drawImage(shown, 0, 0);
    pictureSize.value = { width: shown.width, height: shown.height };
}

function scheduleDraw() {
    cancelAnimationFrame(pendingDraw);
    pendingDraw = requestAnimationFrame(draw);
}

watch([tool, totalDegrees, quad, adjustments], scheduleDraw, { deep: true });

onMounted(async () => {
    const image = new Image();

    image.src = props.attachment.url;

    try {
        await image.decode();
        picture = image;
        draw();
    } catch {
        error.value = t('The picture could not be opened.');
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => cancelAnimationFrame(pendingDraw));

function turn(direction: 1 | -1) {
    quarterTurns.value = (quarterTurns.value + direction + 4) % 4;
    quad.value =
        direction === 1
            ? turnQuadClockwise(quad.value)
            : turnQuadCounterclockwise(quad.value);
}

/** Move the handles onto the page. They stay adjustable, as detection can be wrong. */
async function detect() {
    detecting.value = true;
    detectionNotice.value = null;

    // Let the busy state show before the browser is occupied with loading and detecting.
    await new Promise((resolve) => setTimeout(resolve, 30));

    try {
        draw();

        const found = frame ? await detectPage(frame) : null;

        if (found) {
            quad.value = found;
        } else {
            detectionNotice.value = t(
                'No page found. Place the corners by hand.',
            );
        }
    } catch {
        detectionNotice.value = t('Page detection could not be started.');
    } finally {
        detecting.value = false;
    }
}

function applyPreset(values: Adjustments) {
    Object.assign(adjustments, values);
}

function reset() {
    quarterTurns.value = 0;
    straighten.value = 0;
    quad.value = fullQuad();
    Object.assign(adjustments, NEUTRAL);
}

type Drag =
    | { kind: 'corner'; index: number }
    | { kind: 'edge'; index: number; last: Point };

let drag: Drag | null = null;

function pointerPosition(event: PointerEvent): Point {
    const rect = overlay.value!.getBoundingClientRect();

    return {
        x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
        y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
    };
}

function startDrag(event: PointerEvent, kind: Drag['kind'], index: number) {
    (event.currentTarget as Element).setPointerCapture(event.pointerId);

    drag =
        kind === 'corner'
            ? { kind, index }
            : { kind, index, last: pointerPosition(event) };
}

function moveDrag(event: PointerEvent) {
    if (!drag) {
        return;
    }

    const position = pointerPosition(event);
    const next = quad.value.map((point) => ({ ...point })) as Quad;

    if (drag.kind === 'corner') {
        next[drag.index] = position;
    } else {
        // Top and bottom edges move up and down, the side edges left and right, which
        // keeps a plain rectangular crop rectangular.
        const isHorizontal = drag.index % 2 === 0;
        const clamp = (value: number) => Math.min(1, Math.max(0, value));

        for (const index of [drag.index, (drag.index + 1) % 4]) {
            if (isHorizontal) {
                next[index].y = clamp(next[index].y + position.y - drag.last.y);
            } else {
                next[index].x = clamp(next[index].x + position.x - drag.last.x);
            }
        }

        drag.last = position;
    }

    if (isConvexQuad(next)) {
        quad.value = next;
    }
}

function endDrag() {
    drag = null;
}

async function save() {
    if (!picture) {
        return;
    }

    saving.value = true;
    error.value = null;

    // Let the busy state show before the browser is occupied with the full size picture.
    await new Promise((resolve) => setTimeout(resolve, 30));

    const type = /^image\/(jpeg|webp)$/.test(props.attachment.mime_type)
        ? 'image/jpeg'
        : 'image/png';
    let blob: Blob;

    try {
        const result = flatten(
            renderFrame(
                picture,
                picture.naturalWidth,
                picture.naturalHeight,
                totalDegrees.value,
                RESULT_EDGE,
            ),
            quad.value,
        );

        adjust(result, adjustments);
        blob = await canvasToBlob(result, type, JPEG_QUALITY);
    } catch {
        error.value = t('The picture could not be processed.');
        saving.value = false;

        return;
    }

    router.post(
        attachmentRoutes.image.update.url(props.attachment.id),
        {
            image: new File(
                [blob],
                type === 'image/jpeg' ? 'edited.jpg' : 'edited.png',
                { type },
            ),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            ...reportFailures,
            onSuccess: (page) => {
                // A rejected request comes back as a normal page with an error toast.
                if (
                    (page.flash.toast as FlashToast | undefined)?.type !==
                    'error'
                ) {
                    emit('close');
                }
            },
            onError: (errors) =>
                (error.value = Object.values(errors)[0] ?? null),
            onFinish: () => (saving.value = false),
        },
    );
}

function restoreOriginal() {
    router.delete(attachmentRoutes.image.restore.url(props.attachment.id), {
        preserveScroll: true,
        preserveState: true,
        ...reportFailures,
        onSuccess: () => emit('close'),
        onFinish: () => (confirmingRestore.value = false),
    });
}

/** Keep the editor open and say why, so a failed request never looks like nothing happened. */
const reportFailures = {
    onHttpException: (response: { status: number }) => {
        error.value = t('The picture could not be saved (error :status).', {
            status: response.status,
        });

        return false;
    },
    onNetworkError: () => {
        error.value = t(
            'The picture could not be saved. Check the connection and try again.',
        );

        return false;
    },
};

/** Keep the work from being lost to a stray click or key press. */
function guardUnsaved(event: Event) {
    if (isChanged.value || saving.value) {
        event.preventDefault();
    }
}
</script>

<template>
    <Dialog :open="true" @update:open="emit('close')">
        <DialogContent
            class="flex h-[92vh] max-w-[94vw] flex-col gap-3 p-4 sm:max-w-[94vw]"
            :show-close-button="false"
            @interact-outside="guardUnsaved"
            @escape-key-down="guardUnsaved"
        >
            <DialogTitle class="truncate text-sm font-medium" dir="auto">
                {{ t('Edit picture') }} ·
                {{ attachment.caption ?? attachment.original_name }}
            </DialogTitle>

            <div class="flex min-h-0 flex-1 flex-col gap-3 md:flex-row">
                <div
                    ref="stage"
                    dir="ltr"
                    class="relative flex min-h-48 min-w-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted"
                >
                    <Loader2
                        v-if="loading"
                        class="size-6 animate-spin text-muted-foreground"
                    />
                    <div
                        class="absolute"
                        :style="{
                            width: `${fit.width}px`,
                            height: `${fit.height}px`,
                        }"
                    >
                        <canvas ref="canvas" class="size-full" />
                        <svg
                            v-if="tool === 'crop' && fit.width"
                            ref="overlay"
                            class="absolute inset-0 size-full touch-none overflow-visible"
                            :viewBox="`0 0 ${fit.width} ${fit.height}`"
                        >
                            <path
                                :d="shade"
                                fill="black"
                                fill-opacity="0.45"
                                fill-rule="evenodd"
                            />
                            <polygon
                                :points="outline"
                                fill="none"
                                stroke="white"
                                stroke-width="2"
                            />
                            <g
                                v-for="(edge, index) in edges"
                                :key="`edge-${index}`"
                                class="cursor-move"
                                data-handle="edge"
                                @pointerdown.prevent="
                                    startDrag($event, 'edge', index)
                                "
                                @pointermove="moveDrag"
                                @pointerup="endDrag"
                                @pointercancel="endDrag"
                            >
                                <circle
                                    :cx="edge.x"
                                    :cy="edge.y"
                                    r="18"
                                    fill="transparent"
                                />
                                <circle
                                    :cx="edge.x"
                                    :cy="edge.y"
                                    r="5"
                                    fill="white"
                                    stroke="black"
                                    stroke-opacity="0.5"
                                />
                            </g>
                            <g
                                v-for="(corner, index) in corners"
                                :key="`corner-${index}`"
                                class="cursor-grab active:cursor-grabbing"
                                data-handle="corner"
                                @pointerdown.prevent="
                                    startDrag($event, 'corner', index)
                                "
                                @pointermove="moveDrag"
                                @pointerup="endDrag"
                                @pointercancel="endDrag"
                            >
                                <circle
                                    :cx="corner.x"
                                    :cy="corner.y"
                                    r="22"
                                    fill="transparent"
                                />
                                <circle
                                    :cx="corner.x"
                                    :cy="corner.y"
                                    r="9"
                                    fill="var(--primary)"
                                    stroke="white"
                                    stroke-width="2"
                                />
                            </g>
                        </svg>
                    </div>
                </div>

                <aside
                    class="flex shrink-0 flex-col gap-4 overflow-y-auto max-md:max-h-[45%] md:w-72"
                >
                    <div class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                        <Button
                            size="sm"
                            :variant="tool === 'crop' ? 'default' : 'ghost'"
                            @click="tool = 'crop'"
                        >
                            <Crop />{{ t('Crop') }}
                        </Button>
                        <Button
                            size="sm"
                            :variant="tool === 'enhance' ? 'default' : 'ghost'"
                            @click="tool = 'enhance'"
                        >
                            <SlidersHorizontal />{{ t('Enhance') }}
                        </Button>
                    </div>

                    <template v-if="tool === 'crop'">
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    'Drag the round handles onto the corners of the page. A slanted page is straightened automatically.',
                                )
                            }}
                        </p>
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="loading || detecting"
                            @click="detect"
                        >
                            <Loader2 v-if="detecting" class="animate-spin" />
                            <ScanLine v-else />
                            {{ t('Detect page') }}
                        </Button>
                        <p
                            v-if="detectionNotice"
                            class="text-xs text-muted-foreground"
                            data-detection-notice
                        >
                            {{ detectionNotice }}
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="turn(-1)"
                            >
                                <RotateCcw />{{ t('Rotate left') }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="turn(1)"
                            >
                                <RotateCw />{{ t('Rotate right') }}
                            </Button>
                        </div>
                        <label class="block space-y-1.5 text-sm">
                            <span class="flex justify-between">
                                {{ t('Fine rotation') }}
                                <span
                                    class="text-muted-foreground tabular-nums"
                                    dir="ltr"
                                    >{{ straighten }}°</span
                                >
                            </span>
                            <input
                                v-model.number="straighten"
                                type="range"
                                min="-15"
                                max="15"
                                step="0.5"
                                class="w-full accent-primary"
                            />
                        </label>
                    </template>

                    <template v-else>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-for="preset in presets"
                                :key="preset.label"
                                variant="outline"
                                size="sm"
                                @click="applyPreset(preset.values)"
                            >
                                {{ preset.label }}
                            </Button>
                        </div>
                        <label
                            v-for="slider in sliders"
                            :key="slider.key"
                            class="block space-y-1.5 text-sm"
                        >
                            <span class="flex justify-between">
                                {{ slider.label }}
                                <span
                                    class="text-muted-foreground tabular-nums"
                                    dir="ltr"
                                    >{{ adjustments[slider.key] }}</span
                                >
                            </span>
                            <input
                                v-model.number="adjustments[slider.key]"
                                type="range"
                                :min="slider.min"
                                max="100"
                                class="w-full accent-primary"
                            />
                        </label>
                    </template>

                    <p class="mt-auto text-xs text-muted-foreground">
                        {{
                            t(
                                'The picture as first received is kept, so you can return to it at any time.',
                            )
                        }}
                    </p>
                </aside>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="!isChanged || saving"
                        @click="reset"
                    >
                        <Undo2 />{{ t('Reset') }}
                    </Button>
                    <Button
                        v-if="attachment.is_edited"
                        variant="ghost"
                        size="sm"
                        :disabled="saving"
                        @click="confirmingRestore = true"
                    >
                        {{ t('Restore original') }}
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="saving"
                        @click="emit('close')"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button
                        size="sm"
                        :disabled="!isChanged || saving || loading"
                        @click="save"
                    >
                        <Loader2 v-if="saving" class="animate-spin" />
                        {{ t('Save') }}
                    </Button>
                </div>
            </div>
        </DialogContent>

        <ConfirmDialog
            :open="confirmingRestore"
            :title="t('Restore the original picture?')"
            :description="
                t('The edits made to this picture will no longer be shown.')
            "
            :confirm-label="t('Restore original')"
            :destructive="false"
            @update:open="confirmingRestore = false"
            @confirm="restoreOriginal"
        />
    </Dialog>
</template>
