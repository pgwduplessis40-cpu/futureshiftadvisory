import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    BadgeCheck,
    BriefcaseBusiness,
    ClipboardCheck,
    Eye,
    FileSignature,
    Mail,
    MessageSquare,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, MouseEvent, ReactNode } from 'react';
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
import { cn } from '@/lib/utils';
import { dashboard as dashboardRoute } from '@/routes';

type BrokerDashboardPayload = {
    panel: PanelSummary | null;
    summary: {
        totalReferrals: number;
        activeReferrals: number;
        coverPlaced: number;
        reverseReferrals: number;
    };
    stageCounts: Record<string, number>;
    referrals: ReferralSummary[];
    messages: MessageSummary[];
    reverseReferrals: ReverseReferralSummary[];
    agreement: AgreementSummary | null;
};

type PanelSummary = {
    id: string;
    name: string;
    email: string;
    company: string;
    status: string;
    fspNumber: string | null;
    fspStatus: string | null;
    fspLastCheckedAt: string | null;
    regions: string[];
    specialties: string[];
    approvedAt: string | null;
    suspendedAt: string | null;
};

type ReferralSummary = {
    id: string;
    clientName: string;
    clientContact: string | null;
    referralType: string;
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
    clientName: string;
    stage: string | null;
    sentAt: string | null;
};

type ReverseReferralSummary = {
    id: string;
    targetType: string;
    name: string;
    company: string | null;
    email: string;
    submittedAt: string | null;
};

type AgreementSummary = {
    status: string;
    generatedAt: string | null;
    signedAt: string | null;
    pdfByteSize: number | null;
    hasStoredPdf: boolean;
};

type Props = {
    dashboard: BrokerDashboardPayload;
};

export default function BrokerDashboard({ dashboard }: Props) {
    const panel = dashboard.panel;
    const [processingAction, setProcessingAction] = useState<string | null>(
        null,
    );
    const [highlightedSection, setHighlightedSection] = useState<string | null>(
        null,
    );

    const jumpToSection = (
        sectionId: string,
        event?: MouseEvent<HTMLAnchorElement>,
    ) => {
        event?.preventDefault();

        const section = document.getElementById(sectionId);

        if (!section) {
            return;
        }

        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        section.focus({ preventScroll: true });
        window.history.replaceState(null, '', `#${sectionId}`);
        setHighlightedSection(sectionId);
        window.setTimeout(() => {
            setHighlightedSection((current) =>
                current === sectionId ? null : current,
            );
        }, 1800);
    };

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
            <Head title="Broker dashboard" />
            <main className="flex-1 space-y-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">
                            Broker dashboard
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
                                <FspBadge
                                    status={panel.fspStatus}
                                    lastCheckedAt={panel.fspLastCheckedAt}
                                />
                            </>
                        ) : null}
                        <SectionShortcut
                            sectionId="broker-referrals"
                            onJump={jumpToSection}
                        >
                            Referrals
                        </SectionShortcut>
                        <SectionShortcut
                            sectionId="broker-agreement"
                            onJump={jumpToSection}
                        >
                            Agreement
                        </SectionShortcut>
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
                                No broker panel profile is linked to this
                                account yet.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <section className="grid gap-4 md:grid-cols-4">
                            <MetricCard
                                icon={BriefcaseBusiness}
                                label="Active referrals"
                                value={dashboard.summary.activeReferrals}
                                detail={`${dashboard.summary.totalReferrals} total`}
                                explanation="Active referrals are broker introductions that still need acknowledgement, quote progress, cover placement, or closure."
                                href="#broker-referrals"
                                actionLabel="Review"
                                onJump={jumpToSection}
                            />
                            <MetricCard
                                icon={ClipboardCheck}
                                label="Cover placed"
                                value={dashboard.summary.coverPlaced}
                                detail="Completed broker outcomes"
                                explanation="Cover placed counts broker referrals that have reached a successful insurance placement outcome."
                                href="#broker-referrals"
                                actionLabel="View"
                                onJump={jumpToSection}
                            />
                            <MetricCard
                                icon={UsersRound}
                                label="Reverse referrals"
                                value={dashboard.summary.reverseReferrals}
                                detail="Submitted to FSA"
                                explanation="Reverse referrals are prospects or opportunities you have sent back to Future Shift Advisory for follow-up."
                                href="#broker-reverse-referrals"
                                actionLabel="Open"
                                onJump={jumpToSection}
                            />
                            <MetricCard
                                icon={ShieldCheck}
                                label="FSP status"
                                value={labelFor(panel.fspStatus ?? 'unknown')}
                                detail={
                                    panel.fspLastCheckedAt
                                        ? `Checked ${formatDate(panel.fspLastCheckedAt)}`
                                        : 'Not checked yet'
                                }
                                explanation="FSP status reflects the latest registration verification that Future Shift Advisory has recorded for your broker panel profile."
                                href="#broker-profile"
                                actionLabel="Details"
                                onJump={jumpToSection}
                            />
                        </section>

                        <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                            <ReferralPipeline
                                referrals={dashboard.referrals}
                                processingAction={processingAction}
                                onStageAction={updateReferralStage}
                                highlighted={
                                    highlightedSection === 'broker-referrals'
                                }
                            />
                            <BrokerProfile
                                panel={panel}
                                highlighted={
                                    highlightedSection === 'broker-profile'
                                }
                            />
                        </section>

                        <section className="grid gap-4 xl:grid-cols-3">
                            <AgreementPanel
                                agreement={dashboard.agreement}
                                highlighted={
                                    highlightedSection === 'broker-agreement'
                                }
                            />
                            <MessagePanel messages={dashboard.messages} />
                            <ReverseReferralPanel
                                referrals={dashboard.reverseReferrals}
                                highlighted={
                                    highlightedSection ===
                                    'broker-reverse-referrals'
                                }
                            />
                        </section>
                    </>
                )}
            </main>
        </>
    );
}

