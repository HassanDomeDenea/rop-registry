import { router } from '@inertiajs/vue3';

/**
 * Keep the language and text direction of the document in step with the page.
 * The server only renders them on a full load, so a visit that changes the
 * language, such as signing in, would otherwise leave the old direction behind.
 */
export function initializeDocumentLanguage(): void {
    router.on('navigate', (event) => {
        const { locale, direction } = event.detail.page.props;

        if (typeof locale === 'string') {
            document.documentElement.lang = locale.replace('_', '-');
        }

        if (direction === 'ltr' || direction === 'rtl') {
            document.documentElement.dir = direction;
        }
    });
}
