import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** Today's date in the local time zone, formatted as YYYY-MM-DD. */
export function todayIso(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** Add a number of days to a YYYY-MM-DD date. */
export function addDays(date: string, days: number): string {
    const value = new Date(`${date}T00:00:00`);
    value.setDate(value.getDate() + days);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
}

/**
 * Read a typed date as YYYY-MM-DD. Accepts year first (2026-07-07, 2026/7/7,
 * 20260707) or day first (7/7/2026), in Latin or Arabic-Indic digits. Returns
 * null for anything that is not a real calendar date.
 */
export function parseTypedDate(text: string): string | null {
    const value = text
        .trim()
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0));

    const yearFirst =
        value.match(/^(\d{4})[-/.\s](\d{1,2})[-/.\s](\d{1,2})$/) ??
        value.match(/^(\d{4})(\d{2})(\d{2})$/);
    const dayFirst = value.match(/^(\d{1,2})[-/.\s](\d{1,2})[-/.\s](\d{4})$/);

    let parts: [number, number, number];

    if (yearFirst) {
        parts = [
            Number(yearFirst[1]),
            Number(yearFirst[2]),
            Number(yearFirst[3]),
        ];
    } else if (dayFirst) {
        parts = [Number(dayFirst[3]), Number(dayFirst[2]), Number(dayFirst[1])];
    } else {
        return null;
    }

    const [year, month, day] = parts;
    const date = new Date(Date.UTC(year, month - 1, day));

    if (
        year < 1900 ||
        year > 2100 ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day
    ) {
        return null;
    }

    const pad = (part: number) => String(part).padStart(2, '0');

    return `${year}-${pad(month)}-${pad(day)}`;
}

/** Whole days from the first YYYY-MM-DD date to the second. */
export function daysBetween(from: string, to: string): number {
    return Math.round(
        (new Date(`${to}T00:00:00`).getTime() -
            new Date(`${from}T00:00:00`).getTime()) /
            86_400_000,
    );
}
