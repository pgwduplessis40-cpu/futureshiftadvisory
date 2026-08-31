import { Head, Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    ArrowLeft,
    Brain,
    CreditCard,
    FileCheck2,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    ListChecks,
    LockKeyhole,
    Mail,
    MessageSquare,
    MessageSquarePlus,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    SlidersHorizontal,
    Target,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import type { Dispatch, MouseEvent, SetStateAction } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import InputError from '@/components/input-error';
import { NpoHealthPanel } from '@/components/npo/NpoHealthPanel';
import { AdvisorSupportAction } from '@/components/screen-share/AdvisorSupportAction';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    AccountingConnectionsPanel,
    PaymentsPanel,
} from './client-detail-finance';
import { GoalsPanel } from './client-detail-goals';
import {
    FindingFeedbackCard,
    KnowledgeAssessmentPanel,
} from './client-detail-knowledge';
import { AdvisorServiceTabList } from './service-workspaces';
import {
    ActionTile,
    AnalysisFindingFilterButton,
    ClientDetailSection,
    ClientDetailTabList,
} from './client-detail-navigation';
import {
    NpoFundingPanel,
    NpoSocialEnterprisePanel,
    NpoValuePanel,
} from './client-detail-npo';
import {
    Detail,
    Metric,
    WellbeingTrend,
    formatDate,
    lifecycleActions,
    lifecycleIcon,
    statusVariant,
} from './client-detail-presenters';
import { ProposalsPanel } from './client-detail-proposals';
import { MeetingsBriefingsPanel, ReportsPanel } from './client-detail-reports';
import type {
    AnalysisFindingFeedback,
    AnalysisFindingFilter,
    ClientDetail,
    ClientDetailTab,
    ConflictDeclaration,
    LifecycleForm,
    Props,
    StandardAdvisoryGeneratePayload,
} from './client-detail-types';
import { AdvisorServiceWorkspace } from './client-detail-workspace';
import type {
    AdvisorServiceTab,
    AdvisorServiceTabKey,
} from './service-workspaces';
type ClientDetailLayoutProps = {
    client: ClientDetail;
    conflictDeclaration: ConflictDeclaration;
    screenShare: Props['screenShare'];
    coBrowse: Props['coBrowse'];
    activeTab: ClientDetailTab;
    setActiveTab: Dispatch<SetStateAction<ClientDetailTab>>;
    advisorServiceTabs: AdvisorServiceTab[];
    selectedServiceTab: AdvisorServiceTabKey;
    selectAdvisorServiceTab: (tab: AdvisorServiceTabKey) => void;
    generatingPack: boolean;
    analysisFindingFilter: AnalysisFindingFilter;
    setAnalysisFindingFilter: Dispatch<SetStateAction<AnalysisFindingFilter>>;
    lifecycleForm: InertiaFormProps<LifecycleForm>;
    submitLifecycle: (status: string) => void;
    resendInvite: () => void;
    cancelInvite: () => void;
    recomputeHealthRadar: () => void;
    createKnowledgeDraft: () => void;
    jumpToSection: (sectionId: string, event?: MouseEvent<Element>) => void;
    paymentExceptionCount: number;
    analysisFindingsNeedingReview: AnalysisFindingFeedback[];
    reviewedAnalysisFindings: AnalysisFindingFeedback[];
    visibleAnalysisFindings: AnalysisFindingFeedback[];
    draftProposalCount: number;
    npoConfigurationSummary: string;
    strategicBudgetPriorityValue: string;
    strategicBudgetActionLabel: string;
    dueDiligencePriorityValue: string | null;
    isDueDiligenceClient: boolean;
    advisoryAccessPriorityValue: string;
    strategicPlanPriorityValue: string;
    standardAdvisoryReportStatus: string | undefined;
    showStrategicPlanActions: boolean;
    runStandardAdvisoryAnalysis: () => void;
    generateStandardAdvisoryPack: (
        payload?: StandardAdvisoryGeneratePayload,
    ) => void;
};

