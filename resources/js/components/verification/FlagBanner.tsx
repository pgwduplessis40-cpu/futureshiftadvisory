import { AlertTriangle, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { VerificationOutcome } from './Badge';
import { VerificationBadge } from './Badge';

type Props = {
    outcome: VerificationOutcome;
    title: string;
    children?: ReactNode;
};

export function FlagBanner({ outcome, title, children }: Props) {
    const blocking =
        outcome === 'accuracy_discrepancy' || outcome === 'verification_error';
    const Icon = blocking ? AlertTriangle : ShieldAlert;

    return (
        <div
            className={cn(
                'rounded-md border p-3 text-sm',
                blocking
                    ? 'border-destructive/30 bg-destructive/5'
                    : 'border-[var(--fs-admiralty)]/20 bg-[var(--fs-linen)]',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-2">
                    <Icon
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <div className="min-w-0">
                        <div className="font-medium">{title}</div>
                        {children && (
                            <div className="mt-1 text-muted-foreground">
                                {children}
                            </div>
                        )}
                    </div>
                </div>
                <VerificationBadge outcome={outcome} />
            </div>
        </div>
    );
}
