<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Monitor, Moon, Search, Sun, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import patients from '@/routes/patients';
import reminders from '@/routes/reminders';

const page = usePage();
const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const search = ref('');
const attention = computed(() => page.props.reminderCounts?.attention ?? 0);

const nextAppearance = {
    light: 'dark',
    dark: 'system',
    system: 'light',
} as const;
const appearanceIcon = computed(
    () => ({ light: Sun, dark: Moon, system: Monitor })[appearance.value],
);

function submitSearch() {
    router.get(patients.index.url(), { search: search.value });
}
</script>

<template>
    <header
        class="no-print flex h-14 shrink-0 items-center gap-3 border-b border-border/70 px-4"
    >
        <SidebarTrigger class="-ms-1" />

        <form class="relative w-full max-w-sm" @submit.prevent="submitSearch">
            <Search
                class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                v-model="search"
                type="search"
                dir="auto"
                :placeholder="t('Search patients by name, file no. or phone…')"
                class="h-9 w-full rounded-md border border-transparent bg-muted ps-8 pe-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </form>

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
        </div>
    </header>
</template>
