import { router, useForm } from '@inertiajs/react';
import {
    FileSpreadsheet,
    ListChecks,
    ShieldAlert,
    SlidersHorizontal,
    Star,
    Target,
    TrendingUp,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import type { MouseEvent } from 'react';
import { toast } from 'sonner';
import { useDrillFocus } from '@/hooks/use-drill-focus';
import { ClientDetailLayout } from './client-detail-layout';
import type {
    AnalysisFindingFilter,
    ClientDetail,
    ClientDetailTab,
    LifecycleForm,
    Props,
    StandardAdvisoryGeneratePayload,
} from './client-detail-types';
import {
    clientSectionServiceTabs,
    clientSectionTabs,
} from './client-detail-types';
import type {
    AdvisorServiceTab,
    AdvisorServiceTabKey,
} from './service-workspaces';

export default function ClientsShow({
    client,
    serviceWorkspaces,
    conflictDeclaration,
    screenShare,
    coBrowse,
}: Props) {
    useDrillFocus();
    const [activeTab, setActiveTab] = useState<ClientDetailTab>(() =>
        initialClientDetailTab(),
    );
    const [activeServiceTab, setActiveServiceTab] =
        useState<AdvisorServiceTabKey>(() => initialAdvisorServiceTab(client));
    const [generatingPack, setGeneratingPack] = useState(false);
    const [analysisFindingFilter, setAnalysisFindingFilter] =
        useState<AnalysisFindingFilter>('needs_review');

    const lifecycleForm = useForm<LifecycleForm>({
        status: client.status,
        reason: '',
    });

    const submitLifecycle = (status: string) => {
        lifecycleForm.setData('status', status);
        lifecycleForm.transform((data) => ({
            ...data,
            status,
        }));
        lifecycleForm.patch(client.lifecycle_update_url, {
            preserveScroll: true,
            onFinish: () =>
                lifecycleForm.transform((data) => ({
                    ...data,
                })),
        });
    };

    const resendInvite = () => {
        if (!client.invitation?.resend_url) {
            return;
        }

        router.post(client.invitation.resend_url, {}, { preserveScroll: true });
    };

    const cancelInvite = () => {
        if (!client.invitation?.cancel_url) {
            return;
        }

        if (
            !window.confirm(
                `Cancel the pending invite for ${client.invitation.email}? The current invite link will stop working.`,
            )
        ) {
            return;
        }

        router.delete(client.invitation.cancel_url, { preserveScroll: true });
    };

    const recomputeHealthRadar = () => {
        router.post(
            client.business_health_recompute_url,
            {},
            { preserveScroll: true },
        );
    };

    const runStandardAdvisoryAnalysis = () => {
        if (!client.standard_advisory?.can_run_analysis) {
            return;
        }

        router.post(
            client.standard_advisory.run_analysis_url,
            {},
            { preserveScroll: true },
        );
    };

    const generateStandardAdvisoryPack = (
        payload: StandardAdvisoryGeneratePayload = {},
    ) => {
        const summary = client.standard_advisory;
        const hasWaiverPayload =
            (payload.waiver_modules?.length ?? 0) > 0 &&
            (payload.waiver_reason?.trim() ?? '') !== '';

        if (!summary || (!summary.can_generate_pack && !hasWaiverPayload)) {
            return;
        }

        router.post(summary.generate_pack_url, payload, {
            preserveScroll: true,
            onStart: () => setGeneratingPack(true),
            onFinish: () => setGeneratingPack(false),
            onError: (errors) => {
                const message =
                    errors.standard_advisory ??
                    'The advisory pack could not be generated.';

                toast.error(message);
            },
        });
    };

    const createKnowledgeDraft = () => {
        if (!client.offboarding) {
            return;
        }

        router.post(
            client.knowledge_draft_store_url,
            {},
            { preserveScroll: true },
        );
    };

    const jumpToSection = (sectionId: string, event?: MouseEvent<Element>) => {
        event?.preventDefault();
        const serviceTab = clientSectionServiceTabs[sectionId];

        if (serviceTab && advisorServiceTabAvailable(client, serviceTab)) {
            setActiveServiceTab(serviceTab);
        }

        setActiveTab(clientSectionTabs[sectionId] ?? 'actions');

        window.setTimeout(() => {
            const section = document.getElementById(sectionId);

            if (!section) {
                return;
            }

            if (!section.hasAttribute('tabindex')) {
                section.setAttribute('tabindex', '-1');
            }

            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.focus({ preventScroll: true });
            window.history.replaceState(null, '', `#${sectionId}`);
        }, 0);
    };

    const paymentExceptionCount = client.payments.filter((payment) =>
        ['failed', 'retrying'].includes(payment.status),
    ).length;
    const analysisFindingsNeedingReview = client.analysis_findings.filter(
        (finding) => finding.feedback_count === 0,
    );
    const reviewedAnalysisFindings = client.analysis_findings.filter(
        (finding) => finding.feedback_count > 0,
    );
    const visibleAnalysisFindings =
        analysisFindingFilter === 'needs_review'
            ? analysisFindingsNeedingReview
            : analysisFindingFilter === 'reviewed'
              ? reviewedAnalysisFindings
              : client.analysis_findings;
    const draftProposalCount = client.proposals.filter((proposal) =>
        ['draft', 'generated'].includes(proposal.status),
    ).length;
    const npoConfigurationSummary = client.npo_configuration
        ? [
              client.npo_configuration.legal_structure_label,
              client.npo_configuration.tiriti_mode_label,
          ]
              .filter(Boolean)
              .join(' / ')
        : 'Not configured';
    const standardAdvisoryReportStatus =
        client.standard_advisory?.reports.client?.review_status === 'reviewed'
            ? 'Released'
            : client.standard_advisory?.reports.client?.review_status ===
                'pending_review'
              ? 'Awaiting release'
              : client.standard_advisory?.status_label;
    const strategicBudgetPriorityValue = client.strategic_budget.locked
        ? 'Financials needed'
        : client.strategic_budget.status === 'advisor_approved'
          ? 'Approved'
          : `${client.strategic_budget.readiness_score}/100 ready`;
    const strategicBudgetActionLabel =
        client.strategic_budget.status === 'submitted_for_review'
            ? 'Assess'
            : 'Review';
    const dueDiligencePriorityValue = client.due_diligence
        ? client.due_diligence.assessment_ready
            ? 'Assessed'
            : client.due_diligence.report_url
              ? client.due_diligence.assessment_status_label
              : 'Report needed'
        : null;
    const signedProposal = client.proposals.find(
        (proposal) => proposal.status === 'signed',
    );
    const isDueDiligenceClient = isDueDiligenceClientFor(client);
    const advisoryAccessReady =
        isDueDiligenceClient &&
        (client.due_diligence?.assessment_ready ?? false) &&
        [
            'advisor_approved',
            'used_in_proposal',
            'accepted_proposal_snapshot',
        ].includes(client.strategic_budget.status);
    const advisoryAccessPriorityValue = advisoryAccessReady
        ? 'Ready to scope'
        : 'After approvals';
    const strategicPlanPriorityValue = client.strategic_plan
        ? client.strategic_plan.status === 'deployed'
            ? `${client.strategic_plan.progress_percent}% progressing`
            : client.strategic_plan_deployment_guard.allowed
              ? 'Ready to deploy'
              : 'Assessment locked'
        : signedProposal
          ? 'Ready to generate'
          : 'After acceptance';
    const showStrategicPlanActions =
        !isDueDiligenceClient &&
        Boolean(client.strategic_plan || signedProposal);
    const showStrategicPlanServiceTab =
        !isDueDiligenceClient && Boolean(client.strategic_plan);
    const advisorServiceTabs: AdvisorServiceTab[] = [
        {
            key: 'overview',
            label: 'Overview',
            description: 'Client-wide actions and information.',
            status: client.status_label,
            icon: Target,
        },
        ...(client.due_diligence
            ? [
                  {
                      key: 'due_diligence' as const,
                      label: 'Due Diligence',
                      description:
                          'Target, evidence, data room, assessment, and DD report.',
                      status: dueDiligencePriorityValue ?? 'Open',
                      icon: ShieldAlert,
                  },
              ]
            : []),
        {
            key: 'business_plan_budget',
            label: 'Business Plan & Budget',
            description:
                'Plan assessment, budget confidence, financial evidence, and advisor approval.',
            status: strategicBudgetPriorityValue,
            icon: FileSpreadsheet,
        },
        ...(isDueDiligenceClient && client.due_diligence
            ? [
                  {
                      key: 'advisory_access' as const,
                      label: 'Advisory Access',
                      description:
                          'Request or scope advisory service after DD and BP&B approval.',
                      status: advisoryAccessPriorityValue,
                      icon: TrendingUp,
                  },
              ]
            : []),
        ...(client.standard_advisory
            ? [
                  {
                      key: 'standard_advisory' as const,
                      label: 'Standard Advisory',
                      description:
                          'Questionnaire, evidence, analysis, and advisory report pack.',
                      status: standardAdvisoryReportStatus,
                      icon: ListChecks,
                  },
              ]
            : []),
        ...(client.founding_advisory
            ? [
                  {
                      key: 'founding_advisory' as const,
                      label: 'Founding Advisory',
                      description:
                          'Founder roadmap, replan decisions, and transition review.',
                      status: client.founding_advisory.status_label,
                      icon: Star,
                  },
              ]
            : []),
        ...(client.is_npo ||
        client.npo_conversion ||
        client.npo_governance_review ||
        client.npo_configuration ||
        client.npo_health ||
        client.npo_funding ||
        client.npo_values ||
        client.npo_social_enterprise
            ? [
                  {
                      key: 'npo' as const,
                      label: 'NPO',
                      description:
                          'NPO conversion, governance, funding, value, and social enterprise context.',
                      status: npoConfigurationSummary,
                      icon: SlidersHorizontal,
                  },
              ]
            : []),
        ...(showStrategicPlanServiceTab
            ? [
                  {
                      key: 'strategic_plan' as const,
                      label: 'Strategic Plan',
                      description:
                          'Post-acceptance strategic plan generation and milestones.',
                      status: strategicPlanPriorityValue,
                      icon: Trophy,
                  },
              ]
            : []),
    ];
    const selectedServiceTab = advisorServiceTabs.some(
        (tab) => tab.key === activeServiceTab,
    )
        ? activeServiceTab
        : 'overview';
    const selectAdvisorServiceTab = (tab: AdvisorServiceTabKey) => {
        setActiveServiceTab(tab);

        if (tab !== 'overview') {
            setActiveTab('actions');
        }
    };

    return (
        <ClientDetailLayout
            client={client}
            serviceWorkspaces={serviceWorkspaces}
            conflictDeclaration={conflictDeclaration}
            screenShare={screenShare}
            coBrowse={coBrowse}
            activeTab={activeTab}
            setActiveTab={setActiveTab}
            advisorServiceTabs={advisorServiceTabs}
            selectedServiceTab={selectedServiceTab}
            activeWorkspaceKey={
                selectedServiceTab === 'due_diligence'
                    ? 'due_diligence'
                    : selectedServiceTab === 'business_plan_budget'
                      ? 'dd_plan_budget'
                      : serviceWorkspaces.active_key
            }
            selectAdvisorServiceTab={selectAdvisorServiceTab}
            generatingPack={generatingPack}
            analysisFindingFilter={analysisFindingFilter}
            setAnalysisFindingFilter={setAnalysisFindingFilter}
            lifecycleForm={lifecycleForm}
            submitLifecycle={submitLifecycle}
            resendInvite={resendInvite}
            cancelInvite={cancelInvite}
            recomputeHealthRadar={recomputeHealthRadar}
            createKnowledgeDraft={createKnowledgeDraft}
            jumpToSection={jumpToSection}
            paymentExceptionCount={paymentExceptionCount}
            analysisFindingsNeedingReview={analysisFindingsNeedingReview}
            reviewedAnalysisFindings={reviewedAnalysisFindings}
            visibleAnalysisFindings={visibleAnalysisFindings}
            draftProposalCount={draftProposalCount}
            npoConfigurationSummary={npoConfigurationSummary}
            strategicBudgetPriorityValue={strategicBudgetPriorityValue}
            strategicBudgetActionLabel={strategicBudgetActionLabel}
            dueDiligencePriorityValue={dueDiligencePriorityValue}
            isDueDiligenceClient={isDueDiligenceClient}
            advisoryAccessPriorityValue={advisoryAccessPriorityValue}
            strategicPlanPriorityValue={strategicPlanPriorityValue}
            standardAdvisoryReportStatus={standardAdvisoryReportStatus}
            showStrategicPlanActions={showStrategicPlanActions}
            runStandardAdvisoryAnalysis={runStandardAdvisoryAnalysis}
            generateStandardAdvisoryPack={generateStandardAdvisoryPack}
        />
    );
}

function initialClientDetailTab(): ClientDetailTab {
    if (typeof window === 'undefined') {
        return 'actions';
    }

    return clientSectionTabs[window.location.hash.slice(1)] ?? 'actions';
}

function initialAdvisorServiceTab(client: ClientDetail): AdvisorServiceTabKey {
    if (typeof window === 'undefined') {
        return 'overview';
    }

    const serviceTab = clientSectionServiceTabs[window.location.hash.slice(1)];

    return serviceTab && advisorServiceTabAvailable(client, serviceTab)
        ? serviceTab
        : 'overview';
}

function advisorServiceTabAvailable(
    client: ClientDetail,
    tab: AdvisorServiceTabKey,
): boolean {
    if (tab === 'overview') {
        return true;
    }

    if (tab === 'due_diligence') {
        return client.due_diligence !== null;
    }

    if (tab === 'business_plan_budget') {
        return Boolean(client.strategic_budget);
    }

    if (tab === 'advisory_access') {
        return isDueDiligenceClientFor(client) && client.due_diligence !== null;
    }

    if (tab === 'standard_advisory') {
        return client.standard_advisory !== null;
    }

    if (tab === 'founding_advisory') {
        return client.founding_advisory !== null;
    }

    if (tab === 'npo') {
        return Boolean(
            client.is_npo ||
            client.npo_conversion ||
            client.npo_governance_review ||
            client.npo_configuration ||
            client.npo_health ||
            client.npo_funding ||
            client.npo_values ||
            client.npo_social_enterprise,
        );
    }

    return !isDueDiligenceClientFor(client) && Boolean(client.strategic_plan);
}

function isDueDiligenceClientFor(client: ClientDetail): boolean {
    return (
        client.engagement_type === 'due_diligence' ||
        client.due_diligence !== null
    );
}

ClientsShow.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};
