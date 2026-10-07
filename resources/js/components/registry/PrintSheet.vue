<script setup lang="ts">
import { Printer } from '@lucide/vue';
import { onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import type { Clinic } from '@/types';

/** An A4 sheet with the clinic letterhead, shown on screen as it will print. */
defineProps<{ clinic: Clinic; title: string }>();

const { t, formatDate } = useI18n();

// Reports are always printed dark-on-white, whatever theme the application uses.
onMounted(() => document.documentElement.classList.remove('dark'));

function print() {
    window.print();
}
</script>

<template>
    <div class="min-h-svh bg-muted py-8 print:bg-white print:py-0">
        <div
            class="no-print mx-auto mb-4 flex w-[210mm] items-center justify-end gap-2"
        >
            <Button @click="print">
                <Printer />
                {{ t('Print') }}
            </Button>
        </div>

        <article
            class="mx-auto min-h-[297mm] w-[210mm] bg-white px-[16mm] py-[14mm] text-[13px] text-neutral-900 shadow-lg print:min-h-0 print:w-auto print:p-0 print:shadow-none"
        >
            <header
                class="flex items-center gap-4 border-b-2 border-sky-700 pb-4"
            >
                <span
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-sky-700 text-white"
                >
                    <AppLogoIcon class="size-9" />
                </span>
                <div class="flex-1 text-center" dir="auto">
                    <p class="text-xl font-semibold text-sky-800">
                        {{ clinic.name }}
                    </p>
                    <p
                        v-if="clinic.doctor"
                        class="mt-0.5 text-sm text-neutral-600"
                    >
                        {{ clinic.doctor }}
                    </p>
                </div>
                <span class="size-14 shrink-0" />
            </header>

            <h1
                class="my-5 text-center text-base font-semibold tracking-wide uppercase"
            >
                {{ title }}
            </h1>

            <slot />

            <footer
                class="mt-10 flex items-end justify-between border-t pt-3 text-xs text-neutral-500"
            >
                <span dir="auto">
                    {{ clinic.address }}
                    <template v-if="clinic.phone">
                        · <span dir="ltr">{{ clinic.phone }}</span></template
                    >
                </span>
                <span>
                    {{
                        t('Printed :date', {
                            date: formatDate(new Date().toISOString()),
                        })
                    }}
                </span>
            </footer>
        </article>
    </div>
</template>
