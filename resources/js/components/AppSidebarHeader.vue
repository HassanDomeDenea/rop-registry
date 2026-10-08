<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Monitor, Moon, Sun, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import GlobalSearch from '@/components/registry/GlobalSearch.vue';
import ReloadButton from '@/components/ReloadButton.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import patients from '@/routes/patients';
import reminders from '@/routes/reminders';

const page = usePage();
const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const attention = computed(() => page.props.reminderCounts?.attention ?? 0);

const nextAppearance = {
    light: 'dark',
    dark: 'system',
    system: 'light',
} as const;
const appearanceIcon = computed(
    () => ({ light: Sun, dark: Moon, system: Monitor })[appearance.value],
);
</script>

<template>
    <header
        data-slot="app-header"
        class="no-print flex h-14 shrink-0 items-center gap-3 border-b border-border/70 px-4"
    >
        <SidebarTrigger class="-ms-1" />

        <GlobalSearch />

        <div class="ms-auto flex items-center gap-1.5">
            <Button as-child size="sm">
                <Link :href="patients.create()">
                    <UserPlus />
                    {{ t('New patient') }}
                </Link>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :title="t('Theme')"
                :aria-label="t('Theme')"
                @click="updateAppearance(nextAppearance[appearance])"
            >
                <component :is="appearanceIcon" />
            </Button>
            <Button
                as-child
                variant="ghost"
                size="icon"
                class="relative"
                :title="t('Reminders')"
            >
                <Link :href="reminders.index()" :aria-label="t('Reminders')">
                    <Bell />
                    <span
                        v-if="attention > 0"
                        class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-white"
                    >
                        {{ attention }}
                    </span>
                </Link>
            </Button>
            <ReloadButton />
        </div>
    </header>
</template>
