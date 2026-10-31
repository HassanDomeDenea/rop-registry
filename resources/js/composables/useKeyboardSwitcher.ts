import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

/**
 * Switches the computer's keyboard to the language of the field being typed in, through
 * HddKeyboardSwitcher, a small Windows program listening on this computer. A field asks for a
 * language with data-keyboard="ar" or "en"; leaving it puts the previous keyboard back. Without
 * the program, as on a phone or another computer, nothing happens.
 */
type KeyboardLanguage = 'ar' | 'en';

export type KeyboardSwitcherStatus =
    | 'idle'
    | 'connecting'
    | 'connected'
    | 'unavailable';

const SWITCHER_URL = 'ws://127.0.0.1:8123/kbd';
const STORAGE_KEY = 'keyboard-switcher';

/** Long enough to tab from one Arabic field to the next without switching back in between. */
const RESTORE_DELAY = 150;

/** How long to leave a switcher that did not answer alone before asking again. */
const RETRY_AFTER = 30_000;

const enabled = ref(readEnabled());
const status = ref<KeyboardSwitcherStatus>('idle');

let socket: WebSocket | null = null;
let failedAt = 0;
let restoreTimer: number | undefined;

/** The language this window asked the switcher for and has not restored yet. */
let switched: KeyboardLanguage | null = null;

function readEnabled(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

function languageOf(target: EventTarget | null): KeyboardLanguage | null {
    if (
        !(target instanceof HTMLElement) ||
        !target.matches('input, textarea, [contenteditable]')
    ) {
        return null;
    }

    const language =
        target.closest<HTMLElement>('[data-keyboard]')?.dataset.keyboard;

    return language === 'ar' || language === 'en' ? language : null;
}

function connect(retryNow = false): void {
    if (!enabled.value || socket) {
        return;
    }

    if (!retryNow && Date.now() - failedAt < RETRY_AFTER) {
        return;
    }

    const connection = new WebSocket(SWITCHER_URL);

    socket = connection;
    status.value = 'connecting';

    connection.addEventListener('open', () => {
        status.value = 'connected';

        // The field was focused while connecting: catch up.
        if (switched) {
            connection.send(`switch:${switched}`);
        }
    });

    connection.addEventListener('close', () => {
        if (status.value !== 'connected') {
            failedAt = Date.now();
        }

        socket = null;
        status.value = status.value === 'connected' ? 'idle' : 'unavailable';
    });
}

function send(message: string): void {
    if (socket?.readyState === WebSocket.OPEN) {
        socket.send(message);
    } else {
        connect();
    }
}

function restore(): void {
    window.clearTimeout(restoreTimer);

    if (switched) {
        switched = null;
        send('restore');
    }
}

function scheduleRestore(): void {
    window.clearTimeout(restoreTimer);

    restoreTimer = window.setTimeout(() => {
        // Focus may have landed on another field of the same language meanwhile.
        if (languageOf(document.activeElement) !== switched) {
            restore();
        }
    }, RESTORE_DELAY);
}

function onFocusIn(event: FocusEvent): void {
    const language = languageOf(event.target);

    if (!enabled.value || !language) {
        return;
    }

    window.clearTimeout(restoreTimer);

    if (switched !== language) {
        switched = language;
        send(`switch:${language}`);
    }
}

function onFocusOut(): void {
    if (switched) {
        scheduleRestore();
    }
}

/** Start following the focus. Called once when the application starts. */
export function initializeKeyboardSwitcher(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.addEventListener('focusin', onFocusIn);
    document.addEventListener('focusout', onFocusOut);

    // A page change can remove the focused field without a focusout.
    router.on('navigate', () => {
        if (switched) {
            scheduleRestore();
        }
    });
}

export function useKeyboardSwitcher() {
    function setEnabled(value: boolean): void {
        enabled.value = value;

        try {
            localStorage.setItem(STORAGE_KEY, value ? 'on' : 'off');
        } catch {
            // Storage is blocked: the choice lasts until the page is reloaded.
        }

        if (value) {
            connect(true);
        } else {
            restore();
            socket?.close();
        }
    }

    /** Find out now whether the switcher is running, instead of at the next Arabic field. */
    function checkConnection(): void {
        connect(true);
    }

    onMounted(checkConnection);

    return { enabled, status, setEnabled, checkConnection };
}
