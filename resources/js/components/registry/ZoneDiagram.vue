<script setup lang="ts">
import { computed } from 'vue';
import type { Eye } from '@/types';

/**
 * A schematic fundus with the three ROP zones, as printed on the paper examination
 * form. The optic disc sits on the nasal side, so the two eyes mirror each other.
 * Clicking a zone selects it; clicking the selected zone clears it.
 */
const model = defineModel<string | number | boolean | null>();

const props = defineProps<{ eye: Eye; readonly?: boolean }>();

// Viewed by the examiner, the nose is to the right of the right eye.
const discX = computed(() => (props.eye === 'right' ? 122 : 78));

const zones = computed(() => [
    { value: 'zone_3', label: 'III', labelX: props.eye === 'right' ? 22 : 178 },
    { value: 'zone_2', label: 'II', labelX: props.eye === 'right' ? 62 : 138 },
    { value: 'zone_1', label: 'I', labelX: discX.value },
]);

function isActive(zone: string) {
    return (
        model.value === zone ||
        (zone === 'zone_2' && model.value === 'posterior_zone_2')
    );
}

function select(zone: string) {
    if (!props.readonly) {
        model.value = model.value === zone ? null : zone;
    }
}

function fill(zone: string) {
    return isActive(zone) ? 'var(--primary)' : 'var(--card)';
}
</script>

<template>
    <svg
        viewBox="0 0 200 140"
        class="h-auto w-full max-w-64"
        role="img"
        :aria-label="eye === 'right' ? 'Right eye zones' : 'Left eye zones'"
    >
        <defs>
            <clipPath :id="`fundus-${eye}`">
                <ellipse cx="100" cy="70" rx="94" ry="64" />
            </clipPath>
        </defs>

        <g
            :clip-path="`url(#fundus-${eye})`"
            :class="readonly ? '' : 'cursor-pointer'"
        >
            <!-- Zone III: the remaining temporal crescent -->
            <rect
                width="200"
                height="140"
                :fill="fill('zone_3')"
                :fill-opacity="isActive('zone_3') ? 0.85 : 1"
                @click="select('zone_3')"
            />
            <!-- Zone II: centred on the disc, reaching the nasal ora serrata -->
            <circle
                :cx="discX"
                cy="70"
                r="72"
                :fill="fill('zone_2')"
                :fill-opacity="isActive('zone_2') ? 0.85 : 1"
                stroke="var(--muted-foreground)"
                stroke-opacity="0.55"
                stroke-width="1.5"
                stroke-dasharray="4 3"
                @click="select('zone_2')"
            />
            <!-- Zone I: twice the disc-to-fovea distance -->
            <circle
                :cx="discX"
                cy="70"
                r="30"
                :fill="fill('zone_1')"
                :fill-opacity="isActive('zone_1') ? 0.85 : 1"
                stroke="var(--muted-foreground)"
                stroke-opacity="0.55"
                stroke-width="1.5"
                stroke-dasharray="4 3"
                @click="select('zone_1')"
            />
        </g>

        <ellipse
            cx="100"
            cy="70"
            rx="94"
            ry="64"
            fill="none"
            stroke="var(--muted-foreground)"
            stroke-opacity="0.5"
            stroke-width="1.5"
        />

        <!-- Optic disc and fovea -->
        <circle
            :cx="discX"
            cy="70"
            r="5"
            fill="var(--warning)"
            fill-opacity="0.75"
            class="pointer-events-none"
        />
        <circle
            :cx="eye === 'right' ? discX - 15 : discX + 15"
            cy="70"
            r="2.5"
            fill="var(--destructive)"
            fill-opacity="0.6"
            class="pointer-events-none"
        />

        <text
            v-for="zone in zones"
            :key="zone.value"
            :x="zone.labelX"
            :y="zone.value === 'zone_1' ? 54 : 74"
            text-anchor="middle"
            class="pointer-events-none text-[11px] font-semibold select-none"
            :fill="
                isActive(zone.value)
                    ? 'var(--primary-foreground)'
                    : 'var(--muted-foreground)'
            "
        >
            {{ zone.label }}
        </text>
    </svg>
</template>
