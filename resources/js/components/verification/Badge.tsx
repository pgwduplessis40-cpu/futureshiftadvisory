import { AlertTriangle, CheckCircle2, Clock3, ShieldAlert } from 'lucide-react';
import { Badge as UiBadge } from '@/components/ui/badge';

export type VerificationOutcome =
    | 'pending'
    | 'verified'
    | 'advisory_flag'
    | 'accuracy_discrepancy'
    | 'verification_error';

const LABELS: Record<VerificationOutcome, string> = {
    pending: 'Pending',
    verified: 'Verified',
    advisory_flag: 'Advisor review',
    accuracy_discrepancy: 'Discrepancy',
    verification_error: 'Verification error',
};

export function VerificationBadge({
    outcome,
}: {
    outcome: VerificationOutcome;
}) {
    const Icon = {
        pending: Clock3,
        verified: CheckCircle2,
        advisory_flag: ShieldAlert,
        accuracy_discrepancy: AlertTriangle,
        verification_error: AlertTriangle,
    }[outcome];

    const variant =
        outcome === 'accuracy_discrepancy' || outcome === 'verification_error'
            ? 'destructive'
            : outcome === 'verified'
              ? 'secondary'
              : 'outline';

    return (
        <UiBadge variant={variant}>
            <Icon className="size-3" aria-hidden="true" />
            {LABELS[outcome]}
        </UiBadge>
    );
}
