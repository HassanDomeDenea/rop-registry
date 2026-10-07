<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, Palette, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useI18n } from '@/composables/useI18n';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { t } = useI18n();
const { isCurrentOrParentUrl } = useCurrentUrl();

const items = computed<NavItem[]>(() => [
    {
        title: t('Appearance & language'),
        href: editAppearance(),
        icon: Palette,
    },
    { title: t('Profile'), href: editProfile(), icon: UserRound },
    { title: t('Password'), href: editSecurity(), icon: KeyRound },
]);
</script>

<template>
    <div class="space-y-6">
        <PageHeader
            :title="t('Settings')"
            :description="
                t('Appearance, language and the administrator account')
            "
        />

        <div class="flex gap-8">
            <nav
                class="flex w-56 shrink-0 flex-col gap-1"
                :aria-label="t('Settings')"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'bg-accent font-medium text-accent-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>
            </nav>

            <div class="max-w-2xl flex-1 space-y-6">
                <slot />
            </div>
        </div>
    </div>
</template>
