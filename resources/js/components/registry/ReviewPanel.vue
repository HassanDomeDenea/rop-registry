<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, ClipboardCheck, Plus, RotateCcw, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import patientRoutes from '@/routes/patients';
import reviewItemRoutes from '@/routes/review-items';
import type { ReviewItem } from '@/types';

/**
 * Facts that could not be transcribed with certainty from the paper records.
 * Resolving an item records how it was settled and removes it from the queue.
 */
const props = defineProps<{ patientId: number; items: ReviewItem[] }>();

const { t, formatDateTime } = useI18n();

const resolutions = ref<Record<number, string | number | boolean | null>>({});

const form = useForm<Record<string, string | number | boolean | null>>({
    field: null,
    issue: null,
});

function setResolved(item: ReviewItem, resolved: boolean) {
    router.patch(
        reviewItemRoutes.update.url(item.id),
        { resolved, resolution: resolutions.value[item.id] ?? null },
        { preserveScroll: true },
    );
}

function destroy(item: ReviewItem) {
    router.delete(reviewItemRoutes.destroy.url(item.id), {
        preserveScroll: true,
    });
}

function add() {
    form.post(patientRoutes.reviewItems.store.url(props.patientId), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="space-y-4">
        <form class="flex items-start gap-2" @submit.prevent="add">
            <TextInput
                v-model="form.issue"
                dir="auto"
                :placeholder="t('Add a note that needs checking…')"
                :invalid="!!form.errors.issue"
            />
            <Button
                type="submit"
                variant="outline"
                :disabled="form.processing || !form.issue"
            >
                <Plus />
                {{ t('Add') }}
            </Button>
        </form>

        <ul v-if="items.length" class="space-y-2">
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border bg-card p-4"
                :class="item.resolved_at ? 'opacity-70' : ''"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 space-y-1">
                        <p
                            v-if="item.field"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{ item.field }}
                        </p>
                        <p
                            class="text-sm"
                            :class="item.resolved_at ? 'line-through' : ''"
                            dir="auto"
                        >
                            {{ item.issue }}
                        </p>
                        <p
                            v-if="item.source_reference"
                            class="text-xs text-muted-foreground"
                        >
                            {{ item.source_reference }}
                        </p>
                        <p v-if="item.resolved_at" class="text-xs text-success">
                            {{
                                t('Resolved :date', {
                                    date: formatDateTime(item.resolved_at),
                                })
                            }}
                            <template v-if="item.resolution">
                                — <span dir="auto">{{ item.resolution }}</span>
                            </template>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            v-if="item.resolved_at"
                            variant="ghost"
                            size="sm"
                            @click="setResolved(item, false)"
                        >
                            <RotateCcw />
                            {{ t('Reopen') }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:text-destructive"
                            :title="t('Delete')"
                            @click="destroy(item)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
                <div
                    v-if="!item.resolved_at"
                    class="mt-3 flex items-center gap-2"
                >
                    <TextInput
                        v-model="resolutions[item.id]"
                        dir="auto"
                        class="h-8"
                        :placeholder="t('How was it settled? (optional)')"
                    />
                    <Button size="sm" @click="setResolved(item, true)">
                        <Check />
                        {{ t('Resolve') }}
                    </Button>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="ClipboardCheck"
            :title="t('Nothing to review')"
            :description="
                t(
                    'Uncertain or conflicting facts for this patient appear here.',
                )
            "
        />
    </div>
</template>
