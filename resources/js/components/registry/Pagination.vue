<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { Paginated } from '@/types';

const props = defineProps<{ paginator: Paginated<unknown> }>();

const { isRtl, t } = useI18n();

// Laravel wraps the numbered links with a "previous" and a "next" link.
const previous = computed(() => props.paginator.links[0]);
const next = computed(() => props.paginator.links.at(-1));
const pages = computed(() => props.paginator.links.slice(1, -1));
</script>

<template>
    <nav
        class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm"
        :aria-label="t('Pagination')"
    >
        <p class="text-muted-foreground tabular-nums">
            {{
                t('Showing :from–:to of :total', {
                    from: paginator.from ?? 0,
                    to: paginator.to ?? 0,
                    total: paginator.total,
                })
            }}
        </p>
        <div v-if="paginator.last_page > 1" class="flex items-center gap-1">
            <component
                :is="previous?.url ? Link : 'span'"
                :href="previous?.url ?? undefined"
                preserve-scroll
                class="inline-flex size-8 items-center justify-center rounded-md border bg-card"
                :class="
                    previous?.url
                        ? 'hover:bg-accent'
                        : 'pointer-events-none opacity-40'
                "
                :aria-label="t('Previous')"
            >
                <component
                    :is="isRtl ? ChevronRight : ChevronLeft"
                    class="size-4"
                />
            </component>
            <template v-for="(page, index) in pages" :key="index">
                <Link
                    v-if="page.url"
                    :href="page.url"
                    preserve-scroll
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 tabular-nums"
                    :class="
                        page.active
                            ? 'bg-primary font-medium text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                >
                    {{ page.label }}
                </Link>
                <span v-else class="px-1 text-muted-foreground">…</span>
            </template>
            <component
                :is="next?.url ? Link : 'span'"
                :href="next?.url ?? undefined"
                preserve-scroll
                class="inline-flex size-8 items-center justify-center rounded-md border bg-card"
                :class="
                    next?.url
                        ? 'hover:bg-accent'
                        : 'pointer-events-none opacity-40'
                "
                :aria-label="t('Next')"
            >
                <component
                    :is="isRtl ? ChevronLeft : ChevronRight"
                    class="size-4"
                />
            </component>
        </div>
    </nav>
</template>
