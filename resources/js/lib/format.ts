/**
 * Display formatting for the Vietnamese UI (docs/06-giao-dien.md):
 * dates dd/MM/yyyy, money "1.250.000 ₫", times in Asia/Ho_Chi_Minh.
 * The server stores UTC; timezone/locale are shared as Inertia props and applied via configureFormatting().
 */

type FormatConfig = {
    locale: string;
    timeZone: string;
};

const config: FormatConfig = {
    locale: 'vi-VN',
    timeZone: 'Asia/Ho_Chi_Minh',
};

export function configureFormatting(
    options: Partial<{ locale: string | null; timeZone: string | null }>,
): void {
    if (options.locale) {
        config.locale = options.locale === 'vi' ? 'vi-VN' : options.locale;
    }

    if (options.timeZone) {
        config.timeZone = options.timeZone;
    }
}

type DateInput = string | number | Date | null | undefined;

function toDate(value: DateInput): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    // Date-only strings ("2026-11-17") are calendar dates, not instants: keep them in local time.
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-').map(Number);

        return new Date(y, m - 1, d);
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

function isDateOnly(value: DateInput): boolean {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

/** 17/11/2026 */
export function formatDate(value: DateInput, fallback = ''): string {
    const date = toDate(value);

    if (!date) {
        return fallback;
    }

    return new Intl.DateTimeFormat(config.locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: isDateOnly(value) ? undefined : config.timeZone,
    }).format(date);
}

/** 17/11/2026 14:30 */
export function formatDateTime(value: DateInput, fallback = ''): string {
    const date = toDate(value);

    if (!date) {
        return fallback;
    }

    const parts = new Intl.DateTimeFormat(config.locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: config.timeZone,
    }).formatToParts(date);

    const get = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((p) => p.type === type)?.value ?? '';

    return `${get('day')}/${get('month')}/${get('year')} ${get('hour')}:${get('minute')}`;
}

/** 14:30 */
export function formatTime(value: DateInput, fallback = ''): string {
    const date = toDate(value);

    if (!date) {
        return fallback;
    }

    return new Intl.DateTimeFormat(config.locale, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: config.timeZone,
    }).format(date);
}

/** "3 ngày trước", "trong 2 giờ nữa" */
export function formatRelative(
    value: DateInput,
    fallback = '',
    now: Date = new Date(),
): string {
    const date = toDate(value);

    if (!date) {
        return fallback;
    }

    const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(config.locale, { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return rtf.format(seconds, 'second');
}

/** 1.250.000 */
export function formatNumber(
    value: number | string | null | undefined,
    fallback = '',
    maximumFractionDigits = 2,
): string {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const number = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(number)) {
        return fallback;
    }

    return new Intl.NumberFormat(config.locale, {
        maximumFractionDigits,
    }).format(number);
}

/** 1.250.000 ₫ (VNĐ, no decimals — DB stores decimal(15,0)) */
export function formatMoney(
    value: number | string | null | undefined,
    fallback = '',
): string {
    const formatted = formatNumber(value, '', 0);

    return formatted === '' ? fallback : `${formatted} ₫`;
}

/** 24,50 — exam scores, decimal(5,2) */
export function formatScore(
    value: number | string | null | undefined,
    fallback = '',
): string {
    const number = typeof value === 'string' ? Number(value) : value;

    if (number === null || number === undefined || Number.isNaN(number)) {
        return fallback;
    }

    return new Intl.NumberFormat(config.locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}