export function ClientDetailLayout({
    client,
    conflictDeclaration,
    screenShare,
    coBrowse,
    activeTab,
    setActiveTab,
    advisorServiceTabs,
    selectedServiceTab,
    selectAdvisorServiceTab,
    generatingPack,
    analysisFindingFilter,
    setAnalysisFindingFilter,
    lifecycleForm,
    submitLifecycle,
    resendInvite,
    cancelInvite,
    recomputeHealthRadar,
    createKnowledgeDraft,
    jumpToSection,
    paymentExceptionCount,
    analysisFindingsNeedingReview,
    reviewedAnalysisFindings,
    visibleAnalysisFindings,
    draftProposalCount,
    npoConfigurationSummary,
    strategicBudgetPriorityValue,
    strategicBudgetActionLabel,
    dueDiligencePriorityValue,
    isDueDiligenceClient,
    advisoryAccessPriorityValue,
    strategicPlanPriorityValue,
    standardAdvisoryReportStatus,
    showStrategicPlanActions,
    runStandardAdvisoryAnalysis,
    generateStandardAdvisoryPack,
}: ClientDetailLayoutProps) {
    return (
        <>
            <Head title={client.legal_name} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.legal_name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{client.engagement_type_label}</span>
                            <Badge variant="outline">
                                {client.account_status_label}
                            </Badge>
                            {client.is_npo && (
                                <Badge variant="secondary">NPO</Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {client.invitation?.resend_url ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={resendInvite}
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Resend invite
                            </Button>
                        ) : null}
                        {client.invitation?.cancel_url ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={cancelInvite}
                            >
                                <XCircle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Cancel invite
                            </Button>
                        ) : null}
                        <AdvisorSupportAction
                            config={screenShare}
                            coBrowse={coBrowse}
                        />
                        <Button
                            asChild
                            id="section-messages"
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                href={`/advisor/clients/${client.id}/messages`}
                            >
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/compose`}
                            >
                                <Mail className="size-4" aria-hidden="true" />
                                Email
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/surveys`}
                            >
                                <ListChecks
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Surveys
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/offboarding`}
                            >
                                <FileCheck2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Offboard
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/advisor/clients">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <AdvisorServiceTabList
                    tabs={advisorServiceTabs}
                    activeTab={selectedServiceTab}
                    onChange={selectAdvisorServiceTab}
                />

                {selectedServiceTab === 'overview' ? (
                    <>
                        <ClientDetailTabList
                            activeTab={activeTab}
                            onChange={setActiveTab}
                        />

                        {activeTab === 'actions' ? (
                            <>
                                <ClientDetailSection
                                    title="Priority actions"
                                    description="Start with communication, lifecycle, client work, and commercial actions."
                                    collapsible
                                >
                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                        <ActionTile
                                            icon={MessageSquare}
                                            title="Messages"
                                            value="Client thread"
                                            explanation="Open the secure client message thread and review the latest context."
                                            href={`/advisor/clients/${client.id}/messages`}
                                            actionLabel="Open"
                                        />
                                        <ActionTile
                                            icon={Mail}
                                            title="Email"
                                            value="Compose update"
                                            explanation="Send a structured advisory email to the client contact."
                                            href={`/advisor/clients/${client.id}/compose`}
                                            actionLabel="Compose"
                                        />
                                        <ActionTile
                                            icon={RotateCcw}
                                            title="Lifecycle"
                                            value={client.status_label}
                                            explanation="Change lifecycle state, pause access, suspend access, or restore the client."
                                            href="#section-lifecycle"
                                            actionLabel="Manage"
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-lifecycle',
                                                    event,
                                                )
                                            }
                                        />
                                        {client.due_diligence && (
                                            <ActionTile
                                                icon={ShieldAlert}
                                                title="Due Diligence"
                                                value={
                                                    dueDiligencePriorityValue ??
                                                    'Open'
                                                }
                                                explanation="Review the DD target, evidence, workstreams, and the reviewed DD report before moving into funding-plan work."
                                                href="#section-due-diligence"
                                                actionLabel="Review"
                                                onAction={(event) =>
                                                    jumpToSection(
                                                        'section-due-diligence',
                                                        event,
                                                    )
                                                }
                                            />
                                        )}
                                        <ActionTile
                                            icon={FileSpreadsheet}
                                            title="Business Plan & Budget"
                                            value={strategicBudgetPriorityValue}
                                            explanation="Assess the DD-sourced business plan, budget evidence, funding assumptions, and readiness before advisor approval."
                                            href="#section-strategic-budget"
                                            actionLabel={
                                                strategicBudgetActionLabel
                                            }
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-strategic-budget',
                                                    event,
                                                )
                                            }
                                        />
                                        {isDueDiligenceClient &&
                                            client.due_diligence && (
                                                <ActionTile
                                                    icon={TrendingUp}
                                                    title="Advisory access"
                                                    value={
                                                        advisoryAccessPriorityValue
                                                    }
                                                    explanation="After the DD report and Business Plan & Budget assessment are approved, confirm whether the client wants an advisory service proposal."
                                                    href="#section-advisory-service-access"
                                                    actionLabel="Next step"
                                                    onAction={(event) =>
                                                        jumpToSection(
                                                            'section-advisory-service-access',
                                                            event,
                                                        )
                                                    }
                                                />
                                            )}
                                        {client.standard_advisory && (
                                            <ActionTile
                                                icon={ListChecks}
                                                title="Standard Advisory"
                                                value={
                                                    standardAdvisoryReportStatus
                                                }
                                                explanation="Tracks questionnaire, evidence, analysis, advisory pack generation, and client report release."
                                                href="#section-standard-advisory"
                                                actionLabel="Review"
                                                onAction={(event) =>
                                                    jumpToSection(
                                                        'section-standard-advisory',
                                                        event,
                                                    )
                                                }
                                            />
                                        )}
                                        {client.is_npo && (
                                            <ActionTile
                                                icon={SlidersHorizontal}
                                                title="NPO configuration"
                                                value={npoConfigurationSummary}
                                                explanation="Review or update NPO classification, Te Tiriti mode, and social-enterprise weighting."
                                                href={
                                                    client.npo_configuration
                                                        ? '#section-npo-configuration'
                                                        : '#section-overview'
                                                }
                                                actionLabel={
                                                    client.npo_configuration
                                                        ? 'Configure'
                                                        : 'Review'
                                                }
                                                onAction={(event) =>
                                                    jumpToSection(
                                                        client.npo_configuration
                                                            ? 'section-npo-configuration'
                                                            : 'section-overview',
                                                        event,
                                                    )
                                                }
                                            />
                                        )}
                                        <ActionTile
                                            icon={Target}
                                            title="Goals"
                                            value={`${client.goals.active_goals} active`}
                                            explanation="Record goals, milestones, actions, and proof for realised platform value."
                                            href="#section-goals"
                                            actionLabel="Open"
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-goals',
                                                    event,
                                                )
                                            }
                                        />
                                        <ActionTile
                                            icon={CreditCard}
                                            title="Payment exceptions"
                                            value={
                                                paymentExceptionCount > 0
                                                    ? `${paymentExceptionCount} open`
                                                    : 'Clear'
                                            }
                                            explanation="Review failed or retrying payments only. Successful payments are hidden from this action view."
                                            href="#section-payments"
                                            actionLabel="Review"
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-payments',
                                                    event,
                                                )
                                            }
                                        />
                                        <ActionTile
                                            icon={FileText}
                                            title="Proposals"
                                            value={
                                                draftProposalCount > 0
                                                    ? `${draftProposalCount} draft`
                                                    : `${client.proposals.length} total`
                                            }
                                            explanation="Create, release, recall, or renew advisory proposals for this client."
                                            href="#section-proposals"
                                            actionLabel="Review"
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-proposals',
                                                    event,
                                                )
                                            }
                                        />
                                        {showStrategicPlanActions && (
                                            <ActionTile
                                                icon={ListChecks}
                                                title="Strategic Plan"
                                                value={
                                                    strategicPlanPriorityValue
                                                }
                                                explanation="Generate the post-acceptance strategic plan, review it with the client, then deploy milestones."
                                                href={
                                                    client.strategic_plan
                                                        ? '#section-strategic-plan'
                                                        : '#section-proposals'
                                                }
                                                actionLabel={
                                                    client.strategic_plan
                                                        ? 'Open'
                                                        : 'Generate'
                                                }
                                                onAction={(event) =>
                                                    jumpToSection(
                                                        client.strategic_plan
                                                            ? 'section-strategic-plan'
                                                            : 'section-proposals',
                                                        event,
                                                    )
                                                }
                                            />
                                        )}
                                        <ActionTile
                                            icon={MessageSquarePlus}
                                            title="Analysis"
                                            value={`${client.analysis_findings.length} findings`}
                                            explanation="Review analysis findings, add feedback, and recompute client health."
                                            href="#section-analysis"
                                            actionLabel="Review"
                                            onAction={(event) =>
                                                jumpToSection(
                                                    'section-analysis',
                                                    event,
                                                )
                                            }
                                        />
                                    </div>
                                </ClientDetailSection>

                                <ClientDetailSection
                                    title="Client status"
                                    description="Keep the top-level status signals visible before opening detailed workflow panels."
                                >
                                    <div
                                        id="section-overview"
                                        className="grid gap-4 md:grid-cols-3"
                                    >
                                        <Metric
                                            label="NZBN"
                                            value={client.nzbn ?? '-'}
                                        />
                                        <Metric label="Account">
                                            <Badge
                                                variant={statusVariant(
                                                    client.account_status,
                                                )}
                                            >
                                                {client.account_status_label}
                                            </Badge>
                                        </Metric>
                                        <Metric label="Data quality">
                                            <div id="section-questionnaire">
                                                <div id="section-documents">
                                                    <DataQualityBadge
                                                        summary={
                                                            client.data_quality_summary
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </Metric>
                                    </div>
                                </ClientDetailSection>

                                <ClientDetailSection
                                    title="Action panels"
                                    description="Client-wide operating controls sit here. Open a service tab above for Due Diligence, Business Plan & Budget, advisory access, or other service-specific actions."
                                >
                                    <GoalsPanel client={client} />

                                    <PaymentsPanel client={client} />

                                    <ProposalsPanel client={client} />

                                    <section
                                        id="section-lifecycle"
                                        className="space-y-4 rounded-md border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <RotateCcw
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                <h2 className="text-sm font-medium">
                                                    Lifecycle
                                                </h2>
                                                <Badge
                                                    variant={statusVariant(
                                                        client.status,
                                                    )}
                                                >
                                                    {client.status_label}
                                                </Badge>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Portal access is revoked while
                                                suspended.
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="lifecycle_reason">
                                                Reason
                                            </Label>
                                            <textarea
                                                id="lifecycle_reason"
                                                value={
                                                    lifecycleForm.data.reason
                                                }
                                                onChange={(event) =>
                                                    lifecycleForm.setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                                rows={3}
                                                className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <InputError
                                                message={
                                                    lifecycleForm.errors.reason
                                                }
                                            />
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {lifecycleActions(
                                                client.status,
                                            ).map((action) => {
                                                const Icon = lifecycleIcon(
                                                    action.status,
                                                );

                                                return (
                                                    <Button
                                                        key={action.status}
                                                        type="button"
                                                        variant={
                                                            action.status ===
                                                            'suspended'
                                                                ? 'destructive'
                                                                : 'outline'
                                                        }
                                                        disabled={
                                                            lifecycleForm.processing
                                                        }
                                                        onClick={() =>
                                                            submitLifecycle(
                                                                action.status,
                                                            )
                                                        }
                                                    >
                                                        <Icon
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {action.label}
                                                    </Button>
                                                );
                                            })}
                                        </div>
                                        <InputError
                                            message={
                                                lifecycleForm.errors.status
                                            }
                                        />
                                    </section>

                                    <section
                                        id="section-analysis"
                                        className="space-y-4 rounded-md border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <MessageSquarePlus
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                <h2 className="text-sm font-medium">
                                                    Analysis findings
                                                </h2>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <div
                                                    className="inline-flex rounded-md border bg-muted/30 p-1"
                                                    role="tablist"
                                                    aria-label="Filter analysis findings"
                                                >
                                                    <AnalysisFindingFilterButton
                                                        active={
                                                            analysisFindingFilter ===
                                                            'needs_review'
                                                        }
                                                        count={
                                                            analysisFindingsNeedingReview.length
                                                        }
                                                        onClick={() =>
                                                            setAnalysisFindingFilter(
                                                                'needs_review',
                                                            )
                                                        }
                                                    >
                                                        Needs review
                                                    </AnalysisFindingFilterButton>
                                                    <AnalysisFindingFilterButton
                                                        active={
                                                            analysisFindingFilter ===
                                                            'all'
                                                        }
                                                        count={
                                                            client
                                                                .analysis_findings
                                                                .length
                                                        }
                                                        onClick={() =>
                                                            setAnalysisFindingFilter(
                                                                'all',
                                                            )
                                                        }
                                                    >
                                                        All
                                                    </AnalysisFindingFilterButton>
                                                    <AnalysisFindingFilterButton
                                                        active={
                                                            analysisFindingFilter ===
                                                            'reviewed'
                                                        }
                                                        count={
                                                            reviewedAnalysisFindings.length
                                                        }
                                                        onClick={() =>
                                                            setAnalysisFindingFilter(
                                                                'reviewed',
                                                            )
                                                        }
                                                    >
                                                        Reviewed
                                                    </AnalysisFindingFilterButton>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={
                                                        recomputeHealthRadar
                                                    }
                                                >
                                                    <RotateCcw
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Recompute health
                                                </Button>
                                            </div>
                                        </div>

                                        {client.analysis_findings.length ===
                                        0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No analysis findings yet.
                                            </p>
                                        ) : visibleAnalysisFindings.length ===
                                          0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No findings in this view.
                                            </p>
                                        ) : (
                                            <div className="grid gap-2 xl:grid-cols-2">
                                                {visibleAnalysisFindings.map(
                                                    (finding) => (
                                                        <FindingFeedbackCard
                                                            key={finding.id}
                                                            finding={finding}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </section>
                                </ClientDetailSection>
                            </>
                        ) : (
                            <>
                                <ClientDetailSection
                                    title="Client information"
                                    description="Registry and engagement context used to interpret the active work."
                                >
                                    <div className="grid gap-6 lg:grid-cols-2">
                                        <section
                                            id="section-registry"
                                            className="space-y-4 rounded-md border p-4"
                                        >
                                            <h2 className="text-sm font-medium">
                                                Registry
                                            </h2>
                                            <dl className="grid gap-3 text-sm">
                                                <Detail
                                                    label="Entity"
                                                    value={client.entity_type}
                                                />
                                                <Detail
                                                    label="Filing"
                                                    value={client.filing_status}
                                                />
                                                <Detail
                                                    label="Trading"
                                                    value={client.trading_name}
                                                />
                                            </dl>
                                            <div className="flex flex-wrap gap-2">
                                                {Object.entries(
                                                    client.registry_sources,
                                                ).map(([service, badge]) => (
                                                    <Badge
                                                        key={service}
                                                        variant="secondary"
                                                    >
                                                        {service}: {badge}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </section>

                                        <section
                                            id="section-engagement"
                                            className="space-y-4 rounded-md border p-4"
                                        >
                                            <div className="flex items-center gap-2">
                                                <h2 className="text-sm font-medium">
                                                    Engagement
                                                </h2>
                                                {client.engagement_type_locked && (
                                                    <Badge variant="outline">
                                                        <LockKeyhole
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                        locked
                                                    </Badge>
                                                )}
                                            </div>
                                            <dl className="grid gap-3 text-sm">
                                                <Detail
                                                    label="Type"
                                                    value={
                                                        client.engagement_type_label
                                                    }
                                                />
                                                <Detail
                                                    label="Status"
                                                    value={client.status_label}
                                                />
                                                <Detail
                                                    label="Conflict"
                                                    value={
                                                        conflictDeclaration
                                                            ? 'declared'
                                                            : 'missing'
                                                    }
                                                />
                                                <Detail
                                                    label="Offboarding"
                                                    value={
                                                        client.offboarding
                                                            ? formatDate(
                                                                  client
                                                                      .offboarding
                                                                      .triggered_at,
                                                              )
                                                            : 'not started'
                                                    }
                                                />
                                                <Detail
                                                    label="Relationship"
                                                    value={
                                                        conflictDeclaration
                                                            ?.declaration
                                                            .existing_relationship
                                                            ? 'yes'
                                                            : 'no'
                                                    }
                                                />
                                            </dl>
                                            {client.offboarding && (
                                                <div className="flex justify-end">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={
                                                            createKnowledgeDraft
                                                        }
                                                    >
                                                        <Brain
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Draft insight
                                                    </Button>
                                                </div>
                                            )}
                                        </section>
                                    </div>
                                </ClientDetailSection>

                                <ClientDetailSection
                                    title="Decision context"
                                    description="Review health, funding, value, reports, and operating history after action work is clear."
                                >
                                    {client.npo_health && (
                                        <div id="section-npo-health">
                                            <NpoHealthPanel
                                                payload={client.npo_health}
                                            />
                                        </div>
                                    )}

                                    {client.npo_funding && (
                                        <NpoFundingPanel
                                            funding={client.npo_funding}
                                        />
                                    )}

                                    {client.npo_values && (
                                        <NpoValuePanel
                                            values={client.npo_values}
                                        />
                                    )}

                                    {client.npo_social_enterprise && (
                                        <NpoSocialEnterprisePanel
                                            summary={
                                                client.npo_social_enterprise
                                            }
                                        />
                                    )}

                                    <KnowledgeAssessmentPanel client={client} />

                                    <AccountingConnectionsPanel
                                        client={client}
                                    />

                                    <ReportsPanel client={client} />

                                    <MeetingsBriefingsPanel client={client} />

                                    {client.wellbeing_trend && (
                                        <section
                                            id="section-wellbeing"
                                            className="space-y-4 rounded-md border p-4"
                                        >
                                            <div className="flex items-center gap-2">
                                                <HeartPulse
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                <h2 className="text-sm font-medium">
                                                    Wellbeing
                                                </h2>
                                            </div>
                                            <WellbeingTrend
                                                points={client.wellbeing_trend}
                                            />
                                        </section>
                                    )}
                                </ClientDetailSection>
                            </>
                        )}
                    </>
                ) : (
                    <AdvisorServiceWorkspace
                        activeTab={selectedServiceTab}
                        client={client}
                        generatingPack={generatingPack}
                        onGenerateStandardAdvisoryPack={
                            generateStandardAdvisoryPack
                        }
                        onRunStandardAdvisoryAnalysis={
                            runStandardAdvisoryAnalysis
                        }
                    />
                )}
            </div>
        </>
    );
}
