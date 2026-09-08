import { formatNzDate } from '@/lib/formatters';

export function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value);
}

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function formatRubricVersion(value: number | null): string {
    return value ? `rubric v${value}` : 'the assigned rubric';
}