function SectionShortcut({
    children,
    sectionId,
    onJump,
}: {
    children: ReactNode;
    sectionId: string;
    onJump: (sectionId: string, event?: MouseEvent<HTMLAnchorElement>) => void;
}) {
    return (
        <Button variant="outline" size="sm" asChild>
            <a
                href={`#${sectionId}`}
                onClick={(event) => onJump(sectionId, event)}
            >
                {children}
                <ArrowUpRight aria-hidden="true" />
            </a>
        </Button>
    );
}

function sectionCardClass(highlighted: boolean) {
    return cn(
        'scroll-mt-6 rounded-lg transition-[box-shadow,background-color] outline-none',
        highlighted && 'bg-primary/5 ring-2 ring-primary/40',
    );
}

BrokerDashboard.layout = {
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
    onJump,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: string | number;
    detail: string;
    explanation: string;
    href?: string;
    actionLabel?: string;
    onJump?: (sectionId: string, event?: MouseEvent<HTMLAnchorElement>) => void;
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
                                <a
                                    href={href}
                                    onClick={(event) => {
                                        if (href.startsWith('#')) {
                                            onJump?.(href.slice(1), event);
                                        }
                                    }}
                                >
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
    highlighted,
}: {
    referrals: ReferralSummary[];
    processingAction: string | null;
    onStageAction: (referral: ReferralSummary, action: ReferralAction) => void;
    highlighted: boolean;
}) {
    return (
        <Card
            id="broker-referrals"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
            <CardHeader>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Referral pipeline</CardTitle>
                        <CardDescription>
                            Current client referrals assigned to your broker
                            panel.
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
                    <EmptyState text="No broker referrals assigned yet." />
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
                                            {referral.clientName}
                                        </h2>
                                        <StageBadge stage={referral.stage} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {referral.reason ??
                                            labelFor(referral.referralType)}
                                    </p>
                                    {referral.clientContact ? (
                                        <p className="text-sm">
                                            {referral.clientContact}
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
                                {referral.clientContact ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`mailto:${referral.clientContact}`}
                                        >
                                            <Mail aria-hidden="true" />
                                            Email client
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

function BrokerProfile({
    panel,
    highlighted,
}: {
    panel: PanelSummary;
    highlighted: boolean;
}) {
    return (
        <Card
            id="broker-profile"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
            <CardHeader>
                <CardTitle>Broker profile</CardTitle>
                <CardDescription>{panel.email}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-3 text-sm">
                    <Detail label="Company" value={panel.company} />
                    <Detail label="FSP number" value={panel.fspNumber ?? '-'} />
                    <Detail
                        label="Approved"
                        value={
                            panel.approvedAt
                                ? formatDate(panel.approvedAt)
                                : 'Pending'
                        }
                    />
                </dl>
                <TagList label="Regions" values={panel.regions} />
                <TagList label="Specialties" values={panel.specialties} />
            </CardContent>
        </Card>
    );
}

function AgreementPanel({
    agreement,
    highlighted,
}: {
    agreement: AgreementSummary | null;
    highlighted: boolean;
}) {
    return (
        <Card
            id="broker-agreement"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
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
                                    {message.clientName}
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

function ReverseReferralPanel({
    referrals,
    highlighted,
}: {
    referrals: ReverseReferralSummary[];
    highlighted: boolean;
}) {
    return (
        <Card
            id="broker-reverse-referrals"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle>Reverse referrals</CardTitle>
                    <BadgeCheck
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <CardDescription>
                    Prospects you have referred back to FSA.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {referrals.length === 0 ? (
                    <EmptyState text="No reverse referrals submitted." />
                ) : (
                    referrals.map((referral) => (
                        <article key={referral.id} className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">{referral.name}</p>
                                <Badge variant="outline">
                                    {labelFor(referral.targetType)}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {referral.company ?? referral.email}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {referral.submittedAt
                                    ? formatDate(referral.submittedAt)
                                    : null}
                            </p>
                            <div className="pt-2">
                                <ReverseReferralDetailDialog
                                    referral={referral}
                                />
                            </div>
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
                    <DialogTitle>{referral.clientName}</DialogTitle>
                    <DialogDescription>
                        Broker referral detail and current stage.
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid gap-3 text-sm">
                    <Detail label="Stage" value={labelFor(referral.stage)} />
                    <Detail
                        label="Type"
                        value={labelFor(referral.referralType)}
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
                    {referral.clientContact ? (
                        <Detail
                            label="Contact"
                            value={referral.clientContact}
                        />
                    ) : null}
                </dl>
                {referral.reason ? (
                    <div className="rounded-lg bg-muted/50 p-3 text-sm">
                        {referral.reason}
                    </div>
                ) : null}
                {referral.latestMessage ? (
                    <div className="rounded-lg border p-3 text-sm">
                        <p>{referral.latestMessage.body}</p>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {referral.latestMessage.sentAt
                                ? formatDate(referral.latestMessage.sentAt)
                                : null}
                        </p>
                    </div>
                ) : null}
                <DialogFooter>
                    {referral.clientContact ? (
                        <Button asChild>
                            <a href={`mailto:${referral.clientContact}`}>
                                <Mail aria-hidden="true" />
                                Email client
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

function ReverseReferralDetailDialog({
    referral,
}: {
    referral: ReverseReferralSummary;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Eye aria-hidden="true" />
                    View referral
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{referral.name}</DialogTitle>
                    <DialogDescription>
                        Reverse referral submitted to Future Shift Advisory.
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid gap-3 text-sm">
                    <Detail
                        label="Target"
                        value={labelFor(referral.targetType)}
                    />
                    <Detail label="Email" value={referral.email} />
                    <Detail label="Company" value={referral.company ?? '-'} />
                    <Detail
                        label="Submitted"
                        value={
                            referral.submittedAt
                                ? formatDate(referral.submittedAt)
                                : '-'
                        }
                    />
                </dl>
                <DialogFooter>
                    <Button asChild>
                        <a href={`mailto:${referral.email}`}>
                            <Mail aria-hidden="true" />
                            Email contact
                        </a>
                    </Button>
                </DialogFooter>
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
            ? 'Your broker panel agreement is active and your broker portal access is open.'
            : status === 'suspended'
              ? 'Your broker panel access is suspended. Contact Future Shift Advisory before acting on referrals.'
              : `Your broker panel application has been approved${approvedAt ? ` since ${formatDate(approvedAt)}` : ''}. You can work referrals while FSA keeps your panel record under review.`;

    return (
        <ExplainedBadge className={classes} explanation={explanation}>
            {labelFor(status)}
        </ExplainedBadge>
    );
}

function FspBadge({
    status,
    lastCheckedAt,
}: {
    status: string | null;
    lastCheckedAt: string | null;
}) {
    const classes =
        status === 'current'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : status === 'lapsed'
              ? 'border-red-200 bg-red-50 text-red-700'
              : 'border-amber-200 bg-amber-50 text-amber-700';
    const explanation =
        status === 'current'
            ? `FSP Current means the broker FSP registration passed the latest verification${lastCheckedAt ? ` on ${formatDate(lastCheckedAt)}` : ''}.`
            : status === 'lapsed'
              ? 'FSP Lapsed means the last FSP verification did not confirm a current registration.'
              : 'FSP Unknown means Future Shift Advisory has not confirmed a current FSP status yet.';

    return (
        <ExplainedBadge className={classes} explanation={explanation}>
            FSP {labelFor(status ?? 'unknown')}
        </ExplainedBadge>
    );
}

function ExplainedBadge({
    children,
    className,
    explanation,
}: {
    children: React.ReactNode;
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
    if (stage === 'cover_placed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (stage === 'declined' || stage === 'no_response') {
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
