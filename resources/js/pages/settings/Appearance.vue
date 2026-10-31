<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    Keyboard,
    Languages,
    Monitor,
    Moon,
    Palette,
    Printer,
    Sparkles,
    Sun,
} from '@lucide/vue';
import { computed } from 'vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import { useKeyboardSwitcher } from '@/composables/useKeyboardSwitcher';
import { update } from '@/routes/preferences';
import type { Appearance } from '@/types';

const props = defineProps<{ printLocale: string | null }>();

const page = usePage();
const { t, locale } = useI18n();

const printLanguages = computed(() => [
    { code: null, label: t('Same as the interface') },
    ...page.props.locales,
]);

function changePrintLocale(code: string | null) {
    if (code === props.printLocale) {
        return;
    }

    router.put(update.url(), { print_locale: code }, { preserveScroll: true });
}
const { appearance, updateAppearance, colorful, updateColorful } =
    useAppearance();

const keyboard = useKeyboardSwitcher();

const keyboardChoices = [
    { value: true, label: 'Switch automatically' },
    { value: false, label: 'Off' },
];

const keyboardStatus = computed(() => {
    switch (keyboard.status.value) {
        case 'connected':
            return {
                text: 'HddKeyboardSwitcher is running on this computer.',
                dot: 'bg-emerald-500',
            };
        case 'unavailable':
            return {
                text: 'HddKeyboardSwitcher is not running on this computer.',
                dot: 'bg-amber-500',
            };
        default:
            return {
                text: 'Looking for HddKeyboardSwitcher…',
                dot: 'bg-muted-foreground animate-pulse',
            };
    }
});

const themes: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'Automatic' },
];

const colourStyles = [
    {
        value: false,
        label: 'Calm',
        swatch: 'bg-linear-to-r from-slate-300 via-sky-200 to-slate-200',
    },
    {
        value: true,
        label: 'Colourful',
        swatch: 'bg-linear-to-r from-indigo-500 via-fuchsia-500 to-amber-400',
    },
];

function changeLocale(code: string) {
    if (code === locale.value) {
        return;
    }

    // A full reload applies the text direction and fonts of the new language.
    router.put(
        update.url(),
        { locale: code },
        { onSuccess: () => window.location.reload() },
    );
}
</script>

<template>
    <Head :title="t('Appearance & language')" />

    <SectionCard
        :title="t('Theme')"
        :description="t('Automatic follows the theme of this computer')"
        :icon="Palette"
    >
        <div class="grid grid-cols-3 gap-3">
            <button
                v-for="theme in themes"
                :key="theme.value"
                type="button"
                class="flex flex-col items-center gap-2 rounded-lg border px-4 py-5 text-sm transition-colors"
                :class="
                    appearance === theme.value
                        ? 'border-primary bg-accent font-medium text-accent-foreground ring-1 ring-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="appearance === theme.value"
                @click="updateAppearance(theme.value)"
            >
                <component :is="theme.icon" class="size-5" />
                {{ t(theme.label) }}
            </button>
        </div>
    </SectionCard>

    <SectionCard
        :title="t('Colours')"
        :description="
            t(
                'Colourful paints panels, buttons and fields with colours and gradients. It follows the light or dark theme.',
            )
        "
        :icon="Sparkles"
    >
        <div class="grid grid-cols-2 gap-3">
            <button
                v-for="style in colourStyles"
                :key="style.label"
                type="button"
                class="flex flex-col gap-3 rounded-lg border px-4 py-3.5 text-sm transition-colors"
                :class="
                    colorful === style.value
                        ? 'border-primary bg-accent font-medium text-accent-foreground ring-1 ring-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="colorful === style.value"
                @click="updateColorful(style.value)"
            >
                <span class="h-8 w-full rounded-md" :class="style.swatch" />
                <span class="flex w-full items-center justify-between">
                    {{ t(style.label) }}
                    <Check v-if="colorful === style.value" class="size-4" />
                </span>
            </button>
        </div>
    </SectionCard>

    <SectionCard
        :title="t('Language')"
        :description="t('Arabic switches the interface to right-to-left')"
        :icon="Languages"
    >
        <div class="grid grid-cols-2 gap-3">
            <button
                v-for="language in page.props.locales"
                :key="language.code"
                type="button"
                class="flex items-center justify-between rounded-lg border px-4 py-3.5 text-sm transition-colors"
                :class="
                    locale === language.code
                        ? 'border-primary bg-accent font-medium text-accent-foreground ring-1 ring-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="locale === language.code"
                @click="changeLocale(language.code)"
            >
                {{ language.label }}
                <Check v-if="locale === language.code" class="size-4" />
            </button>
        </div>
    </SectionCard>

    <SectionCard
        :title="t('Print language')"
        :description="
            t(
                'The language of printed reports. Examination findings are always printed in English.',
            )
        "
        :icon="Printer"
    >
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <button
                v-for="language in printLanguages"
                :key="language.code ?? 'interface'"
                type="button"
                class="flex items-center justify-between rounded-lg border px-4 py-3.5 text-sm transition-colors"
                :class="
                    printLocale === language.code
                        ? 'border-primary bg-accent font-medium text-accent-foreground ring-1 ring-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="printLocale === language.code"
                @click="changePrintLocale(language.code)"
            >
                {{ language.label }}
                <Check v-if="printLocale === language.code" class="size-4" />
            </button>
        </div>
    </SectionCard>

    <SectionCard
        :title="t('Keyboard')"
        :description="
            t(
                'Switches the keyboard of this computer to Arabic in Arabic fields, such as names, and back when you leave them. Needs HddKeyboardSwitcher running on this computer; the choice applies to this browser only.',
            )
        "
        :icon="Keyboard"
    >
        <div class="grid grid-cols-2 gap-3">
            <button
                v-for="choice in keyboardChoices"
                :key="choice.label"
                type="button"
                class="flex items-center justify-between rounded-lg border px-4 py-3.5 text-sm transition-colors"
                :class="
                    keyboard.enabled.value === choice.value
                        ? 'border-primary bg-accent font-medium text-accent-foreground ring-1 ring-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="keyboard.enabled.value === choice.value"
                @click="keyboard.setEnabled(choice.value)"
            >
                {{ t(choice.label) }}
                <Check
                    v-if="keyboard.enabled.value === choice.value"
                    class="size-4"
                />
            </button>
        </div>
        <div
            v-if="keyboard.enabled.value"
            class="mt-3 flex items-center gap-2 text-sm text-muted-foreground"
        >
            <span
                class="size-2 shrink-0 rounded-full"
                :class="keyboardStatus.dot"
            />
            {{ t(keyboardStatus.text) }}
            <button
                v-if="keyboard.status.value === 'unavailable'"
                type="button"
                class="font-medium text-primary hover:underline"
                @click="keyboard.checkConnection()"
            >
                {{ t('Check again') }}
            </button>
        </div>
    </SectionCard>
</template>
