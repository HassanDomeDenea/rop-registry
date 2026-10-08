import { onMounted, onUnmounted, ref } from 'vue';
import captureRoutes from '@/routes/captures';

export type CaptureTarget = {
    patient_id: number;
    name: string;
    until: string;
    received: number;
};

/**
 * The camera inbox as every open window sees it: how many images wait, and
 * which patient currently receives incoming images. The state lives on the
 * server, so all windows agree; each one simply asks every few seconds.
 */
const pending = ref(0);
const target = ref<CaptureTarget | null>(null);

const INTERVAL = 4000;

let users = 0;
let timer: number | undefined;

async function refresh() {
    if (document.hidden) {
        return;
    }

    try {
        const response = await fetch(captureRoutes.status.url(), {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const status = (await response.json()) as {
                pending: number;
                target: CaptureTarget | null;
            };

            pending.value = status.pending;
            target.value = status.target;
        }
    } catch {
        // The registry is restarting or offline; the next round tries again.
    }
}

export function useCaptureStatus() {
    onMounted(() => {
        if (users++ === 0) {
            void refresh();
            timer = window.setInterval(refresh, INTERVAL);
            document.addEventListener('visibilitychange', refresh);
        }
    });

    onUnmounted(() => {
        if (--users === 0) {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', refresh);
        }
    });

    return { pending, target, refresh };
}
