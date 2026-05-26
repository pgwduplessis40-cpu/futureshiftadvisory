import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    ClipboardCheck,
    Eye,
    FileSignature,
    Mail,
    MessageSquare,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard as dashboardRoute } from '@/routes';

type CoachDashboardPayload = {
    panel: PanelSummary | null;
    summary: {
        totalReferrals: number;
        activeReferrals: number;
        underway: number;
        concluded: number;
    };
    stageCounts: Record<string, number>;
    referrals: ReferralSummary[];
    messages: MessageSummary[];
    agreement: AgreementSummary | null;
};

type PanelSummary = {
    id: string;
    name: string;
    email: string;
    company: string;
    status: string;
    bio: string | null;
    specialisations: string[];
    memberships: Array<{
        body: string;
        level: string | null;
    }>;
    approvedAt: string | null;
    suspendedAt: string | null;
    vettedAt: string | null;
};

type ReferralSummary = {
    id: string;
    subjectName: string;
    subjectContact: string | null;
    subjectType: string;
    referralType: string;
    specialisation: string | null;
    stage: string;
    reason: string | null;
    sentAt: string | null;
    closedAt: string | null;
    stageUpdateUrl: string;
    availableActions: ReferralAction[];
    latestMessage: {
        body: string;
        sentAt: string | null;
    } | null;
};

type ReferralAction = {
    stage: string;
    label: string;
    tone: 'default' | 'outline';
};

type MessageSummary = {
    id: string;
    body: string;
    subjectName: string;
    stage: string | null;
    sentAt: string | null;
};

type AgreementSummary = {
    status: string;
    generatedAt: string | null;
    signedAt: string | null;
    pdfByteSize: number | null;
    hasStoredPdf: boolean;
};

type Props = {
    dashboard: CoachDashboardPayload;
};

export default function CoachDashboard({ dashboard }: Props) {
    const panel = dashboard.panel;
    const [processingAction, setProcessingAction] = useState<string | null>(
        null,
    );

    const updateReferralStage = (
        referral: ReferralSummary,
        action: ReferralAction,
    ) => {
        const actionKey = `${referral.id}-${action.stage}`;

        setProcessingAction(actionKey);
        router.patch(
            referral.stageUpdateUrl,
            { stage: action.stage },
            {
                preserveScroll: true,
                onFinish: () => setProcessingAction(null),
            },
        );
    };

    return (
        <>
            <Head title="Coach dashboard" />
            <main className="flex-1 space-y-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">
                            Coach dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {panel?.company ?? 'Panel access'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {panel ? (
                            <>
                                <StatusBadge
                                    status={panel.status}
                                    approvedAt={panel.approvedAt}
                                />
                                <VettingBadge vettedAt={panel.vettedAt} />
                            </>
                        ) : null}
                        <Button variant="outline" size="sm" asChild>
                            <a href="#coach-referrals">
                                Referrals
                                <ArrowUpRight aria-hidden="true" />
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a href="#coach-agreement">
                                Agreement
                                <ArrowUpRight aria-hidden="true" />
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/notifications">
                                Notifications
                                <ArrowUpRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                </div>

                {!panel ? (
                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Panel setup pending</CardTitle>
                            <CardDescription>
                                No coach panel profile is linked to this account
                                yet.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <section className="grid gap-4 md:grid-cols-4">
                            <MetricCard
                                icon={UsersRound}
                                label="Active referrals"
                                value={dashboard.summary.activeReferrals}
                                detail={`${dashboard.summary.totalReferrals} total`}
                                explanation="Active referrals are coach introductions that still need a response, session progress, or closure."
                                href="#coach-referrals"
                                actionLabel="Review"
                            />
                            <MetricCard
                                icon={Sparkles}
                                label="Underway"
                                value={dashboard.summary.underway}
                                detail="Coaching has started"
                                explanation="Underway referrals have moved beyond acceptance and are now being supported by your coaching work."
                                href="#coach-referrals"
                                actionLabel="View"
                            />
                            <MetricCard
                                icon={ClipboardCheck}
                                label="Concluded"
                                value={dashboard.summary.concluded}
                                detail="Completed outcomes"
                                explanation="Concluded referrals have been closed as completed coaching outcomes."
                                href="#coach-referrals"
                                actionLabel="Open"
                            />
                            <MetricCard
                                icon={ShieldCheck}
                                label="Vetting"
                                value={panel.vettedAt ? 'Vetted' : 'Pending'}
                                detail={
                                    panel.vettedAt
                                        ? `Checked ${formatDate(panel.vettedAt)}`
                                        : 'Awaiting review'
                                }
                                explanation="Coach vetting confirms Future Shift Advisory has reviewed your coaching profile, specialisations, and professional boundary requirements."
                                href="#coach-profile"
                                actionLabel="Details"
                            />
                        </section>

                        <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                            <ReferralPipeline
                                referrals={dashboard.referrals}
                                processingAction={processingAction}
                                onStageAction={updateReferralStage}
                            />
                            <CoachProfile panel={panel} />
                        </section>

                        <section className="grid gap-4 xl:grid-cols-2">
                            <AgreementPanel agreement={dashboard.agreement} />
                            <MessagePanel messages={dashboard.messages} />
                        </section>
                    </>
                )}
            </main>
        </>
    );
}

CoachDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboardRoute(),
        },
    ],
};

