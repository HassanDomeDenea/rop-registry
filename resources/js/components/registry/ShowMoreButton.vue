<script setup lang="ts">
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

/**
 * Unfolds the rest of a long list, or folds it again. Rendered only when entries are folded.
 */
defineProps<{ expanded: boolean; hiddenCount: number }>();

defineEmits<{ toggle: [] }>();

const { t, formatNumber } = useI18n();
</script>

<template>
    <Button
        v-if="hiddenCount > 0"
        variant="ghost"
        size="sm"
        class="no-print text-muted-foreground"
        @click="$emit('toggle')"
    >
        <template v-if="expanded">
            <ChevronUp />
            {{ t('Show fewer') }}
        </template>
        <template v-else>
            <ChevronDown />
            {{ t('Show :count more', { count: formatNumber(hiddenCount) }) }}
        </template>
    </Button>
</template>
