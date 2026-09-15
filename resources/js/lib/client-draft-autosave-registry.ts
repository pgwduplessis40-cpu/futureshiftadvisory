/**
 * The client data-entry surfaces that are either draft-protected or deliberately
 * explicit actions. The matching contract test keeps new mutable portal
 * surfaces from silently skipping this decision.
 *
 * "draft" means that ordinary, reversible answers are automatically persisted.
 * "mixed" means that ordinary answers are draft-protected while the listed
 * explicit action remains a deliberate user decision (for example, upload,
 * submit, accept, or pay). "explicit" is reserved for a surface that only
 * performs a deliberate action and has no ordinary draft data to preserve.
 */
export type DraftAutosaveEvidence = {
    source: string;
    marker: string;
};

type DraftProtectedSurface = {
    source: string;
    mode: 'draft' | 'mixed';
    summary: string;
    explicitAction?: string;
    evidence: readonly DraftAutosaveEvidence[];
};

type ExplicitActionSurface = {
    source: string;
    mode: 'explicit';
    summary: string;
    explicitAction: string;
};

export type ClientDraftAutosaveSurface =
    DraftProtectedSurface | ExplicitActionSurface;

export const CLIENT_DRAFT_AUTOSAVE_SURFACES: readonly ClientDraftAutosaveSurface[] =
    [
        {
            source: 'resources/js/components/messages/ThreadedMessaging.tsx',
            mode: 'mixed',
            summary:
                'Message subjects and bodies are retained as workspace drafts.',
            explicitAction: 'Sending a message and uploading an attachment',
            evidence: [
                {
                    source: 'resources/js/components/messages/ThreadedMessaging.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/Dashboard.tsx',
            mode: 'mixed',
            summary:
                'Editable milestone and metric evidence is retained as a workspace draft.',
            explicitAction:
                'Uploading supporting documents or completing a final action',
            evidence: [
                {
                    source: 'resources/js/pages/portal/Dashboard.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/ProposalSignoff.tsx',
            mode: 'explicit',
            summary:
                'This page records a proposal election and signature, rather than ordinary draft data.',
            explicitAction:
                'Accepting or declining a proposal and any related payment',
        },
        {
            source: 'resources/js/pages/portal/ServiceActivation.tsx',
            mode: 'explicit',
            summary:
                'This page only records a fee/scope acknowledgement or payment completion.',
            explicitAction: 'Accepting service scope or confirming payment',
        },
        {
            source: 'resources/js/pages/portal/ServiceActivationRequest.tsx',
            mode: 'mixed',
            summary:
                'Service request details are retained as a workspace draft before submission.',
            explicitAction: 'Submitting the service activation request',
            evidence: [
                {
                    source: 'resources/js/pages/portal/ServiceActivationRequest.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/StrategicPlanBudget.tsx',
            mode: 'mixed',
            summary:
                'Business plan and budget edits are automatically saved as a draft.',
            explicitAction:
                'Submitting the completed plan and budget for review',
            evidence: [
                {
                    source: 'resources/js/pages/portal/StrategicPlanBudget.tsx',
                    marker: 'postDraft(serializedForm)',
                },
                {
                    source: 'resources/js/pages/portal/StrategicPlanBudget.tsx',
                    marker: 'autosaveTimer.current = window.setTimeout',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/StrategicPlanBudgetQuoteApproval.tsx',
            mode: 'explicit',
            summary:
                'The single checkbox confirms a request for FSA to prepare an add-on quote.',
            explicitAction: 'Requesting a DD plus Business Plan & Budget quote',
        },
        {
            source: 'resources/js/pages/portal/dd/BusinessPlan.tsx',
            mode: 'mixed',
            summary:
                'Due-diligence questionnaire answers are retained as a workspace draft.',
            explicitAction:
                'Uploading source documents or submitting the completed questionnaire',
            evidence: [
                {
                    source: 'resources/js/pages/portal/dd/BusinessPlan.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/Assessment.tsx',
            mode: 'explicit',
            summary:
                'This is an assessment review surface with deliberate reassessment and advisor-review actions.',
            explicitAction:
                'Starting a reassessment or saving advisor review feedback',
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/dashboard-gamification-panel.tsx',
            mode: 'explicit',
            summary:
                'This small panel only records intentional acknowledgement and preference actions.',
            explicitAction:
                'Acknowledging guidance or changing a gamification preference',
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/plan-budget-checkout.tsx',
            mode: 'explicit',
            summary:
                'This payment surface only starts or confirms a Business Plan & Budget payment.',
            explicitAction:
                'Starting or confirming a Business Plan & Budget payment',
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/plan-workspace-actions.tsx',
            mode: 'mixed',
            summary:
                'The plan workspace renders draft-protected company, idea, plan, and budget inputs.',
            explicitAction:
                'Submitting an idea for advisor review, starting a plan, or submitting it for review',
            evidence: [
                {
                    source: 'resources/js/pages/portal/entrepreneur/use-plan-workspace.ts',
                    marker: 'usePersistedWorkspaceDraft',
                },
                {
                    source: 'resources/js/pages/portal/entrepreneur/use-plan-workspace.ts',
                    marker: 'useAutoSavedForm',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/ServiceOffer.tsx',
            mode: 'explicit',
            summary:
                'This page presents a service offer for a deliberate acceptance decision.',
            explicitAction: 'Accepting the offered service',
        },
        {
            source: 'resources/js/pages/portal/entrepreneur/use-plan-workspace.ts',
            mode: 'mixed',
            summary:
                'Company name, idea validation, plan sections, and budget edits are retained before final workflow actions.',
            explicitAction:
                'Submitting for advisor review, activating a plan, or requesting advisory help',
            evidence: [
                {
                    source: 'resources/js/pages/portal/entrepreneur/use-plan-workspace.ts',
                    marker: 'usePersistedWorkspaceDraft',
                },
                {
                    source: 'resources/js/pages/portal/entrepreneur/use-plan-workspace.ts',
                    marker: 'useAutoSavedForm',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/onboarding/Step.tsx',
            mode: 'mixed',
            summary:
                'Onboarding answers are retained as a workspace draft before final submission.',
            explicitAction:
                'Uploading requested documents or completing the onboarding step',
            evidence: [
                {
                    source: 'resources/js/pages/portal/onboarding/Step.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/outcomes/Show.tsx',
            mode: 'mixed',
            summary:
                'Outcome follow-up answers are retained as a workspace draft.',
            explicitAction: 'Submitting the completed follow-up',
            evidence: [
                {
                    source: 'resources/js/pages/portal/outcomes/Show.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/surveys/Show.tsx',
            mode: 'mixed',
            summary:
                'Survey answers are saved locally and to a server-side draft while the client works.',
            explicitAction: 'Submitting the completed survey',
            evidence: [
                {
                    source: 'resources/js/pages/portal/surveys/Show.tsx',
                    marker: 'saveLocalDraft',
                },
                {
                    source: 'resources/js/pages/portal/surveys/Show.tsx',
                    marker: 'saveServerDraft',
                },
            ],
        },
        {
            source: 'resources/js/pages/portal/wellbeing/Pulse.tsx',
            mode: 'mixed',
            summary:
                'Wellbeing check-in answers are retained as a workspace draft.',
            explicitAction: 'Submitting the completed wellbeing check-in',
            evidence: [
                {
                    source: 'resources/js/pages/portal/wellbeing/Pulse.tsx',
                    marker: 'usePersistedWorkspaceDraft',
                },
            ],
        },
    ];
