import { NpoHealthPanel } from '@/components/npo/NpoHealthPanel';
import { StrategicPlanPanel } from './client-detail-finance';
import { FoundingAdvisoryPanel } from './client-detail-founding';
import { ClientDetailSection } from './client-detail-navigation';
import {
    NpoConfigurationPanel,
    NpoConversionPanel,
    NpoFundingPanel,
    NpoGovernanceReviewPanel,
    NpoSocialEnterprisePanel,
    NpoValuePanel,
} from './client-detail-npo';
import {
    AdvisoryServiceAccessPanel,
    ProposalsPanel,
} from './client-detail-proposals';
import { StandardAdvisoryPanel } from './client-detail-standard-advisory';
import type {
    ClientDetail,
    StandardAdvisoryGeneratePayload,
} from './client-detail-types';
import type { AdvisorServiceTabKey } from './service-workspaces';
import {
    BusinessPlanBudgetActionPanel,
    DueDiligenceTargetPanel,
    StrategicBudgetPanel,
} from './service-workspaces';
export function AdvisorServiceWorkspace({
    activeTab,
    client,
    generatingPack,
    onGenerateStandardAdvisoryPack,
    onRunStandardAdvisoryAnalysis,
}: {
    activeTab: AdvisorServiceTabKey;
    client: ClientDetail;
    generatingPack: boolean;
    onGenerateStandardAdvisoryPack: (
        payload?: StandardAdvisoryGeneratePayload,
    ) => void;
    onRunStandardAdvisoryAnalysis: () => void;
}) {
    if (activeTab === 'due_diligence' && client.due_diligence) {
        return (
            <ClientDetailSection
                title="Due Diligence workspace"
                description="Keep the DD target, evidence, data room, assessment, and report actions together."
            >
                <DueDiligenceTargetPanel
                    payload={client.due_diligence}
                    reportStoreUrl={client.report_store_url}
                />
            </ClientDetailSection>
        );
    }

    if (activeTab === 'business_plan_budget') {
        return (
            <ClientDetailSection
                title="Business Plan & Budget workspace"
                description="Assess the DD-sourced plan, budget confidence, financial evidence, funding assumptions, and advisor approval in one place."
            >
                <BusinessPlanBudgetActionPanel
                    budget={client.strategic_budget}
                />
                <StrategicBudgetPanel budget={client.strategic_budget} />
            </ClientDetailSection>
        );
    }

    if (
        activeTab === 'advisory_access' &&
        isDueDiligenceClient(client) &&
        client.due_diligence
    ) {
        return (
            <ClientDetailSection
                title="Advisory access workspace"
                description="After DD and Business Plan & Budget are approved, manage whether the client requests advisory service access."
            >
                <AdvisoryServiceAccessPanel
                    client={client}
                    budget={client.strategic_budget}
                />
                <ProposalsPanel client={client} />
            </ClientDetailSection>
        );
    }

    if (activeTab === 'standard_advisory' && client.standard_advisory) {
        return (
            <ClientDetailSection
                title="Standard Advisory workspace"
                description="Review questionnaire evidence, analysis modules, advisory pack readiness, and report release."
            >
                <StandardAdvisoryPanel
                    summary={client.standard_advisory}
                    onRunAnalysis={onRunStandardAdvisoryAnalysis}
                    onGeneratePack={onGenerateStandardAdvisoryPack}
                    generatingPack={generatingPack}
                />
            </ClientDetailSection>
        );
    }

    if (activeTab === 'founding_advisory' && client.founding_advisory) {
        return (
            <ClientDetailSection
                title="Founding Advisory workspace"
                description="Review founder roadmap state, replanning, and transition-readiness actions."
            >
                <FoundingAdvisoryPanel summary={client.founding_advisory} />
            </ClientDetailSection>
        );
    }

    if (activeTab === 'npo') {
        const hasNpoPanels = Boolean(
            client.npo_conversion ||
            client.npo_governance_review ||
            client.npo_configuration ||
            client.npo_health ||
            client.npo_funding ||
            client.npo_values ||
            client.npo_social_enterprise,
        );

        return (
            <ClientDetailSection
                title="NPO workspace"
                description="Keep NPO conversion, governance, funding, value, and social-enterprise decisions together."
            >
                {client.npo_conversion && (
                    <NpoConversionPanel conversion={client.npo_conversion} />
                )}

                {client.npo_governance_review && (
                    <NpoGovernanceReviewPanel
                        summary={client.npo_governance_review}
                    />
                )}

                {client.npo_configuration && (
                    <NpoConfigurationPanel
                        configuration={client.npo_configuration}
                    />
                )}

                {client.npo_health && (
                    <div id="section-npo-health">
                        <NpoHealthPanel payload={client.npo_health} />
                    </div>
                )}

                {client.npo_funding && (
                    <NpoFundingPanel funding={client.npo_funding} />
                )}

                {client.npo_values && (
                    <NpoValuePanel values={client.npo_values} />
                )}

                {client.npo_social_enterprise && (
                    <NpoSocialEnterprisePanel
                        summary={client.npo_social_enterprise}
                    />
                )}

                {!hasNpoPanels && (
                    <p className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                        No NPO-specific panels are ready for this client yet.
                    </p>
                )}
            </ClientDetailSection>
        );
    }

    if (activeTab === 'strategic_plan' && !isDueDiligenceClient(client)) {
        return (
            <ClientDetailSection
                title="Strategic Plan workspace"
                description="Use this only for clients whose accepted advisory proposal unlocks strategic-plan work."
            >
                <StrategicPlanPanel
                    plan={client.strategic_plan}
                    deploymentGuard={client.strategic_plan_deployment_guard}
                />
            </ClientDetailSection>
        );
    }

    return (
        <ClientDetailSection
            title="Service workspace"
            description="This service is not available for the client yet."
        >
            <p className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                Select another service tab or return to Overview.
            </p>
        </ClientDetailSection>
    );
}

function isDueDiligenceClient(client: ClientDetail): boolean {
    return (
        client.engagement_type === 'due_diligence' ||
        client.due_diligence !== null
    );
}
