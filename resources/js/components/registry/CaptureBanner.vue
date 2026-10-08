<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useCaptureStatus } from '@/composables/useCaptureStatus';
import { useI18n } from '@/composables/useI18n';
import captureRoutes from '@/routes/captures';
import patientRoutes from '@/routes/patients';

/**
 * Shown on every page while incoming camera images go straight to one patient,
 * so it is always visible who receives them and how to stop it.
 */
const { t } = useI18n();
const { target, refresh } = useCaptureStatus();

function stop() {
    router.delete(captureRoutes.stop.url(), {
        preserveScroll: true,
        onSuccess: () => void refresh(),
    });
}
</script>

<template>
    <div
        v-if="target"
        class="no-print flex items-center gap-3 border-b border-info/30 bg-info/10 px-4 py-2 text-sm text-info"
    >
        <span class="relative flex size-2.5">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-info opacity-60"
            />
            <span class="relative inline-flex size-2.5 rounded-full bg-info" />
        </span>
        <Camera class="size-4" />
        <p class="min-w-0 flex-1">
            {{ t('Camera images now go to') }}
            <Link
                :href="
                    patientRoutes.show(target.patient_id, {
                        query: { tab: 'attachments' },
                    })
                "
                class="font-semibold underline-offset-2 hover:underline"
            >
                <bdi>{{ target.name }}</bdi>
            </Link>
            <span v-if="target.received" class="ms-2 tabular-nums">
                · {{ t(':count received', { count: target.received }) }}
            </span>
        </p>
        <Button size="sm" variant="outline" class="h-7" @click="stop">
            {{ t('Stop') }}
        </Button>
    </div>
</template>
