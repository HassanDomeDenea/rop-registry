import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { EnumName, EnumOption } from '@/types';

type Replacements = Record<string, string | number>;

/**
 * Translations are keyed by their English source text, exactly like Laravel's
 * JSON language files: an untranslated key simply renders as written.
 */
export function useI18n() {
    const page = usePage();

    const locale = computed(() => page.props.locale);
    const direction = computed(() => page.props.direction);
    const isRtl = computed(() => page.props.direction === 'rtl');

    // Latin digits keep clinical values and dates unambiguous in both languages.
    const intlLocale = computed(() =>
        page.props.locale === 'ar' ? 'ar-IQ-u-nu-latn' : 'en-GB',
    );

    function t(key: string, replacements: Replacements = {}): string {
        let text = page.props.translations[key] ?? key;

        for (const [name, value] of Object.entries(replacements)) {
            text = text.replaceAll(`:${name}`, String(value));
        }

        return text;
    }

    function enumOptions(name: EnumName): EnumOption[] {
        return page.props.enums[name] ?? [];
    }

    function enumLabel(
        name: EnumName,
        value: string | null | undefined,
        fallback = '—',
    ): string {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }

        return (
            enumOptions(name).find((option) => option.value === value)?.label ??
            value
        );
    }

    function formatDate(value: string | null | undefined, fallback = '—') {
        if (!value) {
            return fallback;
        }

        const date = new Date(
            value.length === 10 ? `${value}T00:00:00` : value,
        );

        return new Intl.DateTimeFormat(intlLocale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(date);
    }

    function formatDateTime(value: string | null | undefined, fallback = '—') {
        if (!value) {
            return fallback;
        }

        return new Intl.DateTimeFormat(intlLocale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    }

    function formatMonth(value: string) {
        return new Intl.DateTimeFormat(intlLocale.value, {
            month: 'short',
            year: '2-digit',
        }).format(new Date(`${value}-01T00:00:00`));
    }

    function formatNumber(value: number | null | undefined, fallback = '—') {
        return value === null || value === undefined
            ? fallback
            : new Intl.NumberFormat(intlLocale.value).format(value);
    }

    /** Weeks + days, the conventional notation for gestational and postmenstrual age. */
    function formatWeeks(days: number | null | undefined, fallback = '—') {
        if (days === null || days === undefined) {
            return fallback;
        }

        return t(':weeks w :days d', {
            weeks: Math.floor(days / 7),
            days: days % 7,
        });
    }

    function formatGestationalAge(
        weeks: number | null | undefined,
        days: number | null | undefined,
        fallback = '—',
    ) {
        if (weeks === null || weeks === undefined) {
            return fallback;
        }

        return days
            ? t(':weeks w :days d', { weeks, days })
            : t(':weeks w', { weeks });
    }

    function formatAge(days: number | null | undefined, fallback = '—') {
        if (days === null || days === undefined) {
            return fallback;
        }

        if (days < 14) {
            return t(':count days', { count: days });
        }

        if (days < 120) {
            return t(':count weeks', { count: Math.floor(days / 7) });
        }

        return t(':count months', { count: Math.floor(days / 30.4375) });
    }

    /** Describe a day offset relative to today, e.g. "In 3 days" or "5 days ago". */
    function formatRelativeDays(
        days: number | null | undefined,
        fallback = '',
    ) {
        if (days === null || days === undefined) {
            return fallback;
        }

        if (days === 0) {
            return t('Today');
        }

        if (days === 1) {
            return t('Tomorrow');
        }

        if (days === -1) {
            return t('Yesterday');
        }

        return days > 0
            ? t('In :count days', { count: days })
            : t(':count days ago', { count: Math.abs(days) });
    }

    function formatBytes(bytes: number) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(0)} KB`;
        }

        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return {
        t,
        locale,
        direction,
        isRtl,
        enumOptions,
        enumLabel,
        formatDate,
        formatDateTime,
        formatMonth,
        formatNumber,
        formatWeeks,
        formatGestationalAge,
        formatAge,
        formatRelativeDays,
        formatBytes,
    };
}
