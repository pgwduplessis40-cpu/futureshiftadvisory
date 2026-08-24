export const NZ_LOCALE = 'en-NZ';

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
    return new Intl.DateTimeFormat(NZ_LOCALE, options).format(new Date(value));
}

export function formatNzMonth(value: string): string {
    return formatNzDate(`${value}T00:00:00`, {
        month: 'short',
        year: 'numeric',
    });
}
