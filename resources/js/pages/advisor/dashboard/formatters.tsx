import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import type { CashFlowStatus, HealthLevel } from './types';

export function HealthIcon({ health }: { health: HealthLevel }) {
    if (health === 'green') {
        return (
            <CheckCircle2
                className="size-4 text-emerald-600"
                aria-hidden="true"
            />
        );
    }

    return (
        <AlertTriangle
            className={
                health === 'amber'
                    ? 'size-4 text-amber-600'
                    : 'size-4 text-destructive'
            }
            aria-hidden="true"
        />
    );
}

export function engagementLabel(level: HealthLevel): string {
    return level.charAt(0).toUpperCase() + level.slice(1);
}

export function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function paymentStatusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'retrying') {
        return 'secondary';
    }

    return 'outline';
}

export function healthVariant(
    health: HealthLevel,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (health === 'green') {
        return 'secondary';
    }

    if (health === 'amber') {
        return 'outline';
    }

    return 'destructive';
}

export function formatDate(value: string | null): string {
    if (!value) {
        return 'No activity';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

export function formatDateOnly(value: string | null): string {
    if (!value) {
        return 'No period';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

export function formatPercent(value: number): string {
    return `${Math.round(value * 1000) / 10}%`;
}

export function formatSignedPercent(value: number): string {
    const prefix = value > 0 ? '+' : '';

    return `${prefix}${value.toFixed(2)}%`;
}

export function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatRunway(cashFlow: CashFlowStatus): string {
    if (cashFlow.runway_open_ended) {
        return 'Open-ended';
    }

    if (cashFlow.runway_months !== null) {
        return `${Math.round(cashFlow.runway_months)} months`;
    }

    if (cashFlow.cash_flow_positive_year !== null) {
        return `Cash positive year ${cashFlow.cash_flow_positive_year}`;
    }

    return 'Not available';
}

export function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatIndicatorValue(value: number, unit: string): string {
    if (unit === 'percent') {
        return `${value.toFixed(1)}%`;
    }

    if (unit === 'nzd_per_hour') {
        return `$${value.toFixed(2)}/hr`;
    }

    return value.toLocaleString();
}
