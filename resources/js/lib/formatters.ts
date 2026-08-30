export const NZ_LOCALE = 'en-NZ';
export const NZ_TIME_ZONE = 'Pacific/Auckland';

type CurrencyOptions = Omit<Intl.NumberFormatOptions, 'currency' | 'style'>;

export function formatCurrency(
    value: number,
    currency = 'NZD',
    options: CurrencyOptions = {},
): string {
    return new Intl.NumberFormat(NZ_LOCALE, {
        style: 'currency',
        currency,
        ...options,
    }).format(value);
}

export function formatNzdCurrency(
    value: number | null | undefined,
    options: CurrencyOptions = {},
): string {
    return formatCurrency(value ?? 0, 'NZD', {
        maximumFractionDigits: 0,
        ...options,
    });
}

export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(NZ_LOCALE, options).format(value);
}

export function formatPercentage(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return formatNumber(value, {
        style: 'percent',
        maximumFractionDigits: 1,
        ...options,
    });
}

export function formatNzDate(
    value: string | Date,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    const dateOnly =
        typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
    const date = dateOnly
        ? new Date(`${value}T00:00:00.000Z`)
        : new Date(value);

    return new Intl.DateTimeFormat(NZ_LOCALE, {
        ...options,
        timeZone: options.timeZone ?? (dateOnly ? 'UTC' : NZ_TIME_ZONE),
    }).format(date);
}

export function formatNzMonth(value: string): string {
    return formatNzDate(`${value}-01`, {
        month: 'short',
        year: 'numeric',
    });
}
