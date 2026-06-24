import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PanelMember = {
    id: string;
    panel_type: string;
    status: string;
    name: string;
    email: string | null;
    company: string | null;
    fsp_number: string | null;
    regions: string[];
    specialties: string[];
    review: { decision?: string; reason?: string } | null;
    applied_at: string | null;
    approve_url: string;
    request_info_url: string;
    decline_url: string;
};

export default function PanelMembersIndex({
    members,
}: {
    members: PanelMember[];
}) {
    const page = usePage();
    const errors = page.props.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title="Panel applications" />
            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Panel governance
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Panel applications
                        </h1>
                    </div>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge variant="secondary">
                                {members.length} open
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Applications stay here until approved, declined, or
                            sent back for more information.
                        </TooltipContent>
                    </Tooltip>
                </header>

                {errors.approve && (
                    <div
                        role="alert"
                        className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                    >
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{errors.approve}</span>
                    </div>
                )}

                {members.length === 0 ? (
                    <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                        No panel applications need a decision.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        {members.map((member) => (
                            <PanelMemberCard key={member.id} member={member} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function PanelMemberCard({ member }: { member: PanelMember }) {
    const [reason, setReason] = useState(member.review?.reason ?? '');
    const canDecide = !['approved_pending_agreement', 'declined'].includes(
        member.status,
    );
    const brokerMissingFsp =
        member.panel_type === 'broker' && !member.fsp_number;
    const canApprove = canDecide && !brokerMissingFsp;

    return (
        <article className="grid gap-4 rounded-md border bg-background p-4 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.55fr)]">
            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{member.panel_type}</Badge>
                    <Badge variant={statusVariant(member.status)}>
                        {member.status.replaceAll('_', ' ')}
                    </Badge>
                    {member.fsp_number && (
                        <Badge variant="secondary">{member.fsp_number}</Badge>
                    )}
                </div>
                <div>
                    <h2 className="text-base font-semibold">{member.name}</h2>
                    <p className="text-sm text-muted-foreground">
                        {member.company ?? 'Company not supplied'}
                        {member.email ? ` - ${member.email}` : ''}
                    </p>
                </div>
                <dl className="grid gap-2 text-sm sm:grid-cols-3">
                    <Detail
                        label="Applied"
                        value={formatDate(member.applied_at)}
                    />
                    <Detail
                        label="Regions"
                        value={member.regions.join(', ') || 'Not supplied'}
                    />
                    <Detail
                        label="Specialties"
                        value={member.specialties.join(', ') || 'Not supplied'}
                    />
                </dl>
                {member.review?.reason && (
                    <p className="rounded-md border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                        {member.review.reason}
                    </p>
                )}
                {brokerMissingFsp && (
                    <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                        Broker approval requires an FSP number. Request more
                        information before approving this application.
                    </p>
                )}
            </div>

            <div className="grid gap-3">
                <label className="grid gap-1">
                    <span className="text-xs text-muted-foreground">
                        Decision note
                    </span>
                    <textarea
                        className="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="Reason for more information or decline"
                    />
                </label>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        disabled={!canApprove}
                        onClick={() => router.patch(member.approve_url)}
                    >
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        Approve
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={!canDecide}
                        onClick={() =>
                            router.patch(member.request_info_url, {
                                reason:
                                    reason ||
                                    'Please provide the missing panel application information.',
                            })
                        }
                    >
                        <Info className="size-4" aria-hidden="true" />
                        More info
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        disabled={!canDecide}
                        onClick={() =>
                            router.patch(member.decline_url, {
                                reason:
                                    reason ||
                                    'Application declined after panel review.',
                            })
                        }
                    >
                        <XCircle className="size-4" aria-hidden="true" />
                        Decline
                    </Button>
                </div>
            </div>
        </article>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'approved_pending_agreement') {
        return 'default';
    }

    if (status === 'declined') {
        return 'destructive';
    }

    if (status === 'information_requested') {
        return 'outline';
    }

    return 'secondary';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not supplied';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

PanelMembersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Panel applications',
            href: '/admin/panel-members',
        },
    ],
};
