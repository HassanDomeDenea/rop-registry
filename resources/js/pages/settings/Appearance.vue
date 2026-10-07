<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Languages, Monitor, Moon, Palette, Sun } from '@lucide/vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import { update } from '@/routes/preferences';
import type { Appearance } from '@/types';

const page = usePage();
const { t, locale } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const themes: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'Automatic' },
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
</template>