function MetricCard({
    icon: Icon,
    label,
    value,
    detail,
    explanation,
    href,
    actionLabel,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: string | number;
    detail: string;
    explanation: string;
    href?: string;
    actionLabel?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Card className="rounded-lg">
                    <CardHeader className="gap-3">
                        <div className="flex items-center justify-between gap-3">
                            <CardDescription>{label}</CardDescription>
                            <Icon
                                className="size-4 text-muted-foreground"
                                aria-hidden={true}
                            />
                        </div>
                        <CardTitle className="text-2xl">{value}</CardTitle>
                    </CardHeader>
                    <CardContent className="flex items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {detail}
                        </p>
                        {href && actionLabel ? (
                            <Button variant="ghost" size="sm" asChild>
                                <a href={href}>
                                    {actionLabel}
                                    <ArrowUpRight aria-hidden="true" />
                                </a>
                            </Button>
                        ) : null}
                    </CardContent>
                </Card>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function ReferralPipeline({
    referrals,
    processingAction,
    onStageAction,
}: {
    referrals: ReferralSummary[];
    processingAction: string | null;
    onStageAction: (referral: ReferralSummary, action: ReferralAction) => void;
}) {
    return (
        <Card id="coach-referrals" className="rounded-lg">
            <CardHeader>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Referral pipeline</CardTitle>
                        <CardDescription>
                            Current coaching referrals assigned to your panel.
                        </CardDescription>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/notifications">
                            Referral alerts
                            <ArrowUpRight aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {referrals.length === 0 ? (
                    <EmptyState text="No coach referrals assigned yet." />
                ) : (
                    referrals.map((referral) => (
                        <article
                            key={referral.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-medium">
                                            {referral.subjectName}
                                        </h2>
                                        <StageBadge stage={referral.stage} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {referral.reason ??
                                            labelFor(referral.referralType)}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {labelFor(referral.subjectType)}
                                        </Badge>
                                        {referral.specialisation ? (
                                            <Badge variant="secondary">
                                                {labelFor(
                                                    referral.specialisation,
                                                )}
                                            </Badge>
                                        ) : null}
                                    </div>
                                    {referral.subjectContact ? (
                                        <p className="text-sm">
                                            {referral.subjectContact}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {referral.sentAt
                                        ? formatDate(referral.sentAt)
                                        : 'Not sent'}
                                </div>
                            </div>
                            {referral.latestMessage ? (
                                <div className="mt-4 rounded-lg bg-muted/50 p-3 text-sm">
                                    <p>{referral.latestMessage.body}</p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {referral.latestMessage.sentAt
                                            ? formatDate(
                                                  referral.latestMessage.sentAt,
                                              )
                                            : null}
                                    </p>
                                </div>
                            ) : null}
                            <div className="mt-4 flex flex-wrap gap-2">
                                <ReferralDetailDialog referral={referral} />
                                {referral.subjectContact ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`mailto:${referral.subjectContact}`}
                                        >
                                            <Mail aria-hidden="true" />
                                            Email contact
                                        </a>
                                    </Button>
                                ) : null}
                                {referral.availableActions.map((action) => {
                                    const actionKey = `${referral.id}-${action.stage}`;

                                    return (
                                        <Button
                                            key={action.stage}
                                            type="button"
                                            size="sm"
                                            variant={action.tone}
                                            disabled={
                                                processingAction === actionKey
                                            }
                                            onClick={() =>
                                                onStageAction(referral, action)
                                            }
                                        >
                                            {action.label}
                                        </Button>
                                    );
                                })}
                            </div>
                        </article>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function CoachProfile({ panel }: { panel: PanelSummary }) {
    return (
        <Card id="coach-profile" className="rounded-lg">
            <CardHeader>
                <CardTitle>Coach profile</CardTitle>
                <CardDescription>{panel.email}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-3 text-sm">
                    <Detail label="Company" value={panel.company} />
                    <Detail
                        label="Approved"
                        value={
                            panel.approvedAt
                                ? formatDate(panel.approvedAt)
                                : 'Pending'
                        }
                    />
                    <Detail
                        label="Vetted"
                        value={
                            panel.vettedAt
                                ? formatDate(panel.vettedAt)
                                : 'Pending'
                        }
                    />
                </dl>
                {panel.bio ? (
                    <p className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                        {panel.bio}
                    </p>
                ) : null}
                <TagList
                    label="Specialisations"
                    values={panel.specialisations}
                />
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">Memberships</p>
                    {panel.memberships.length === 0 ? (
                        <p className="text-sm">-</p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {panel.memberships.map((membership) => (
                                <Badge
                                    key={`${membership.body}-${membership.level ?? 'member'}`}
                                    variant="secondary"
                                >
                                    {membership.level
                                        ? `${membership.body} / ${membership.level}`
                                        : membership.body}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function AgreementPanel({ agreement }: { agreement: AgreementSummary | null }) {
    return (
        <Card id="coach-agreement" className="rounded-lg">
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle>Panel agreement</CardTitle>
                    <FileSignature
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <CardDescription>
                    Agreement status and signature record.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {!agreement ? (
                    <EmptyState text="No panel agreement has been issued." />
                ) : (
                    <div className="space-y-4">
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Status"
                                value={labelFor(agreement.status)}
                            />
                            <Detail
                                label="Generated"
                                value={
                                    agreement.generatedAt
                                        ? formatDate(agreement.generatedAt)
                                        : '-'
                                }
                            />
                            <Detail
                                label="Signed"
                                value={
                                    agreement.signedAt
                                        ? formatDate(agreement.signedAt)
                                        : 'Pending'
                                }
                            />
                        </dl>
                        <AgreementDetailDialog agreement={agreement} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function MessagePanel({ messages }: { messages: MessageSummary[] }) {
    return (
        <Card className="rounded-lg">
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle>Recent messages</CardTitle>
                    <MessageSquare
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <CardDescription>
                    Latest referral notes from the advisory team.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {messages.length === 0 ? (
                    <EmptyState text="No referral messages yet." />
                ) : (
                    messages.map((message) => (
                        <article key={message.id} className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">
                                    {message.subjectName}
                                </p>
                                {message.stage ? (
                                    <StageBadge stage={message.stage} />
                                ) : null}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {message.body}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {message.sentAt
                                    ? formatDate(message.sentAt)
                                    : null}
                            </p>
                        </article>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function ReferralDetailDialog({ referral }: { referral: ReferralSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Eye aria-hidden="true" />
                    View details
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{referral.subjectName}</DialogTitle>
                    <DialogDescription>
                        Coach referral detail and current stage.
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid gap-3 text-sm">
                    <Detail label="Stage" value={labelFor(referral.stage)} />
                    <Detail
                        label="Subject"
                        value={labelFor(referral.subjectType)}
                    />
                    <Detail
                        label="Specialisation"
                        value={
                            referral.specialisation
                                ? labelFor(referral.specialisation)
                                : '-'
                        }
                    />
                    <Detail
                        label="Sent"
                        value={
                            referral.sentAt
                                ? formatDate(referral.sentAt)
                                : 'Not sent'
                        }
                    />
                    <Detail
                        label="Closed"
                        value={
                            referral.closedAt
                                ? formatDate(referral.closedAt)
                                : 'Open'
                        }
                    />
                    {referral.subjectContact ? (
                        <Detail
                            label="Contact"
                            value={referral.subjectContact}
                        />
                    ) : null}
                </dl>
                {referral.reason ? (
                    <div className="rounded-lg bg-muted/50 p-3 text-sm">
                        {referral.reason}
                    </div>
                ) : null}
                <DialogFooter>
                    {referral.subjectContact ? (
                        <Button asChild>
                            <a href={`mailto:${referral.subjectContact}`}>
                                <Mail aria-hidden="true" />
                                Email contact
                            </a>
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AgreementDetailDialog({ agreement }: { agreement: AgreementSummary }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Eye aria-hidden="true" />
                    View agreement
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Panel agreement</DialogTitle>
                    <DialogDescription>
                        Signature status and stored agreement metadata.
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid gap-3 text-sm">
                    <Detail label="Status" value={labelFor(agreement.status)} />
                    <Detail
                        label="Generated"
                        value={
                            agreement.generatedAt
                                ? formatDate(agreement.generatedAt)
                                : '-'
                        }
                    />
                    <Detail
                        label="Signed"
                        value={
                            agreement.signedAt
                                ? formatDate(agreement.signedAt)
                                : 'Pending'
                        }
                    />
                    <Detail
                        label="Stored PDF"
                        value={agreement.hasStoredPdf ? 'Available' : 'Pending'}
                    />
                    {agreement.pdfByteSize ? (
                        <Detail
                            label="File size"
                            value={`${Math.round(agreement.pdfByteSize / 1024)} KB`}
                        />
                    ) : null}
                </dl>
            </DialogContent>
        </Dialog>
    );
}

function Detail({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function TagList({ label, values }: { label: string; values: string[] }) {
    return (
        <div className="space-y-2">
            <p className="text-sm text-muted-foreground">{label}</p>
            {values.length === 0 ? (
                <p className="text-sm">-</p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {values.map((value) => (
                        <Badge key={value} variant="secondary">
                            {labelFor(value)}
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}

function EmptyState({ text }: { text: string }) {
    return <p className="text-sm text-muted-foreground">{text}</p>;
}

function StatusBadge({
    status,
    approvedAt,
}: {
    status: string;
    approvedAt: string | null;
}) {
    const classes =
        status === 'active'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : status === 'suspended'
              ? 'border-red-200 bg-red-50 text-red-700'
              : 'border-amber-200 bg-amber-50 text-amber-700';
    const explanation =
        status === 'active'
            ? 'Your coach panel agreement is active and your coach portal access is open.'
            : status === 'suspended'
              ? 'Your coach panel access is suspended. Contact Future Shift Advisory before acting on referrals.'
              : `Your coach panel application has been approved${approvedAt ? ` since ${formatDate(approvedAt)}` : ''}. You can work referrals while FSA keeps your panel record under review.`;

    return (
        <ExplainedBadge className={classes} explanation={explanation}>
            {labelFor(status)}
        </ExplainedBadge>
    );
}

function VettingBadge({ vettedAt }: { vettedAt: string | null }) {
    const classes = vettedAt
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-amber-200 bg-amber-50 text-amber-700';
    const explanation = vettedAt
        ? `Coach vetting was completed on ${formatDate(vettedAt)}. This confirms your coaching scope and professional boundaries have been reviewed.`
        : 'Coach vetting is pending. Future Shift Advisory reviews specialisations, memberships, and scope boundaries before relying on the panel profile.';

    return (
        <ExplainedBadge className={classes} explanation={explanation}>
            {vettedAt ? 'Vetted' : 'Vetting pending'}
        </ExplainedBadge>
    );
}

function ExplainedBadge({
    children,
    className,
    explanation,
}: {
    children: ReactNode;
    className: string;
    explanation: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex" tabIndex={0}>
                    <Badge variant="outline" className={className}>
                        {children}
                    </Badge>
                </span>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function StageBadge({ stage }: { stage: string }) {
    const classes = stageClass(stage);

    return (
        <Badge variant="outline" className={classes}>
            {labelFor(stage)}
        </Badge>
    );
}

function stageClass(stage: string): string {
    if (stage === 'concluded' || stage === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (stage === 'declined') {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    if (stage === 'withdrawn') {
        return 'border-slate-200 bg-slate-50 text-slate-700';
    }

    return 'border-sky-200 bg-sky-50 text-sky-700';
}

function labelFor(value: string): string {
    return value
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}
