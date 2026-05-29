import { Head, Link, router, useForm } from '@inertiajs/react';
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

type CoachDashboardPayload = {
    applicationUrl: string;
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
    signUrl: string | null;
};

type Props = {
    dashboard: CoachDashboardPayload;
};

type CoachDashboardTab = 'actions' | 'information';

const coachSectionTabs: Record<string, CoachDashboardTab> = {
    'coach-agreement': 'actions',
    'coach-referrals': 'actions',
    'coach-messages': 'information',
    'coach-profile': 'information',
};

export default function CoachDashboard({ dashboard }: Props) {
    const panel = dashboard.panel;
    const [processingAction, setProcessingAction] = useState<string | null>(
        null,
    );
    const [highlightedSection, setHighlightedSection] = useState<string | null>(
        null,
    );
    const [activeTab, setActiveTab] = useState<CoachDashboardTab>(() =>
        initialCoachDashboardTab(),
    );

    const jumpToSection = (
        sectionId: string,
        event?: MouseEvent<HTMLAnchorElement>,
    ) => {
        event?.preventDefault();
        setActiveTab(coachSectionTabs[sectionId] ?? 'actions');

        window.setTimeout(() => {
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
        }, 0);
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
                        <SectionShortcut
                            sectionId="coach-referrals"
                            onJump={jumpToSection}
                        >
                            Referrals
                        </SectionShortcut>
                        <SectionShortcut
                            sectionId="coach-agreement"
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
                    <CoachApplicationCard
                        applicationUrl={dashboard.applicationUrl}
                    />
                ) : (
                    <>
                        <DashboardTabList
                            activeTab={activeTab}
                            onChange={setActiveTab}
                        />

                        {activeTab === 'actions' ? (
                            <>
                                <DashboardSection
                                    title="Priority actions"
                                    description="Start with active coaching referrals, agreement status, messages, and vetting."
                                >
                                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                        <MetricCard
                                            icon={UsersRound}
                                            label="Active referrals"
                                            value={
                                                dashboard.summary
                                                    .activeReferrals
                                            }
                                            detail={`${dashboard.summary.totalReferrals} total`}
                                            explanation="Active referrals are coach introductions that still need a response, session progress, or closure."
                                            href="#coach-referrals"
                                            actionLabel="Review"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={Sparkles}
                                            label="Underway"
                                            value={dashboard.summary.underway}
                                            detail="Coaching has started"
                                            explanation="Underway referrals have moved beyond acceptance and are now being supported by your coaching work."
                                            href="#coach-referrals"
                                            actionLabel="View"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={FileSignature}
                                            label="Agreement"
                                            value={
                                                dashboard.agreement
                                                    ? labelFor(
                                                          dashboard.agreement
                                                              .status,
                                                      )
                                                    : 'Pending'
                                            }
                                            detail={
                                                dashboard.agreement?.signedAt
                                                    ? `Signed ${formatDate(dashboard.agreement.signedAt)}`
                                                    : 'Signature record'
                                            }
                                            explanation="The panel agreement confirms whether the coach relationship has a signed operating record."
                                            href="#coach-agreement"
                                            actionLabel="Review"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={MessageSquare}
                                            label="Recent messages"
                                            value={dashboard.messages.length}
                                            detail="Referral notes"
                                            explanation="Recent messages contain advisory context and follow-up notes attached to coach referrals."
                                            href="#coach-messages"
                                            actionLabel="Open"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={ShieldCheck}
                                            label="Vetting"
                                            value={
                                                panel.vettedAt
                                                    ? 'Vetted'
                                                    : 'Pending'
                                            }
                                            detail={
                                                panel.vettedAt
                                                    ? `Checked ${formatDate(panel.vettedAt)}`
                                                    : 'Awaiting review'
                                            }
                                            explanation="Coach vetting confirms Future Shift Advisory has reviewed your coaching profile, specialisations, and professional boundary requirements."
                                            href="#coach-profile"
                                            actionLabel="Details"
                                            onJump={jumpToSection}
                                        />
                                    </section>
                                </DashboardSection>

                                <DashboardSection
                                    title="Action panels"
                                    description="Use these panels to progress coaching referrals and check agreement work."
                                >
                                    <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                                        <ReferralPipeline
                                            referrals={dashboard.referrals}
                                            processingAction={processingAction}
                                            onStageAction={updateReferralStage}
                                            highlighted={
                                                highlightedSection ===
                                                'coach-referrals'
                                            }
                                        />
                                        <AgreementPanel
                                            agreement={dashboard.agreement}
                                            highlighted={
                                                highlightedSection ===
                                                'coach-agreement'
                                            }
                                        />
                                    </section>
                                </DashboardSection>
                            </>
                        ) : (
                            <>
                                <DashboardSection
                                    title="Information"
                                    description="Review completed outcomes, profile details, and recent referral notes after priority work is clear."
                                >
                                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <MetricCard
                                            icon={ClipboardCheck}
                                            label="Concluded"
                                            value={dashboard.summary.concluded}
                                            detail="Completed outcomes"
                                            explanation="Concluded referrals have been closed as completed coaching outcomes."
                                            href="#coach-referrals"
                                            actionLabel="Open"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={UsersRound}
                                            label="Total referrals"
                                            value={
                                                dashboard.summary.totalReferrals
                                            }
                                            detail="Lifetime panel scope"
                                            explanation="Total referrals counts all coach referrals surfaced to your panel in this workspace."
                                            href="#coach-referrals"
                                            actionLabel="View"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={Sparkles}
                                            label="Specialisations"
                                            value={panel.specialisations.length}
                                            detail="Profile coverage"
                                            explanation="Specialisations show the coaching areas Future Shift Advisory has recorded for your panel profile."
                                            href="#coach-profile"
                                            actionLabel="Details"
                                            onJump={jumpToSection}
                                        />
                                        <MetricCard
                                            icon={ShieldCheck}
                                            label="Memberships"
                                            value={panel.memberships.length}
                                            detail="Professional records"
                                            explanation="Memberships list professional bodies and levels attached to the coach profile."
                                            href="#coach-profile"
                                            actionLabel="Details"
                                            onJump={jumpToSection}
                                        />
                                    </section>

                                    <section className="grid gap-4 xl:grid-cols-2">
                                        <CoachProfile
                                            panel={panel}
                                            highlighted={
                                                highlightedSection ===
                                                'coach-profile'
                                            }
                                        />
                                        <MessagePanel
                                            messages={dashboard.messages}
                                            highlighted={
                                                highlightedSection ===
                                                'coach-messages'
                                            }
                                        />
                                    </section>
                                </DashboardSection>
                            </>
                        )}
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
        'scroll-mt-6 rounded-md transition-[box-shadow,background-color] outline-none',
        highlighted && 'bg-primary/5 ring-2 ring-primary/40',
    );
}

function DashboardSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function DashboardTabList({
    activeTab,
    onChange,
}: {
    activeTab: CoachDashboardTab;
    onChange: (tab: CoachDashboardTab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Coach dashboard sections"
        >
            <DashboardTabButton
                active={activeTab === 'actions'}
                onClick={() => onChange('actions')}
            >
                Actions
            </DashboardTabButton>
            <DashboardTabButton
                active={activeTab === 'information'}
                onClick={() => onChange('information')}
            >
                Information
            </DashboardTabButton>
        </div>
    );
}

function DashboardTabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function initialCoachDashboardTab(): CoachDashboardTab {
    if (typeof window === 'undefined') {
        return 'actions';
    }

    return coachSectionTabs[window.location.hash.slice(1)] ?? 'actions';
}

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
                <Card className="rounded-md">
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
            id="coach-referrals"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
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
                            className="rounded-md border p-4"
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
                                <div className="mt-4 rounded-md bg-muted/50 p-3 text-sm">
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

function CoachProfile({
    panel,
    highlighted,
}: {
    panel: PanelSummary;
    highlighted: boolean;
}) {
    return (
        <Card
            id="coach-profile"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
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
                    <p className="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
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

function AgreementPanel({
    agreement,
    highlighted,
}: {
    agreement: AgreementSummary | null;
    highlighted: boolean;
}) {
    return (
        <Card
            id="coach-agreement"
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
                        <div className="flex flex-wrap gap-2">
                            {agreement.signUrl ? (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(agreement.signUrl ?? '', {})
                                    }
                                >
                                    <FileSignature aria-hidden="true" />
                                    Sign agreement
                                </Button>
                            ) : null}
                            <AgreementDetailDialog agreement={agreement} />
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function MessagePanel({
    messages,
    highlighted,
}: {
    messages: MessageSummary[];
    highlighted: boolean;
}) {
    return (
        <Card
            id="coach-messages"
            tabIndex={-1}
            className={sectionCardClass(highlighted)}
        >
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
                    <div className="rounded-md bg-muted/50 p-3 text-sm">
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
                {agreement.signUrl ? (
                    <DialogFooter>
                        <Button
                            onClick={() =>
                                router.post(agreement.signUrl ?? '', {})
                            }
                        >
                            <FileSignature aria-hidden="true" />
                            Sign agreement
                        </Button>
                    </DialogFooter>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function CoachApplicationCard({ applicationUrl }: { applicationUrl: string }) {
    const form = useForm({
        company: '',
        specialties: '',
        professional_memberships: '',
        bio: '',
    });

    return (
        <Card className="max-w-3xl rounded-md">
            <CardHeader>
                <CardTitle>Coach panel application</CardTitle>
                <CardDescription>
                    Submit your coach profile for FSA review, vetting, and panel
                    agreement issue.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(applicationUrl, { preserveScroll: true });
                    }}
                >
                    <Field
                        label="Company"
                        value={form.data.company}
                        error={form.errors.company}
                        onChange={(value) => form.setData('company', value)}
                    />
                    <Field
                        label="Specialties"
                        value={form.data.specialties}
                        error={form.errors.specialties}
                        placeholder="Leadership, Wellbeing"
                        onChange={(value) => form.setData('specialties', value)}
                    />
                    <Field
                        label="Professional memberships"
                        value={form.data.professional_memberships}
                        error={form.errors.professional_memberships}
                        placeholder="ICF, EMCC"
                        onChange={(value) =>
                            form.setData('professional_memberships', value)
                        }
                    />
                    <label className="grid gap-2 text-sm" htmlFor="coach-bio">
                        <span className="font-medium">Bio</span>
                        <textarea
                            id="coach-bio"
                            className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.bio}
                            onChange={(event) =>
                                form.setData('bio', event.target.value)
                            }
                        />
                        {form.errors.bio ? (
                            <span className="text-xs text-destructive">
                                {form.errors.bio}
                            </span>
                        ) : null}
                    </label>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Submit application
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    value,
    error,
    placeholder,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    placeholder?: string;
    onChange: (value: string) => void;
}) {
    const id = label.toLowerCase().replace(/\s+/g, '-');

    return (
        <label className="grid gap-2 text-sm" htmlFor={id}>
            <span className="font-medium">{label}</span>
            <input
                id={id}
                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
            />
            {error ? (
                <span className="text-xs text-destructive">{error}</span>
            ) : null}
        </label>
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
