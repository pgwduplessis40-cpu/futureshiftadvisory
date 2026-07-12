import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Bot,
    ChevronDown,
    ChevronUp,
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    MessageSquare,
    Pencil,
    RefreshCw,
    RotateCcw,
    Send,
    Trash2,
    Trophy,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type {
    ComponentType,
    Dispatch,
    FormEvent,
    ReactNode,
    SetStateAction,
} from 'react';
import { BudgetCashChart } from '@/components/budget-cash-chart';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ProfilePayload = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
};

type IdeaValidationPayload = {
    id: string;
    revision_number: number;
    problem: string;
    target_customer: string;
    solution: string;
    value_proposition: string;
    demand_signal: string;
    revenue_model: string;
    summary: string;
    viability_alerts: {
        message?: string;
        severity?: string;
    }[];
    evaluated_at: string | null;
    advisor_gate_status:
        | 'approved'
        | 'changes_requested'
        | 'recalled'
        | 'advisor_review'
        | string;
    change_request_note: string | null;
    changes_requested_at: string | null;
    recalled_at: string | null;
    restored_from_revision_number: number | null;
    advisor_gate_passed_at: string | null;
    advisor_gate_note: string | null;
    plan_builder_unlocked: boolean;
} | null;

type IdeaValidationVersion = {
    id: string;
    revision_number: number;
    problem: string;
    target_customer: string;
    demand_signal: string;
    evaluated_at: string | null;
    advisor_gate_status: string;
    recalled_at: string | null;
    is_current: boolean;
    restore_url: string;
};

type IdeaValidationForm = {
    problem: string;
    target_customer: string;
    solution: string;
    value_proposition: string;
    demand_signal: string;
    revenue_model: string;
};

type BusinessPlanPayload = {
    id: string;
    title: string;
    status: string;
    completed_at: string | null;
    updated_at: string | null;
    requirements_complete: boolean;
    missing_requirements: string[];
    budget: BudgetPayload;
    latest_assessment: {
        id: string;
        round: number;
        status: string;
        overall_grade: string;
        finalised_at: string | null;
        url: string;
    } | null;
    phases: PlanPhasePayload[];
} | null;

type PlanPhasePayload = {
    id: string;
    key: string;
    title: string;
    status: string;
    requirements: PlanRequirementPayload[];
    sections: PlanSectionPayload[];
};

type PlanTemplatePhasePayload = {
    key: string;
    title: string;
    requirements: PlanRequirementPayload[];
};

type PlanRequirementPayload = {
    key: string;
    phase_key: string;
    phase_title: string;
    title: string;
    description: string;
    type?: string;
    complete: boolean;
    section_id: string | null;
    section_title?: string | null;
};

type BudgetRow = {
    label: string;
    amount: string | number;
    quantity?: string | number;
    month?: string | number;
    monthly_growth_percent?: string | number;
    variable_cost_percent?: string | number;
    unit_cost?: string | number;
    gross_profit_percent?: string | number;
    confidence?: 'known' | 'estimate' | 'guess';
    description?: string;
};

type BudgetAssumptions = {
    revenue_growth_percent: string | number;
    cost_inflation_percent: string | number;
    target_gross_profit_percent: string | number;
    target_net_profit_before_tax_percent: string | number;
    target_net_profit_after_tax_percent: string | number;
};

type FutureCostRow = BudgetRow & {
    year?: string | number;
    recurring?: boolean;
};

type FundingScenarioRow = {
    name: string;
    type: 'bank_loan' | 'investor' | 'mixed';
    amount: string | number;
    year?: string | number;
    interest_rate_percent?: string | number;
    term_years?: string | number;
    interest_only_months?: string | number;
    investor_equity_percent?: string | number;
    confidence?: 'known' | 'estimate' | 'guess';
};

type BudgetPayload = {
    id: string | null;
    expected_runway_months: number | null;
    forecast_years: number;
    status: string;
    assumptions: Partial<BudgetAssumptions> & {
        company_tax_rate_percent?: number;
        company_tax_configured?: boolean;
        field_labels?: Record<string, string>;
        missing_fields?: string[];
    };
    launch_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    future_costs: FutureCostRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
    funding_scenarios: FundingScenarioRow[];
    computed: {
        forecast_years?: number;
        total_launch_costs?: number;
        monthly_fixed_costs?: number;
        total_funding?: number;
        available_after_launch?: number;
        runway_months?: number | null;
        runway_open_ended?: boolean;
        break_even_month?: number | null;
        break_even_year?: number | null;
        first_profitable_year?: number | null;
        cash_flow_positive_year?: number | null;
        break_even_reached?: boolean;
        annual_totals?: {
            year: number;
            revenue: number;
            gross_profit_percent: number | null;
            net_profit_before_tax_percent: number | null;
            net_profit_after_tax_percent: number | null;
        }[];
        assumptions?: Partial<BudgetAssumptions> & {
            company_tax_rate_percent?: number;
            company_tax_configured?: boolean;
            field_labels?: Record<string, string>;
            missing_fields?: string[];
        };
        missing_assumptions?: string[];
        explanations?: Record<string, string>;
        monthly_series?: {
            month: number;
            revenue: number;
            variable_costs: number;
            fixed_costs: number;
            net_cash_flow: number;
            cumulative_cash: number;
        }[];
        populated_inputs?: Record<string, number>;
    };
    flags: BudgetFlag[];
    active_flags: BudgetFlag[];
    advisor_line_nudge_seen_at: string | null;
    pack_available: boolean;
    budget_pack_url: string | null;
    budget_pack_pdf_url: string | null;
};

type BudgetFlag = {
    key: string;
    title: string;
    message: string;
    severity: string;
    first_raised_at?: string;
    acknowledged_at?: string | null;
};

type BudgetFormState = {
    expected_runway_months: string;
    forecast_years: string;
    assumptions: BudgetAssumptions;
    launch_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    future_costs: FutureCostRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
    funding_scenarios: FundingScenarioRow[];
};

type BudgetSetupMode = 'guided' | 'advanced';

type BudgetGroupKey = keyof Pick<
    BudgetFormState,
    | 'launch_costs'
    | 'monthly_fixed_costs'
    | 'future_costs'
    | 'revenue_forecast'
    | 'funding_sources'
>;

type PlanSectionPayload = {
    id: string;
    title: string;
    body: string;
    source_type: string;
    completeness_status: string;
    attached_document_ids: string[];
    predictive_score: {
        score?: number;
        band?: string;
        gaps?: string[];
        reasons?: string[];
    } | null;
    guidance: {
        summary?: string;
        ai_summary?: string;
        predictive_score?: {
            score?: number;
            band?: string;
            gaps?: string[];
        };
    } | null;
    requirement_key: string | null;
    guidance_url: string;
};

type ReportPayload = {
    id: string;
    title: string;
    type: string;
    generated_at: string | null;
    view_url: string;
    download_url: string;
};

type AdvisoryRequestPayload = {
    available: boolean;
    requested: boolean;
    request_url: string;
    thread_url: string | null;
    blockers: string[];
};

type GamificationPayload = {
    enabled: boolean;
    disable_request_url: string;
    disable_request_requested: boolean;
    disable_request_thread_url: string | null;
    current_level?: {
        stage?: string;
        phase?: number | null;
        label: string;
    };
    plan_completion?: {
        total: number;
        completed: number;
        percent: number;
    };
    current_streak?: number;
    new_badge_count?: number;
    next_milestone?: {
        label: string;
        progress_percent: number;
    } | null;
};

type PackageAccessPayload = {
    package_scope: 'idea_validation' | 'plan_budget' | 'combo';
    package_scope_label: string;
    package_label: string;
    includes_idea_validation: boolean;
    includes_plan_budget: boolean;
    included_stages: string[];
    client_outcomes: string[];
    source_activation_id: string | null;
};

type Props = {
    profile: ProfilePayload;
    packageAccess: PackageAccessPayload;
    ideaValidation: IdeaValidationPayload;
    ideaValidationVersions: IdeaValidationVersion[];
    plan: BusinessPlanPayload;
    planTemplate: PlanTemplatePhasePayload[];
    reports: ReportPayload[];
    advisoryRequest: AdvisoryRequestPayload;
    gamification: GamificationPayload;
    urls: {
        dashboard: string;
        ideaValidation: string;
        recallIdeaValidation: string;
        startPlan: string;
        sectionStore: string;
        budgetUpdate: string;
        budgetPack: string;
        budgetPackPdf: string;
        budgetFlagAcknowledge: string;
        budgetAdvisorNudgeDismiss: string;
        assistRequirement: string;
        preview: string;
        submit: string;
        documentUpload: string;
        messages: string;
        advisoryRequest: string;
    };
};

type Tab = 'actions' | 'information';

export default function EntrepreneurPlan({
    profile,
    packageAccess,
    ideaValidation,
    ideaValidationVersions,
    plan,
    planTemplate,
    reports,
    advisoryRequest,
    gamification,
    urls,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const ideaForm = useForm<IdeaValidationForm>({
        problem: ideaValidation?.problem ?? '',
        target_customer: ideaValidation?.target_customer ?? '',
        solution: ideaValidation?.solution ?? '',
        value_proposition: ideaValidation?.value_proposition ?? '',
        demand_signal: ideaValidation?.demand_signal ?? '',
        revenue_model: ideaValidation?.revenue_model ?? '',
    });
    const [showValidatedIdeaForm, setShowValidatedIdeaForm] = useState(false);
    const [recallingIdea, setRecallingIdea] = useState(false);
    const [restoringIdeaVersionId, setRestoringIdeaVersionId] = useState<
        string | null
    >(null);
    const phases = plan?.phases ?? planTemplate;
    const requirements = useMemo(
        () => phases.flatMap((phase) => phase.requirements),
        [phases],
    );
    const firstMissingRequirement =
        requirements.find((requirement) => !requirement.complete) ??
        requirements[0] ??
        null;
    const [selectedKey, setSelectedKey] = useState<string | null>(
        firstMissingRequirement ? requirementId(firstMissingRequirement) : null,
    );
    const selectedRequirement =
        requirements.find(
            (requirement) => requirementId(requirement) === selectedKey,
        ) ??
        firstMissingRequirement ??
        null;
    const selectedSection = selectedRequirement
        ? findSection(plan, selectedRequirement)
        : null;
    const completedRequirementCount = requirements.filter(
        (requirement) => requirement.complete,
    ).length;
    const totalRequirementCount = requirements.length;
    const planCompletion = gamification.plan_completion ?? {
        total: totalRequirementCount,
        completed: completedRequirementCount,
        percent:
            totalRequirementCount > 0
                ? Math.round(
                      (completedRequirementCount / totalRequirementCount) * 100,
                  )
                : 0,
    };
    const selectedCompletionPercent =
        totalRequirementCount > 0 && selectedRequirement
            ? Math.round(
                  ((completedRequirementCount +
                      (selectedRequirement.complete ? 0 : 1)) /
                      totalRequirementCount) *
                      100,
              )
            : planCompletion.percent;
    const includesIdeaValidation = packageAccess.includes_idea_validation;
    const includesPlanBudget = packageAccess.includes_plan_budget;
    const directPlanAccess = includesPlanBudget && !includesIdeaValidation;
    const hasIdeaValidation = Boolean(ideaValidation);
    const planBuilderUnlocked =
        directPlanAccess || Boolean(ideaValidation?.plan_builder_unlocked);
    const ideaValidationApproved = Boolean(
        ideaValidation?.advisor_gate_passed_at ||
        ideaValidation?.plan_builder_unlocked,
    );
    const ideaChangesRequested =
        ideaValidation?.advisor_gate_status === 'changes_requested';
    const ideaValidationRecalled = Boolean(ideaValidation?.recalled_at);
    const ideaUnderAdvisorReview =
        hasIdeaValidation &&
        !ideaValidationApproved &&
        !ideaChangesRequested &&
        !ideaValidationRecalled;
    const showIdeaValidationEditor =
        !hasIdeaValidation ||
        ideaChangesRequested ||
        ideaValidationRecalled ||
        (ideaValidationApproved && showValidatedIdeaForm);
    const ideaValidationSummary = ideaFields.map((field) => ({
        label: field.label,
        value: ideaValidation?.[field.key as keyof IdeaValidationForm] ?? '-',
    }));
    const hasPlan = Boolean(plan);
    const nextSmallWin =
        includesIdeaValidation && !hasIdeaValidation
            ? {
                  badge: 'Step 1',
                  title: 'Complete idea validation',
                  body: 'Answer the idea validation questions first. Your advisor reviews this before the plan sections open.',
                  action: 'Start idea validation',
              }
            : includesIdeaValidation && ideaChangesRequested
              ? {
                    badge: 'Step 1',
                    title: 'Revise idea validation',
                    body: 'Your advisor has requested changes. Update the idea validation and resubmit it for review.',
                    action: 'Revise idea validation',
                }
              : includesIdeaValidation && ideaValidationRecalled
                ? {
                      badge: 'Step 1',
                      title: 'Revise idea validation',
                      body: 'Your validation has been recalled from advisor review. Update it, then resubmit it for review.',
                      action: 'Revise idea validation',
                  }
                : includesIdeaValidation && !planBuilderUnlocked
                  ? {
                        badge: 'Step 2',
                        title: 'Advisor review',
                        body: 'Idea validation is submitted. Your advisor needs to approve it before the plan sections open.',
                        action: null,
                    }
                  : includesPlanBudget && !hasPlan
                    ? {
                          badge: includesIdeaValidation ? 'Step 3' : 'Step 1',
                          title: 'Start the business plan',
                          body: includesIdeaValidation
                              ? 'Idea validation is approved. Start the plan to unlock section-by-section guidance and AI assist.'
                              : 'Your package opens the business plan and budget workspace directly.',
                          action: 'Start plan',
                      }
                    : includesPlanBudget
                      ? {
                            badge: `${planCompletion.completed}/${planCompletion.total} sections`,
                            title: 'Next plan section',
                            body: selectedRequirement
                                ? selectedRequirement.complete
                                    ? 'This section is already complete. Choose the next needed section when you are ready.'
                                    : `Focus on "${selectedRequirement.title}" first, then save it to move the plan to ${selectedCompletionPercent}%.`
                                : 'Select one requirement and complete that section first.',
                            action: null,
                        }
                      : {
                            badge: packageAccess.package_scope_label,
                            title: hasIdeaValidation
                                ? 'Idea validation submitted'
                                : 'Complete idea validation',
                            body: hasIdeaValidation
                                ? 'Your advisor can review the validation and provide gate feedback for this package.'
                                : 'Answer the idea validation questions to test the concept before investing in detailed plan work.',
                            action: hasIdeaValidation
                                ? null
                                : 'Start idea validation',
                        };
    const [sectionTitle, setSectionTitle] = useState('');
    const [sectionBody, setSectionBody] = useState('');
    const [supportingFile, setSupportingFile] = useState<File | null>(null);
    const [supportingKey, setSupportingKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [savingSection, setSavingSection] = useState(false);
    const [assistingSection, setAssistingSection] = useState(false);
    const [assistantNotice, setAssistantNotice] = useState<string | null>(null);
    const [budgetForm, setBudgetForm] = useState<BudgetFormState>(() =>
        budgetToForm(plan?.budget),
    );
    const [savingBudget, setSavingBudget] = useState(false);

    useEffect(() => {
        if (!selectedRequirement) {
            return;
        }

        const section = findSection(plan, selectedRequirement);
        // Intentionally sync the editable form state to the selected
        // requirement (and re-sync when the plan refreshes after a save).
        /* eslint-disable react-hooks/set-state-in-effect */
        setSectionTitle(section?.title ?? selectedRequirement.title);
        setSectionBody(section?.body ?? '');
        setSupportingFile(null);
        setSupportingKey((key) => key + 1);
        setSectionError(null);
        setAssistantNotice(null);
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [selectedRequirement, plan]);

    useEffect(() => {
        // Keep the editable budget form aligned with Inertia refreshes after save.
        /* eslint-disable-next-line react-hooks/set-state-in-effect */
        setBudgetForm(budgetToForm(plan?.budget));
    }, [plan?.budget]);

    useEffect(() => {
        // Keep the idea form aligned with the latest submitted validation.
        ideaForm.setData(ideaValidationToForm(ideaValidation));
        ideaForm.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        ideaValidation?.id,
        ideaValidation?.problem,
        ideaValidation?.target_customer,
        ideaValidation?.solution,
        ideaValidation?.value_proposition,
        ideaValidation?.demand_signal,
        ideaValidation?.revenue_model,
    ]);

    const validateIdeaForm = () => {
        let valid = true;
        ideaForm.clearErrors();

        for (const field of ideaFields) {
            const value = ideaForm.data[field.key].trim();

            if (value.length === 0) {
                ideaForm.setError(field.key, `${field.label} is required.`);
                valid = false;
            } else if (value.length < field.minimum) {
                ideaForm.setError(
                    field.key,
                    `${field.label} must be at least ${field.minimum} characters.`,
                );
                valid = false;
            }
        }

        return valid;
    };

    const submitIdea = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!validateIdeaForm()) {
            return;
        }

        ideaForm.post(urls.ideaValidation, {
            preserveScroll: true,
            onSuccess: () => {
                if (ideaValidationApproved) {
                    setShowValidatedIdeaForm(false);
                }
            },
        });
    };

    const recallIdeaForRevision = () => {
        setRecallingIdea(true);
        router.post(
            urls.recallIdeaValidation,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRecallingIdea(false),
            },
        );
    };

    const restoreIdeaVersion = (version: IdeaValidationVersion) => {
        if (
            !window.confirm(
                `Restore version ${version.revision_number} as a new idea validation revision? Your advisor will review the new revision.`,
            )
        ) {
            return;
        }

        setRestoringIdeaVersionId(version.id);
        router.post(
            version.restore_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRestoringIdeaVersionId(null),
            },
        );
    };

    const startPlan = () => {
        router.post(urls.startPlan, {}, { preserveScroll: true });
    };

    const submitPlan = () => {
        router.post(urls.submit, {}, { preserveScroll: true });
    };

    const requestAdvisory = () => {
        router.post(urls.advisoryRequest, {}, { preserveScroll: true });
    };

    const requestGamificationDisablement = () => {
        router.post(
            gamification.disable_request_url,
            {},
            { preserveScroll: true },
        );
    };

    const assistRequirement = async () => {
        if (!selectedRequirement || !plan) {
            return;
        }

        setAssistingSection(true);
        setSectionError(null);
        setAssistantNotice(null);

        try {
            const response = await fetch(urls.assistRequirement, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    phase_key: selectedRequirement.phase_key,
                    requirement_key: selectedRequirement.key,
                    body: sectionBody,
                }),
            });

            if (!response.ok) {
                setSectionError(
                    'AI assist could not prepare this requirement yet.',
                );

                return;
            }

            const payload = (await response.json()) as {
                title?: string;
                draft?: string;
                summary?: string;
                checklist?: string[];
            };
            const draft = (payload.draft ?? '').trim();

            if (payload.title && !sectionTitle.trim()) {
                setSectionTitle(payload.title);
            }

            if (draft) {
                setSectionBody((current) => {
                    const existing = current.trim();

                    return existing
                        ? `${existing}\n\nAI draft to review:\n${draft}`
                        : draft;
                });
            }

            const checklist = (payload.checklist ?? [])
                .filter((item) => item.trim() !== '')
                .map((item) => `- ${item}`);
            const gamificationHint =
                gamification.enabled && selectedRequirement
                    ? `Save this one section to move plan progress to ${selectedCompletionPercent}% and keep the journey moving.`
                    : null;
            setAssistantNotice(
                [payload.summary, ...checklist, gamificationHint]
                    .filter(Boolean)
                    .join('\n'),
            );
        } catch {
            setSectionError(
                'AI assist could not prepare this requirement yet.',
            );
        } finally {
            setAssistingSection(false);
        }
    };

    const saveSection = async () => {
        if (!selectedRequirement) {
            return;
        }

        setSavingSection(true);
        setSectionError(null);
        const attachedIds: string[] = [];

        if (supportingFile) {
            const formData = new FormData();
            formData.append('file', supportingFile);
            formData.append('category', 'plan_attachment');
            formData.append('claim_value', sectionBody);
            formData.append('question_prompt', selectedRequirement.title);

            const response = await fetch(urls.documentUpload, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            if (!response.ok) {
                setSavingSection(false);
                setSectionError('Supporting document upload failed.');

                return;
            }

            const payload = (await response.json()) as {
                document?: { id?: string };
            };

            if (payload.document?.id) {
                attachedIds.push(payload.document.id);
            }
        }

        router.post(
            urls.sectionStore,
            {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle,
                body: sectionBody,
                attached_document_ids: attachedIds,
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingSection(false),
            },
        );
    };

    const saveBudget = () => {
        if (!plan) {
            return;
        }

        setSavingBudget(true);
        router.post(urls.budgetUpdate, cleanBudgetForm(budgetForm), {
            preserveScroll: true,
            onFinish: () => setSavingBudget(false),
        });
    };

    const acknowledgeBudgetFlag = (key: string) => {
        router.post(
            urls.budgetFlagAcknowledge,
            { key },
            { preserveScroll: true },
        );
    };

    const dismissBudgetAdvisorNudge = () => {
        router.post(
            urls.budgetAdvisorNudgeDismiss,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <TooltipProvider>
            <Head title="Business plan" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Business plan workspace
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile.name} /{' '}
                            {displayStageLabel(
                                profile.stage,
                                profile.stage_label,
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={urls.preview}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye className="size-4" aria-hidden="true" />
                                Preview business plan
                            </a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={urls.messages}>
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                    </div>
                </div>

                <TabList activeTab={activeTab} onChange={setActiveTab} />

                {gamification.enabled ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Trophy
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-sm font-medium">
                                        Gamification enabled
                                    </h2>
                                    <Badge
                                        variant={
                                            gamification.disable_request_requested
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {gamification.disable_request_requested
                                            ? 'Disablement requested'
                                            : 'Active'}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>
                                        {journeyLevelLabel(
                                            gamification.current_level,
                                        )}
                                    </span>
                                    <span>
                                        Plan{' '}
                                        {gamification.plan_completion
                                            ?.percent ?? 0}
                                        %
                                    </span>
                                    <span>
                                        Streak{' '}
                                        {gamification.current_streak ?? 0} days
                                    </span>
                                    {(gamification.new_badge_count ?? 0) > 0 ? (
                                        <span>
                                            {gamification.new_badge_count} new
                                            badges
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                            {gamification.disable_request_requested &&
                            gamification.disable_request_thread_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={
                                            gamification.disable_request_thread_url
                                        }
                                    >
                                        <MessageSquare
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Open request
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={requestGamificationDisablement}
                                >
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Request disablement
                                </Button>
                            )}
                        </div>
                    </section>
                ) : null}

                {activeTab === 'actions' ? (
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Priority actions
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {packageAccess.package_scope_label}
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <ActionPanel
                                    icon={Bot}
                                    title="Idea validation"
                                    value={
                                        !includesIdeaValidation
                                            ? 'Not included'
                                            : ideaValidation
                                              ? planBuilderUnlocked
                                                  ? 'Advisor approved'
                                                  : ideaChangesRequested
                                                    ? 'Changes requested'
                                                    : ideaValidationRecalled
                                                      ? 'Ready to revise'
                                                      : 'Awaiting advisor gate'
                                              : 'Not submitted'
                                    }
                                    explanation="Idea validation captures the customer problem, solution, demand, and revenue logic before the plan builder opens."
                                >
                                    {!includesIdeaValidation ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : !ideaValidation ? (
                                        <Button asChild size="sm">
                                            <a href="#idea-validation">
                                                Start idea validation
                                            </a>
                                        </Button>
                                    ) : planBuilderUnlocked ? (
                                        <Badge variant="secondary">
                                            Builder unlocked
                                        </Badge>
                                    ) : ideaChangesRequested ? (
                                        <Badge variant="outline">
                                            Changes requested
                                        </Badge>
                                    ) : ideaValidationRecalled ? (
                                        <Badge variant="outline">
                                            Ready to revise
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Advisor review
                                        </Badge>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={FileText}
                                    title="Plan completion"
                                    value={
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : plan
                                              ? plan.requirements_complete
                                                  ? 'Complete'
                                                  : `${plan.missing_requirements.length} gaps`
                                              : planBuilderUnlocked
                                                ? 'Not started'
                                                : 'Locked'
                                    }
                                    explanation="Plan completion is based on all required business plan sections, not merely one section per phase."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : plan ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={submitPlan}
                                            disabled={
                                                !plan.requirements_complete
                                            }
                                        >
                                            <Send
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Submit for assessment
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={startPlan}
                                            disabled={!planBuilderUnlocked}
                                        >
                                            <RefreshCw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Start plan
                                        </Button>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={Eye}
                                    title="Assessment"
                                    value={
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : plan?.latest_assessment
                                              ? `${formatLabel(plan.latest_assessment.overall_grade)}`
                                              : 'Pending'
                                    }
                                    explanation="Assessment appears once your advisor scores the submitted plan and finalises feedback."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : plan?.latest_assessment ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    plan.latest_assessment.url
                                                }
                                            >
                                                View assessment
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Badge variant="outline">
                                            Advisor action
                                        </Badge>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={CheckCircle2}
                                    title="Advisory"
                                    value={
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : advisoryRequest.requested
                                              ? 'Requested'
                                              : advisoryRequest.available
                                                ? 'Available'
                                                : 'Locked'
                                    }
                                    explanation="Request advisory once the plan has been assessed as advisory ready. This asks your advisor to convert the plan into a standard advisory engagement."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : advisoryRequest.requested &&
                                      advisoryRequest.thread_url ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    advisoryRequest.thread_url
                                                }
                                            >
                                                Open request
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={
                                                !advisoryRequest.available
                                            }
                                            onClick={requestAdvisory}
                                        >
                                            Request advisory
                                        </Button>
                                    )}
                                </ActionPanel>
                            </div>
                        </section>

                        <section className="rounded-md border bg-background p-4">
                            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Trophy
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            {gamification.enabled
                                                ? 'Next small win'
                                                : 'Next step'}
                                        </h2>
                                        <Badge variant="outline">
                                            {nextSmallWin.badge}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 text-sm font-medium">
                                        {nextSmallWin.title}
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {nextSmallWin.body}
                                    </p>
                                    {nextSmallWin.action ===
                                    'Start idea validation' ? (
                                        <div className="mt-3">
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    Start idea validation
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {nextSmallWin.action ===
                                    'Revise idea validation' ? (
                                        <div className="mt-3">
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    Revise idea validation
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {nextSmallWin.action === 'Start plan' ? (
                                        <div className="mt-3">
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={startPlan}
                                            >
                                                Start plan
                                            </Button>
                                        </div>
                                    ) : null}
                                    {gamification.enabled &&
                                    gamification.next_milestone ? (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Next badge:{' '}
                                            {nextMilestoneLabel(
                                                gamification.next_milestone,
                                            )}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Plan progress</span>
                                        <span>{planCompletion.percent}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-emerald-500 transition-all"
                                            style={{
                                                width: `${Math.min(100, Math.max(0, planCompletion.percent))}%`,
                                            }}
                                        />
                                    </div>
                                    {gamification.enabled ? (
                                        <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                            <span>
                                                Streak{' '}
                                                {gamification.current_streak ??
                                                    0}{' '}
                                                days
                                            </span>
                                            {(gamification.new_badge_count ??
                                                0) > 0 ? (
                                                <span>
                                                    {
                                                        gamification.new_badge_count
                                                    }{' '}
                                                    new badge
                                                    {gamification.new_badge_count ===
                                                    1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </section>

                        {includesIdeaValidation ? (
                            <section
                                id="idea-validation"
                                className="space-y-4 rounded-md border bg-background p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                Step 1
                                            </Badge>
                                            {ideaValidation ? (
                                                <Badge variant="outline">
                                                    Version{' '}
                                                    {
                                                        ideaValidation.revision_number
                                                    }
                                                </Badge>
                                            ) : null}
                                            <h2 className="text-sm font-medium">
                                                Idea validation
                                            </h2>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Capture the customer problem,
                                            solution, demand, and revenue logic
                                            before detailed plan work starts.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {ideaValidationApproved ? (
                                            <Badge variant="secondary">
                                                Gate passed
                                            </Badge>
                                        ) : ideaChangesRequested ? (
                                            <Badge variant="outline">
                                                Changes requested
                                            </Badge>
                                        ) : ideaValidationRecalled ? (
                                            <Badge variant="outline">
                                                Ready to revise
                                            </Badge>
                                        ) : ideaValidation ? (
                                            <Badge variant="outline">
                                                Advisor review
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Not submitted
                                            </Badge>
                                        )}
                                        {ideaValidationApproved ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setShowValidatedIdeaForm(
                                                        (current) => !current,
                                                    )
                                                }
                                            >
                                                {showIdeaValidationEditor ? (
                                                    <ChevronUp
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <ChevronDown
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {showIdeaValidationEditor
                                                    ? 'Roll up'
                                                    : 'Review details'}
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                {ideaChangesRequested &&
                                ideaValidation?.change_request_note ? (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
                                        <div className="font-medium">
                                            Advisor feedback
                                        </div>
                                        <p className="mt-1">
                                            {ideaValidation.change_request_note}
                                        </p>
                                    </div>
                                ) : null}

                                {ideaValidationRecalled ? (
                                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                        This validation has been removed from
                                        advisor review. Update the answers
                                        below, then resubmit it when ready.
                                    </div>
                                ) : null}

                                {ideaValidation?.restored_from_revision_number ? (
                                    <div className="text-xs text-muted-foreground">
                                        Restored from version{' '}
                                        {
                                            ideaValidation.restored_from_revision_number
                                        }
                                    </div>
                                ) : null}

                                {ideaValidationApproved &&
                                !showIdeaValidationEditor ? (
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {ideaValidationSummary.map((item) => (
                                            <div
                                                key={item.label}
                                                className="rounded-md border bg-muted/20 p-3"
                                            >
                                                <div className="text-xs font-medium text-muted-foreground">
                                                    {item.label}
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-sm">
                                                    {item.value || '-'}
                                                </p>
                                            </div>
                                        ))}
                                        {ideaValidation?.advisor_gate_note ? (
                                            <div className="rounded-md border bg-muted/20 p-3 md:col-span-2 xl:col-span-3">
                                                <div className="text-xs font-medium text-muted-foreground">
                                                    Advisor note
                                                </div>
                                                <p className="mt-1 text-sm">
                                                    {
                                                        ideaValidation.advisor_gate_note
                                                    }
                                                </p>
                                            </div>
                                        ) : null}
                                    </div>
                                ) : showIdeaValidationEditor ? (
                                    <form
                                        className="grid gap-3 lg:grid-cols-2"
                                        onSubmit={submitIdea}
                                    >
                                        {ideaChangesRequested ||
                                        ideaValidationRecalled ? (
                                            <div className="lg:col-span-2">
                                                <IdeaValidationSnapshot
                                                    fields={
                                                        ideaValidationSummary
                                                    }
                                                    revisionNumber={
                                                        ideaValidation?.revision_number ??
                                                        null
                                                    }
                                                    submittedAt={
                                                        ideaValidation?.evaluated_at ??
                                                        null
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                        {ideaFields.map((field) => (
                                            <label
                                                key={field.key}
                                                className="grid gap-1 text-sm"
                                            >
                                                <span>{field.label}</span>
                                                <textarea
                                                    value={
                                                        ideaForm.data[
                                                            field.key as keyof IdeaValidationForm
                                                        ]
                                                    }
                                                    onChange={(event) =>
                                                        ideaForm.setData(
                                                            field.key as keyof IdeaValidationForm,
                                                            event.target.value,
                                                        )
                                                    }
                                                    rows={4}
                                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        ideaForm.errors[
                                                            field.key as keyof IdeaValidationForm
                                                        ]
                                                    }
                                                />
                                            </label>
                                        ))}
                                        <div className="lg:col-span-2">
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={ideaForm.processing}
                                            >
                                                {ideaForm.processing
                                                    ? 'Submitting...'
                                                    : ideaChangesRequested ||
                                                        ideaValidationRecalled
                                                      ? 'Resubmit idea validation'
                                                      : ideaValidation
                                                        ? 'Update idea validation'
                                                        : 'Submit idea validation'}
                                            </Button>
                                            {ideaForm.recentlySuccessful ? (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Idea validation submitted
                                                    for advisor review.
                                                </p>
                                            ) : null}
                                        </div>
                                    </form>
                                ) : ideaUnderAdvisorReview ? (
                                    <div className="space-y-3">
                                        <div className="rounded-md border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                            Your idea validation is with your
                                            advisor. They will either approve
                                            the builder gate or request changes.
                                        </div>
                                        <IdeaValidationSnapshot
                                            fields={ideaValidationSummary}
                                            revisionNumber={
                                                ideaValidation?.revision_number ??
                                                null
                                            }
                                            submittedAt={
                                                ideaValidation?.evaluated_at ??
                                                null
                                            }
                                        />
                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={recallingIdea}
                                                onClick={recallIdeaForRevision}
                                            >
                                                <Pencil
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {recallingIdea
                                                    ? 'Recalling...'
                                                    : 'Recall for revision'}
                                            </Button>
                                            <p className="text-xs text-muted-foreground">
                                                Removes this submission from
                                                advisor review while you update
                                                it.
                                            </p>
                                        </div>
                                    </div>
                                ) : null}

                                {ideaValidationVersions.length > 1 ? (
                                    <IdeaValidationHistory
                                        versions={ideaValidationVersions}
                                        restoringVersionId={
                                            restoringIdeaVersionId
                                        }
                                        onRestore={restoreIdeaVersion}
                                    />
                                ) : null}
                            </section>
                        ) : null}

                        <section
                            id="business-plan-requirements"
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Plan requirements
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {includesPlanBudget
                                            ? 'Complete every required section and attach supporting evidence where it helps the advisor rely on the plan.'
                                            : 'Business plan and budget are not included in this package.'}
                                    </p>
                                </div>
                                {!plan && includesPlanBudget ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={startPlan}
                                        disabled={!planBuilderUnlocked}
                                    >
                                        Start plan
                                    </Button>
                                ) : null}
                            </div>

                            {!includesPlanBudget ? (
                                <div className="rounded-md border border-dashed bg-muted/20 p-4">
                                    <h3 className="text-sm font-medium">
                                        Not included in this package
                                    </h3>
                                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                        This package covers idea validation
                                        only. Your advisor can invite you to
                                        Business Plan + Budget or the bundle
                                        package if you decide to progress.
                                    </p>
                                </div>
                            ) : hasPlan ? (
                                <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                                    <div className="space-y-3">
                                        {phases.map((phase) => (
                                            <div
                                                key={phase.key}
                                                className="space-y-2"
                                            >
                                                <div className="text-xs font-medium text-muted-foreground">
                                                    {phase.title}
                                                </div>
                                                {phase.requirements.map(
                                                    (requirement) => (
                                                        <button
                                                            key={requirementId(
                                                                requirement,
                                                            )}
                                                            type="button"
                                                            className={cn(
                                                                'w-full rounded-md border p-3 text-left text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                                selectedRequirement &&
                                                                    requirementId(
                                                                        requirement,
                                                                    ) ===
                                                                        requirementId(
                                                                            selectedRequirement,
                                                                        ) &&
                                                                    'border-foreground',
                                                            )}
                                                            onClick={() =>
                                                                setSelectedKey(
                                                                    requirementId(
                                                                        requirement,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <div className="font-medium">
                                                                        {
                                                                            requirement.title
                                                                        }
                                                                    </div>
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {
                                                                            requirement.description
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <Badge
                                                                    variant={
                                                                        requirement.complete
                                                                            ? 'secondary'
                                                                            : 'outline'
                                                                    }
                                                                >
                                                                    {requirement.complete
                                                                        ? 'Complete'
                                                                        : 'Needed'}
                                                                </Badge>
                                                            </div>
                                                        </button>
                                                    ),
                                                )}
                                            </div>
                                        ))}
                                    </div>

                                    <div className="space-y-4 rounded-md border p-4">
                                        {selectedRequirement ? (
                                            selectedRequirement.type ===
                                                'budget' && plan ? (
                                                <BudgetEditor
                                                    budget={plan.budget}
                                                    form={budgetForm}
                                                    plan={plan}
                                                    ideaValidation={
                                                        ideaValidation
                                                    }
                                                    gamification={gamification}
                                                    saving={savingBudget}
                                                    onFormChange={setBudgetForm}
                                                    onSave={saveBudget}
                                                    onAcknowledgeFlag={
                                                        acknowledgeBudgetFlag
                                                    }
                                                    onDismissAdvisorNudge={
                                                        dismissBudgetAdvisorNudge
                                                    }
                                                />
                                            ) : (
                                                <>
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Complete
                                                                requirement
                                                            </h3>
                                                            <p className="mt-1 text-sm text-muted-foreground">
                                                                {
                                                                    selectedRequirement.description
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    void assistRequirement()
                                                                }
                                                                disabled={
                                                                    !plan ||
                                                                    assistingSection
                                                                }
                                                            >
                                                                <Bot
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                {assistingSection
                                                                    ? 'Assisting'
                                                                    : 'AI assist'}
                                                            </Button>
                                                            {selectedSection ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            selectedSection.guidance_url,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Bot
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                    Score draft
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                    <label className="grid gap-1 text-sm">
                                                        <span>
                                                            Section title
                                                        </span>
                                                        <input
                                                            value={sectionTitle}
                                                            onChange={(event) =>
                                                                setSectionTitle(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                                        />
                                                    </label>
                                                    <label className="grid gap-1 text-sm">
                                                        <span>Plan detail</span>
                                                        <textarea
                                                            value={sectionBody}
                                                            onChange={(event) =>
                                                                setSectionBody(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={8}
                                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                                            placeholder="Add the context, evidence, assumptions, decisions, and risks your advisor should rely on."
                                                        />
                                                    </label>
                                                    <FileDropzone
                                                        key={supportingKey}
                                                        id="entrepreneur-plan-support"
                                                        files={
                                                            supportingFile
                                                                ? [
                                                                      supportingFile,
                                                                  ]
                                                                : []
                                                        }
                                                        label="Attach supporting document"
                                                        onFilesChange={(
                                                            files,
                                                        ) =>
                                                            setSupportingFile(
                                                                files[0] ??
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            sectionError ??
                                                            undefined
                                                        }
                                                    />
                                                    {assistantNotice ? (
                                                        <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                            <div className="font-medium">
                                                                AI assistant
                                                            </div>
                                                            <p className="mt-1 whitespace-pre-line text-muted-foreground">
                                                                {
                                                                    assistantNotice
                                                                }
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedSection?.guidance ? (
                                                        <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                            <div className="font-medium">
                                                                AI guidance
                                                            </div>
                                                            <p className="mt-1 text-muted-foreground">
                                                                {
                                                                    selectedSection
                                                                        .guidance
                                                                        .summary
                                                                }
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            void saveSection()
                                                        }
                                                        disabled={
                                                            !plan ||
                                                            savingSection
                                                        }
                                                    >
                                                        <Upload
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {savingSection
                                                            ? 'Saving'
                                                            : 'Save requirement'}
                                                    </Button>
                                                </>
                                            )
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Select a requirement to start
                                                completing the business plan.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-md border border-dashed bg-muted/20 p-4">
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h3 className="text-sm font-medium">
                                                {planBuilderUnlocked
                                                    ? 'Plan sections are ready'
                                                    : ideaChangesRequested
                                                      ? 'Plan sections are waiting for your revised idea'
                                                      : hasIdeaValidation
                                                        ? 'Plan sections are waiting for advisor review'
                                                        : 'Plan sections unlock after idea validation'}
                                            </h3>
                                            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                                {planBuilderUnlocked
                                                    ? 'Start the business plan to open the section checklist and AI assist.'
                                                    : ideaChangesRequested
                                                      ? 'Your advisor requested changes to the idea validation. Update and resubmit it before these sections open.'
                                                      : hasIdeaValidation
                                                        ? 'Your idea validation has been submitted. Your advisor needs to approve it before these sections open.'
                                                        : 'Complete idea validation first so your advisor can confirm the concept before detailed plan work starts.'}
                                            </p>
                                        </div>
                                        {planBuilderUnlocked ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={startPlan}
                                            >
                                                Start plan
                                            </Button>
                                        ) : !hasIdeaValidation ||
                                          ideaChangesRequested ||
                                          ideaValidationRecalled ? (
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    {ideaChangesRequested ||
                                                    ideaValidationRecalled
                                                        ? 'Revise idea validation'
                                                        : 'Start idea validation'}
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="space-y-4 rounded-md border bg-background p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-sm font-medium">
                                    Assessment reports
                                </h2>
                                <Badge variant="outline">
                                    {reports.length}
                                </Badge>
                            </div>
                            {reports.length > 0 ? (
                                <div className="divide-y rounded-md border">
                                    {reports.map((report) => (
                                        <article
                                            key={report.id}
                                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                                        >
                                            <div>
                                                <div className="text-sm font-medium">
                                                    {report.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        report.generated_at,
                                                    )}
                                                </div>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={
                                                        report.view_url ??
                                                        report.download_url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    View
                                                </a>
                                            </Button>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Reports appear after your advisor finalises
                                    an assessment.
                                </p>
                            )}
                        </section>

                        <section className="space-y-4 rounded-md border bg-background p-4">
                            <h2 className="text-sm font-medium">
                                Current profile
                            </h2>
                            <dl className="grid gap-3 text-sm">
                                <Detail label="Email" value={profile.email} />
                                <Detail
                                    label="Stage"
                                    value={displayStageLabel(
                                        profile.stage,
                                        profile.stage_label,
                                    )}
                                />
                                <Detail
                                    label="Concept"
                                    value={profile.concept_summary}
                                />
                                <Detail
                                    label="Plan status"
                                    value={
                                        plan ? formatLabel(plan.status) : null
                                    }
                                />
                            </dl>
                            {advisoryRequest.blockers.length > 0 ? (
                                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                    {advisoryRequest.blockers.join(' ')}
                                </div>
                            ) : null}
                        </section>
                    </div>
                )}
            </div>
        </TooltipProvider>
    );
}

function TabList({
    activeTab,
    onChange,
}: {
    activeTab: Tab;
    onChange: (tab: Tab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Business plan sections"
        >
            {(['actions', 'information'] as Tab[]).map((tab) => (
                <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab}
                    className={cn(
                        'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        activeTab === tab &&
                            'bg-background text-foreground shadow-xs',
                    )}
                    onClick={() => onChange(tab)}
                >
                    {formatLabel(tab)}
                </button>
            ))}
        </div>
    );
}

function ActionPanel({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Icon className="size-4" aria-hidden={true} />
                                {title}
                            </div>
                            <div className="mt-2 text-sm text-muted-foreground">
                                {value}
                            </div>
                        </div>
                    </div>
                    {children}
                </section>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

type BudgetTemplateKey =
    | 'service'
    | 'consulting'
    | 'retail'
    | 'food'
    | 'online'
    | 'trades'
    | 'subscription';

type BudgetTemplate = {
    title: string;
    description: string;
    expected_runway_months: number;
    launch_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
};

const BUDGET_UNLOCK_REQUIREMENT_KEY = 'business-type-location';
const BUDGET_ASSUMPTIONS_REQUIREMENT_KEY = 'financial-assumptions';

const budgetTemplates: Record<BudgetTemplateKey, BudgetTemplate> = {
    service: {
        title: 'Local service',
        description:
            'A practical service business selling appointments, jobs, or packages.',
        expected_runway_months: 6,
        launch_costs: [
            budgetRow('Website and domain', 500, 'estimate'),
            budgetRow('Basic equipment', 1200, 'estimate'),
            budgetRow('Launch marketing', 800, 'guess'),
        ],
        monthly_fixed_costs: [
            budgetRow('Phone and internet', 120, 'estimate'),
            budgetRow('Accounting software', 60, 'estimate'),
            budgetRow('Insurance', 150, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('First customer work', 750, 'guess', {
                quantity: 2,
                month: 1,
                variable_cost_percent: 15,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 3000, 'guess')],
    },
    consulting: {
        title: 'Consulting or coaching',
        description: 'Advice, coaching, training, or professional services.',
        expected_runway_months: 4,
        launch_costs: [
            budgetRow('Brand and website setup', 900, 'estimate'),
            budgetRow('Professional templates', 250, 'guess'),
            budgetRow('Launch outreach', 500, 'guess'),
        ],
        monthly_fixed_costs: [
            budgetRow('Video meetings and productivity tools', 80, 'estimate'),
            budgetRow('Accounting software', 60, 'estimate'),
            budgetRow('Professional insurance', 170, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('Client retainers or sessions', 1200, 'guess', {
                quantity: 2,
                month: 1,
                variable_cost_percent: 5,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 2500, 'guess')],
    },
    retail: {
        title: 'Retail or product sales',
        description:
            'Physical products, stock, inventory, market stall, or shop sales.',
        expected_runway_months: 6,
        launch_costs: [
            budgetRow('Opening stock', 3500, 'guess'),
            budgetRow('Display, packaging, or signage', 1200, 'estimate'),
            budgetRow('Point of sale setup', 500, 'estimate'),
        ],
        monthly_fixed_costs: [
            budgetRow('Ecommerce or POS software', 80, 'estimate'),
            budgetRow('Storage, stall, or small premises cost', 600, 'guess'),
            budgetRow('Insurance', 180, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('Product sales', 65, 'guess', {
                quantity: 80,
                month: 1,
                variable_cost_percent: 45,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 6000, 'guess')],
    },
    food: {
        title: 'Food or hospitality',
        description:
            'Food truck, catering, cafe, packaged food, or hospitality launch.',
        expected_runway_months: 8,
        launch_costs: [
            budgetRow('Kitchen gear or fit-out', 6000, 'guess'),
            budgetRow('Permits and compliance', 900, 'estimate'),
            budgetRow('Initial ingredients and packaging', 1800, 'guess'),
        ],
        monthly_fixed_costs: [
            budgetRow('Commercial kitchen or site cost', 1200, 'guess'),
            budgetRow('Insurance', 220, 'guess'),
            budgetRow('Utilities and cleaning', 350, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('Average orders', 22, 'guess', {
                quantity: 300,
                month: 1,
                variable_cost_percent: 38,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 9000, 'guess')],
    },
    online: {
        title: 'Online store',
        description:
            'Digital storefront, online product sales, or direct-to-customer sales.',
        expected_runway_months: 6,
        launch_costs: [
            budgetRow('Online store setup', 900, 'estimate'),
            budgetRow('Opening inventory', 2500, 'guess'),
            budgetRow('Launch ads and content', 1200, 'guess'),
        ],
        monthly_fixed_costs: [
            budgetRow('Store platform and apps', 120, 'estimate'),
            budgetRow('Email and marketing tools', 90, 'estimate'),
            budgetRow('Storage or fulfilment', 300, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('Online orders', 75, 'guess', {
                quantity: 60,
                month: 1,
                variable_cost_percent: 42,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 5000, 'guess')],
    },
    trades: {
        title: 'Trades or field work',
        description:
            'Hands-on services, mobile work, installation, maintenance, or repairs.',
        expected_runway_months: 5,
        launch_costs: [
            budgetRow('Tools and equipment', 3500, 'guess'),
            budgetRow('Vehicle setup or signage', 1800, 'guess'),
            budgetRow('Licences and safety gear', 700, 'estimate'),
        ],
        monthly_fixed_costs: [
            budgetRow('Vehicle running costs', 500, 'guess'),
            budgetRow('Insurance', 250, 'guess'),
            budgetRow('Phone and job management software', 120, 'estimate'),
        ],
        revenue_forecast: [
            budgetRow('Jobs completed', 450, 'guess', {
                quantity: 8,
                month: 1,
                variable_cost_percent: 22,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 5000, 'guess')],
    },
    subscription: {
        title: 'Subscription or membership',
        description:
            'Recurring revenue, memberships, SaaS, community, or content products.',
        expected_runway_months: 9,
        launch_costs: [
            budgetRow('Product or platform setup', 5000, 'guess'),
            budgetRow('Brand, landing page, and content', 1500, 'guess'),
            budgetRow('Launch campaign', 1800, 'guess'),
        ],
        monthly_fixed_costs: [
            budgetRow('Hosting and software tools', 350, 'estimate'),
            budgetRow('Support and admin tools', 180, 'guess'),
            budgetRow('Content or product maintenance', 700, 'guess'),
        ],
        revenue_forecast: [
            budgetRow('Monthly subscribers or members', 49, 'guess', {
                quantity: 60,
                month: 1,
                monthly_growth_percent: 8,
                variable_cost_percent: 8,
            }),
        ],
        funding_sources: [budgetRow('Founder cash', 8000, 'guess')],
    },
};

function BudgetEditor({
    budget,
    form,
    plan,
    ideaValidation,
    gamification,
    saving,
    onFormChange,
    onSave,
    onAcknowledgeFlag,
    onDismissAdvisorNudge,
}: {
    budget: BudgetPayload;
    form: BudgetFormState;
    plan: NonNullable<BusinessPlanPayload>;
    ideaValidation: IdeaValidationPayload;
    gamification: GamificationPayload;
    saving: boolean;
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
    onSave: () => void;
    onAcknowledgeFlag: (key: string) => void;
    onDismissAdvisorNudge: () => void;
}) {
    const computed = budget.computed ?? {};
    const activeFlags = budget.active_flags ?? [];
    const showAdvisorNudge =
        activeFlags.length > 0 && !budget.advisor_line_nudge_seen_at;
    const inferredTemplate = useMemo(
        () => inferBudgetTemplateKey(plan, ideaValidation),
        [plan, ideaValidation],
    );
    const budgetSource = useMemo(
        () => budgetPlanSource(plan, BUDGET_UNLOCK_REQUIREMENT_KEY),
        [plan],
    );
    const assumptionsSource = useMemo(
        () => budgetPlanSource(plan, BUDGET_ASSUMPTIONS_REQUIREMENT_KEY),
        [plan],
    );
    const budgetUnlocked =
        budgetSource.requirement?.complete === true &&
        assumptionsSource.requirement?.complete === true;
    const [mode, setMode] = useState<BudgetSetupMode>('guided');
    const template = budgetTemplates[inferredTemplate];
    const assumptionLabels = computed.assumptions?.field_labels ?? {};
    const missingAssumptions = computed.missing_assumptions ?? [];

    const applyTemplate = () => {
        onFormChange((current) => ({
            ...current,
            expected_runway_months:
                current.expected_runway_months ||
                String(template.expected_runway_months),
            launch_costs: mergeBudgetRows(
                current.launch_costs,
                template.launch_costs,
                false,
                true,
            ),
            monthly_fixed_costs: mergeBudgetRows(
                current.monthly_fixed_costs,
                template.monthly_fixed_costs,
            ),
            revenue_forecast: mergeBudgetRows(
                current.revenue_forecast,
                template.revenue_forecast,
                true,
            ),
            funding_sources: mergeBudgetRows(
                current.funding_sources,
                template.funding_sources,
            ),
        }));
    };
    const applyPlanClues = () => {
        const key = inferBudgetTemplateKey(plan, ideaValidation);
        const suggestions = budgetRowsFromPlan(plan, ideaValidation, key);
        const planAssumptions = budgetAssumptionsFromPlan(plan);

        onFormChange((current) => ({
            ...current,
            assumptions: mergeBudgetAssumptions(
                current.assumptions,
                planAssumptions,
            ),
            expected_runway_months:
                current.expected_runway_months ||
                String(suggestions.expected_runway_months),
            launch_costs: mergeBudgetRows(
                current.launch_costs,
                suggestions.launch_costs,
                false,
                true,
            ),
            monthly_fixed_costs: mergeBudgetRows(
                current.monthly_fixed_costs,
                suggestions.monthly_fixed_costs,
            ),
            revenue_forecast: mergeBudgetRows(
                current.revenue_forecast,
                suggestions.revenue_forecast,
                true,
            ),
            funding_sources: mergeBudgetRows(
                current.funding_sources,
                suggestions.funding_sources,
            ),
        }));
    };

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <Banknote className="size-4" aria-hidden="true" />
                        Budget setup assistant
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Start with plain questions and example rows. You can
                        switch to advanced editing at any time.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {budget.pack_available && budget.budget_pack_url ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            asChild
                        >
                            <Link href={budget.budget_pack_url}>
                                <Eye className="size-4" aria-hidden="true" />
                                View budget pack
                            </Link>
                        </Button>
                    ) : null}
                    <div
                        className="inline-flex rounded-md border bg-muted/30 p-1"
                        role="tablist"
                        aria-label="Budget setup mode"
                    >
                        {(['guided', 'advanced'] as BudgetSetupMode[]).map(
                            (option) => (
                                <button
                                    key={option}
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === option}
                                    className={cn(
                                        'rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        mode === option &&
                                            'bg-background text-foreground shadow-xs',
                                    )}
                                    onClick={() => setMode(option)}
                                >
                                    {formatLabel(option)}
                                </button>
                            ),
                        )}
                    </div>
                    <Badge
                        variant={
                            budget.status === 'complete'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {formatLabel(budget.status)}
                    </Badge>
                </div>
            </div>

            {gamification.enabled ? (
                <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3 text-sm">
                    <Trophy
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span className="text-muted-foreground">
                        Budget progress counts toward the Financial phase
                        milestone once the revenue model and launch funding
                        requirements are also complete.
                    </span>
                </div>
            ) : null}

            {!budgetUnlocked ? (
                <section className="grid gap-3 rounded-md border bg-muted/20 p-4 text-sm md:grid-cols-[1fr_auto] md:items-start">
                    <div className="flex gap-3">
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <div className="space-y-1">
                            <div className="font-medium">
                                Complete the plan assumptions first
                            </div>
                            <p className="text-muted-foreground">
                                The budget assistant uses the completed
                                "Business type, location, and operating model"
                                and "Financial assumptions" requirements to
                                understand the business model, margins, growth,
                                funding, and profit targets. Finish those plan
                                sections first so the budget can reuse the
                                answers instead of asking twice.
                            </p>
                        </div>
                    </div>
                    <Button type="button" size="sm" variant="outline" asChild>
                        <a href="#business-plan-requirements">
                            <FileText className="size-4" aria-hidden="true" />
                            Go to requirement
                        </a>
                    </Button>
                </section>
            ) : mode === 'guided' ? (
                <div className="space-y-4">
                    <section className="space-y-3 rounded-md border bg-muted/20 p-3">
                        <div>
                            <div className="text-sm font-medium">
                                Plan-based budget starter
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                This uses the completed Foundation requirement
                                and the rest of the business plan to suggest
                                starter rows. The numbers are placeholders to
                                adjust, not a judgement.
                            </p>
                        </div>
                        <div className="grid gap-3 rounded-md border bg-background p-3 text-sm md:grid-cols-3">
                            <BudgetSourceDetail
                                label="Plan source"
                                value={
                                    budgetSource.section?.title ??
                                    budgetSource.requirement?.title ??
                                    'Foundation requirement'
                                }
                            />
                            <BudgetSourceDetail
                                label="Detected starter"
                                value={template.title}
                                helper={template.description}
                            />
                            <BudgetSourceDetail
                                label="Revenue clue"
                                value={
                                    ideaValidation?.revenue_model?.trim() ||
                                    'Use the Revenue model section when available'
                                }
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3 text-sm">
                            <span className="text-muted-foreground">
                                Add starter rows for{' '}
                                {template.title.toLowerCase()}, then adjust the
                                amounts and confidence levels.
                            </span>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={applyPlanClues}
                                >
                                    <Bot
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Use plan clues
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={applyTemplate}
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Use starter budget
                                </Button>
                            </div>
                        </div>
                    </section>

                    {missingAssumptions.length > 0 ? (
                        <section className="grid gap-3 rounded-md border bg-amber-50 p-3 text-sm text-amber-950 md:grid-cols-[1fr_auto] md:items-start">
                            <div>
                                <div className="font-medium">
                                    Financial assumptions need more detail
                                </div>
                                <p className="mt-1">
                                    Update the business-plan Financial
                                    assumptions section for{' '}
                                    {missingAssumptions
                                        .map(
                                            (key) =>
                                                assumptionLabels[key] ??
                                                formatLabel(key),
                                        )
                                        .join(', ')}
                                    . The budget can still be saved, but weak
                                    assumptions affect viability, scoring, and
                                    funding readiness.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                asChild
                            >
                                <a href="#business-plan-requirements">
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Update assumptions
                                </a>
                            </Button>
                        </section>
                    ) : null}

                    <section className="grid gap-4 rounded-md border bg-background p-3 md:grid-cols-[220px_minmax(0,1fr)]">
                        <label className="grid gap-1 text-sm">
                            <span>Budget horizon</span>
                            <select
                                value={form.forecast_years}
                                onChange={(event) =>
                                    onFormChange((current) => ({
                                        ...current,
                                        forecast_years: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="1">1 year</option>
                                <option value="2">2 years</option>
                                <option value="3">3 years</option>
                                <option value="5">5 years</option>
                            </select>
                            <span className="text-xs text-muted-foreground">
                                Choose the horizon required by the bank,
                                investor, or internal decision.
                            </span>
                        </label>
                        <BudgetAssumptionsEditor
                            assumptions={form.assumptions}
                            onFormChange={onFormChange}
                        />
                    </section>

                    <label className="grid gap-1 text-sm md:max-w-sm">
                        <span>
                            How many months should the business survive before
                            it supports itself?
                        </span>
                        <input
                            type="number"
                            min={0}
                            max={60}
                            value={form.expected_runway_months}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    expected_runway_months: event.target.value,
                                }))
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            placeholder="Example: 6"
                        />
                        <span className="text-xs text-muted-foreground">
                            A guess is fine. The advisor can help refine it.
                        </span>
                    </label>

                    <div className="grid gap-4">
                        <BudgetRowsEditor
                            title="What do you need before your first sale?"
                            helper="Include one-off setup items such as equipment, website, deposits, signage, licences, stock, or launch marketing."
                            group="launch_costs"
                            rows={form.launch_costs}
                            onFormChange={onFormChange}
                            quickAdds={template.launch_costs}
                            timed
                        />
                        <BudgetRowsEditor
                            title="What will you pay every month even if sales are slow?"
                            helper="Include recurring costs such as software, rent, insurance, phone, accounting, subscriptions, or transport."
                            group="monthly_fixed_costs"
                            rows={form.monthly_fixed_costs}
                            onFormChange={onFormChange}
                            quickAdds={template.monthly_fixed_costs}
                        />
                        <BudgetRowsEditor
                            title="How will money come in?"
                            helper="Use simple assumptions: monthly customers, sales, jobs, subscriptions, or average orders."
                            group="revenue_forecast"
                            rows={form.revenue_forecast}
                            onFormChange={onFormChange}
                            quickAdds={template.revenue_forecast}
                            revenue
                        />
                        <BudgetRowsEditor
                            title="What money can you use to start?"
                            helper="Include founder cash, confirmed grants, loans, family support, customer deposits, or pre-sales."
                            group="funding_sources"
                            rows={form.funding_sources}
                            onFormChange={onFormChange}
                            quickAdds={template.funding_sources}
                        />
                        <FutureCostsEditor
                            rows={form.future_costs}
                            onFormChange={onFormChange}
                        />
                        <FundingScenariosEditor
                            rows={form.funding_scenarios}
                            onFormChange={onFormChange}
                        />
                    </div>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-[220px_220px_minmax(0,1fr)]">
                        <label className="grid gap-1 text-sm">
                            <span>Expected runway months</span>
                            <input
                                type="number"
                                min={0}
                                max={60}
                                value={form.expected_runway_months}
                                onChange={(event) =>
                                    onFormChange((current) => ({
                                        ...current,
                                        expected_runway_months:
                                            event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span>Budget horizon</span>
                            <select
                                value={form.forecast_years}
                                onChange={(event) =>
                                    onFormChange((current) => ({
                                        ...current,
                                        forecast_years: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="1">1 year</option>
                                <option value="2">2 years</option>
                                <option value="3">3 years</option>
                                <option value="5">5 years</option>
                            </select>
                        </label>
                        <BudgetAssumptionsEditor
                            assumptions={form.assumptions}
                            onFormChange={onFormChange}
                        />
                    </div>

                    <div className="grid gap-4">
                        <BudgetRowsEditor
                            title="Launch costs"
                            group="launch_costs"
                            rows={form.launch_costs}
                            onFormChange={onFormChange}
                            timed
                        />
                        <BudgetRowsEditor
                            title="Monthly fixed costs"
                            group="monthly_fixed_costs"
                            rows={form.monthly_fixed_costs}
                            onFormChange={onFormChange}
                        />
                        <BudgetRowsEditor
                            title="Revenue forecast"
                            group="revenue_forecast"
                            rows={form.revenue_forecast}
                            onFormChange={onFormChange}
                            revenue
                        />
                        <BudgetRowsEditor
                            title="Funding sources"
                            group="funding_sources"
                            rows={form.funding_sources}
                            onFormChange={onFormChange}
                        />
                        <FutureCostsEditor
                            rows={form.future_costs}
                            onFormChange={onFormChange}
                        />
                        <FundingScenariosEditor
                            rows={form.funding_scenarios}
                            onFormChange={onFormChange}
                        />
                    </div>
                </div>
            )}

            {budgetUnlocked ? (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <BudgetMetric
                            label="Launch costs"
                            value={formatCurrency(computed.total_launch_costs)}
                        />
                        <BudgetMetric
                            label="Break-even year"
                            value={formatYear(computed.break_even_year)}
                        />
                        <BudgetMetric
                            label="Profit year"
                            value={formatYear(computed.first_profitable_year)}
                        />
                        <BudgetMetric
                            label="Cash positive"
                            value={formatYear(computed.cash_flow_positive_year)}
                        />
                    </div>

                    <BudgetCashChart
                        series={computed.monthly_series ?? []}
                        breakEvenMonth={computed.break_even_month}
                        runwayMonths={computed.runway_months}
                        runwayOpenEnded={computed.runway_open_ended}
                        title="12-month cash curve"
                        description="Revenue and cumulative cash use separate scales so a funding balance does not flatten monthly sales."
                    />

                    <AdvisorBudgetPreview budget={budget} form={form} />

                    {activeFlags.length > 0 ? (
                        <div className="space-y-2">
                            {activeFlags.map((flag) => (
                                <div
                                    key={flag.key}
                                    className="flex flex-wrap items-start justify-between gap-3 rounded-md border bg-muted/30 p-3 text-sm"
                                >
                                    <div className="flex min-w-0 gap-2">
                                        <AlertTriangle
                                            className="mt-0.5 size-4 shrink-0 text-amber-600"
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <div className="font-medium">
                                                {flag.title}
                                            </div>
                                            <p className="mt-1 text-muted-foreground">
                                                {flag.message}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            onAcknowledgeFlag(flag.key)
                                        }
                                    >
                                        Acknowledge
                                    </Button>
                                </div>
                            ))}
                        </div>
                    ) : null}

                    {showAdvisorNudge ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3 text-sm">
                            <span className="text-muted-foreground">
                                Advisor line item: unresolved budget warnings.
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onDismissAdvisorNudge}
                            >
                                Dismiss
                            </Button>
                        </div>
                    ) : null}

                    <Button
                        type="button"
                        size="sm"
                        onClick={onSave}
                        disabled={saving}
                    >
                        <Upload className="size-4" aria-hidden="true" />
                        {saving ? 'Saving' : 'Save budget'}
                    </Button>
                </>
            ) : null}
        </div>
    );
}

function BudgetRowsEditor({
    title,
    helper,
    group,
    rows,
    onFormChange,
    quickAdds = [],
    revenue = false,
    timed = false,
}: {
    title: string;
    helper?: string;
    group: BudgetGroupKey;
    rows: BudgetRow[];
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
    quickAdds?: BudgetRow[];
    revenue?: boolean;
    timed?: boolean;
}) {
    return (
        <section className="space-y-2 rounded-md border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div className="text-sm font-medium">{title}</div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onFormChange((current) => ({
                            ...current,
                            [group]: [
                                ...current[group],
                                blankBudgetRow(revenue, timed),
                            ],
                        }))
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
            {helper ? (
                <p className="text-sm text-muted-foreground">{helper}</p>
            ) : null}
            {quickAdds.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {quickAdds.slice(0, 6).map((row) => (
                        <Tooltip key={row.label}>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    title={budgetRowDescription(row)}
                                    onClick={() =>
                                        onFormChange((current) => ({
                                            ...current,
                                            [group]: mergeBudgetRows(
                                                current[group],
                                                [row],
                                                revenue,
                                                timed,
                                            ),
                                        }))
                                    }
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {row.label}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top" className="max-w-xs">
                                {budgetRowDescription(row)}
                            </TooltipContent>
                        </Tooltip>
                    ))}
                </div>
            ) : null}

            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div
                        key={index}
                        className={cn(
                            'grid gap-2',
                            revenue
                                ? 'md:grid-cols-[minmax(0,1.2fr)_repeat(7,minmax(72px,0.5fr))_120px_auto]'
                                : timed
                                  ? 'md:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(92px,0.6fr))_120px_auto]'
                                  : 'md:grid-cols-[minmax(0,1.4fr)_repeat(2,minmax(92px,0.6fr))_120px_auto]',
                        )}
                    >
                        <BudgetInput
                            label="Item"
                            value={row.label}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    label: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Amount"
                            type="number"
                            value={row.amount}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    amount: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Qty"
                            type="number"
                            value={row.quantity ?? 1}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    quantity: value,
                                })
                            }
                        />
                        {timed && !revenue ? (
                            <BudgetInput
                                label="Month"
                                type="number"
                                min={1}
                                value={row.month ?? 1}
                                onChange={(value) =>
                                    updateBudgetRow(
                                        onFormChange,
                                        group,
                                        index,
                                        { month: value },
                                    )
                                }
                            />
                        ) : null}
                        {revenue ? (
                            <>
                                <BudgetInput
                                    label="Start"
                                    type="number"
                                    min={1}
                                    value={row.month ?? 1}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { month: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Growth %"
                                    type="number"
                                    min={-100}
                                    value={row.monthly_growth_percent ?? 0}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { monthly_growth_percent: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Cost %"
                                    type="number"
                                    value={row.variable_cost_percent ?? 0}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { variable_cost_percent: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Unit cost"
                                    type="number"
                                    value={row.unit_cost ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { unit_cost: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="GP %"
                                    type="number"
                                    value={row.gross_profit_percent ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                gross_profit_percent: value,
                                                variable_cost_percent:
                                                    value === ''
                                                        ? row.variable_cost_percent
                                                        : Math.max(
                                                              0,
                                                              100 -
                                                                  numberFromInput(
                                                                      value,
                                                                  ),
                                                          ),
                                            },
                                        )
                                    }
                                />
                            </>
                        ) : null}
                        <BudgetConfidenceSelect
                            value={row.confidence ?? 'estimate'}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    confidence: value,
                                })
                            }
                        />
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                title="Remove row"
                                onClick={() =>
                                    onFormChange((current) => ({
                                        ...current,
                                        [group]: current[group].filter(
                                            (_row, rowIndex) =>
                                                rowIndex !== index,
                                        ),
                                    }))
                                }
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function BudgetAssumptionsEditor({
    assumptions,
    onFormChange,
}: {
    assumptions: BudgetAssumptions;
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
}) {
    const fields: {
        key: keyof BudgetAssumptions;
        label: string;
        helper: string;
    }[] = [
        {
            key: 'revenue_growth_percent',
            label: 'Revenue growth %',
            helper: 'How annual sales should grow after year one.',
        },
        {
            key: 'cost_inflation_percent',
            label: 'Cost/CPI %',
            helper: 'How costs should increase after year one.',
        },
        {
            key: 'target_gross_profit_percent',
            label: 'Target GP %',
            helper: 'Sales left after direct product or delivery costs.',
        },
        {
            key: 'target_net_profit_before_tax_percent',
            label: 'Target NPBT %',
            helper: 'Profit before company tax.',
        },
        {
            key: 'target_net_profit_after_tax_percent',
            label: 'Target NPAT %',
            helper: 'Profit after estimated company tax.',
        },
    ];

    return (
        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            {fields.map((field) => (
                <label key={field.key} className="grid gap-1 text-xs">
                    <span className="text-muted-foreground">{field.label}</span>
                    <input
                        type="number"
                        min={
                            field.key === 'revenue_growth_percent' ||
                            field.key === 'cost_inflation_percent'
                                ? -100
                                : 0
                        }
                        max={field.key === 'cost_inflation_percent' ? 100 : 500}
                        value={assumptions[field.key]}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    [field.key]: event.target.value,
                                },
                            }))
                        }
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                    />
                    <span className="text-[11px] leading-snug text-muted-foreground">
                        {field.helper}
                    </span>
                </label>
            ))}
        </div>
    );
}

function FutureCostsEditor({
    rows,
    onFormChange,
}: {
    rows: FutureCostRow[];
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
}) {
    return (
        <section className="space-y-2 rounded-md border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-medium">
                        What extra costs might happen in later years?
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add expansion, equipment replacement, extra staff,
                        larger premises, or platform upgrades that are not
                        standard CPI increases.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onFormChange((current) => ({
                            ...current,
                            future_costs: [
                                ...current.future_costs,
                                blankFutureCostRow(),
                            ],
                        }))
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div
                        key={index}
                        className="grid gap-2 md:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(84px,0.5fr))_120px_120px_auto]"
                    >
                        <BudgetInput
                            label="Item"
                            value={row.label}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    label: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Amount"
                            type="number"
                            value={row.amount}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    amount: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Qty"
                            type="number"
                            value={row.quantity ?? 1}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    quantity: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Year"
                            type="number"
                            value={row.year ?? 2}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    year: value,
                                })
                            }
                        />
                        <label className="grid gap-1 text-xs">
                            <span className="text-muted-foreground">
                                Recurring
                            </span>
                            <select
                                value={row.recurring ? 'yes' : 'no'}
                                onChange={(event) =>
                                    updateFutureCostRow(onFormChange, index, {
                                        recurring: event.target.value === 'yes',
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="no">One-off</option>
                                <option value="yes">Monthly</option>
                            </select>
                        </label>
                        <BudgetConfidenceSelect
                            value={budgetRowConfidence(row.confidence)}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    confidence: value,
                                })
                            }
                        />
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                title="Remove row"
                                onClick={() =>
                                    onFormChange((current) => ({
                                        ...current,
                                        future_costs:
                                            current.future_costs.filter(
                                                (_row, rowIndex) =>
                                                    rowIndex !== index,
                                            ),
                                    }))
                                }
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function FundingScenariosEditor({
    rows,
    onFormChange,
}: {
    rows: FundingScenarioRow[];
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
}) {
    return (
        <section className="space-y-2 rounded-md border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-medium">
                        What funding scenarios should be tested?
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add bank-loan, investor, or mixed funding options. The
                        base case still drives scoring.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onFormChange((current) => ({
                            ...current,
                            funding_scenarios: [
                                ...current.funding_scenarios,
                                blankFundingScenario(),
                            ],
                        }))
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div
                        key={index}
                        className="grid gap-2 md:grid-cols-[minmax(0,1.2fr)_130px_repeat(5,minmax(76px,0.5fr))_120px_auto]"
                    >
                        <BudgetInput
                            label="Scenario"
                            value={row.name}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    name: value,
                                })
                            }
                        />
                        <label className="grid gap-1 text-xs">
                            <span className="text-muted-foreground">Type</span>
                            <select
                                value={row.type}
                                onChange={(event) =>
                                    updateFundingScenario(onFormChange, index, {
                                        type: event.target
                                            .value as FundingScenarioRow['type'],
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="bank_loan">Bank loan</option>
                                <option value="investor">Investor</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </label>
                        <BudgetInput
                            label="Amount"
                            type="number"
                            value={row.amount}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    amount: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Year"
                            type="number"
                            value={row.year ?? 1}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    year: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Interest %"
                            type="number"
                            value={row.interest_rate_percent ?? 0}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    interest_rate_percent: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Term"
                            type="number"
                            value={row.term_years ?? 0}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    term_years: value,
                                })
                            }
                        />
                        <BudgetInput
                            label="Equity %"
                            type="number"
                            value={row.investor_equity_percent ?? 0}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    investor_equity_percent: value,
                                })
                            }
                        />
                        <BudgetConfidenceSelect
                            value={budgetRowConfidence(row.confidence)}
                            onChange={(value) =>
                                updateFundingScenario(onFormChange, index, {
                                    confidence: value,
                                })
                            }
                        />
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                title="Remove row"
                                onClick={() =>
                                    onFormChange((current) => ({
                                        ...current,
                                        funding_scenarios:
                                            current.funding_scenarios.filter(
                                                (_row, rowIndex) =>
                                                    rowIndex !== index,
                                            ),
                                    }))
                                }
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function BudgetSourceDetail({
    label,
    value,
    helper,
}: {
    label: string;
    value: string;
    helper?: string;
}) {
    return (
        <div className="min-w-0">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 truncate font-medium">{value}</div>
            {helper ? (
                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                    {helper}
                </p>
            ) : null}
        </div>
    );
}

function BudgetInput({
    label,
    value,
    onChange,
    type = 'text',
    min,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    type?: 'text' | 'number';
    min?: number;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <input
                type={type}
                value={value}
                min={type === 'number' ? (min ?? 0) : undefined}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
            />
        </label>
    );
}

function BudgetConfidenceSelect({
    value,
    onChange,
}: {
    value: NonNullable<BudgetRow['confidence']>;
    onChange: (value: NonNullable<BudgetRow['confidence']>) => void;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span className="text-muted-foreground">Confidence</span>
            <select
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value as NonNullable<
                            BudgetRow['confidence']
                        >,
                    )
                }
                className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
            >
                <option value="known">Known</option>
                <option value="estimate">Estimate</option>
                <option value="guess">Guess</option>
            </select>
        </label>
    );
}

function BudgetMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-background p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function AdvisorBudgetPreview({
    budget,
    form,
}: {
    budget: BudgetPayload;
    form: BudgetFormState;
}) {
    const computed = budget.computed ?? {};
    const confidence = confidenceSummary(form);
    const activeFlags = budget.active_flags ?? [];

    return (
        <section className="space-y-3 rounded-md border bg-background p-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Eye className="size-4" aria-hidden="true" />
                    Advisor view
                </div>
                <Badge
                    variant={
                        budget.status === 'complete' ? 'secondary' : 'outline'
                    }
                >
                    {formatLabel(budget.status)}
                </Badge>
            </div>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <AdvisorPreviewItem
                    label="Expected runway"
                    value={formatRunway(budget.expected_runway_months, false)}
                />
                <AdvisorPreviewItem
                    label="Calculated runway"
                    value={formatRunway(
                        computed.runway_months,
                        computed.runway_open_ended,
                    )}
                />
                <AdvisorPreviewItem
                    label="Break-even year"
                    value={formatYear(computed.break_even_year)}
                />
                <AdvisorPreviewItem
                    label="Profit year"
                    value={formatYear(computed.first_profitable_year)}
                />
                <AdvisorPreviewItem
                    label="Cash positive"
                    value={formatYear(computed.cash_flow_positive_year)}
                />
                <AdvisorPreviewItem
                    label="After launch"
                    value={formatCurrency(computed.available_after_launch)}
                />
                <AdvisorPreviewItem
                    label="Confidence"
                    value={`${confidence.known} known, ${countLabel(confidence.estimate, 'estimate')}, ${countLabel(confidence.guess, 'guess', 'guesses')}`}
                />
                <AdvisorPreviewItem
                    label="Coaching prompts"
                    value={
                        activeFlags.length > 0
                            ? activeFlags.map((flag) => flag.title).join('; ')
                            : 'None unresolved'
                    }
                />
            </dl>
        </section>
    );
}

function AdvisorPreviewItem({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="grid gap-1 rounded-md border bg-muted/20 p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

function budgetRow(
    label: string,
    amount: number,
    confidence: NonNullable<BudgetRow['confidence']> = 'estimate',
    extra: Partial<BudgetRow> = {},
): BudgetRow {
    return {
        label,
        amount,
        quantity: extra.quantity ?? 1,
        confidence,
        ...extra,
    };
}

const budgetRowDescriptions: Record<string, string> = {
    'product or platform setup':
        'The one-off cost to build or configure what customers will use: a prototype, app, website platform, booking system, payment setup, member portal, no-code tools, or integrations.',
    'brand, landing page, and content':
        'The basics that make the offer credible online: naming, logo or visual identity, landing page copy, images, explainer content, and simple sales material.',
    'launch campaign':
        'The first push to get attention and early customers, such as ads, email outreach, social content, flyers, launch event costs, or promotional offers.',
    'hosting and software tools':
        'Monthly tools needed to keep the product or platform running, such as hosting, domain services, email tools, subscriptions, plugins, or workflow software.',
    'support and admin tools':
        'Tools or services used to help customers and manage operations, such as helpdesk, booking, CRM, payment admin, document storage, or scheduling.',
    'content or product maintenance':
        'Ongoing work needed to keep the product useful, such as updates, new content, bug fixes, community moderation, or small contractor help.',
    'monthly subscribers or members':
        'Expected recurring customers. Amount is the monthly price per subscriber or member, and Qty is the number of paying people expected that month.',
    'founder cash':
        'Money the founder can realistically contribute to start the business. It can be changed later if the amount is only a guess.',
    'website and domain':
        'One-off setup for a basic website, domain name, email domain, landing page, or simple online presence.',
    'basic equipment':
        'Practical items needed before serving customers, such as laptop, tools, furniture, devices, packaging equipment, or starter supplies.',
    'launch marketing':
        'Initial marketing spend to help people discover the business, such as ads, flyers, photography, launch content, or promotional discounts.',
    'brand and website setup':
        'Initial brand and online setup, such as logo, colours, simple website, domain, email, profile pages, and basic sales copy.',
    'professional templates':
        'Reusable documents needed to deliver the service, such as proposals, agreements, onboarding forms, session notes, checklists, or reports.',
    'launch outreach':
        'Initial effort to reach potential customers, such as email campaigns, calls, networking events, direct messages, or introductory offers.',
    'opening stock':
        'Inventory needed before sales can start. This can include finished goods, raw materials, samples, or minimum order quantities.',
    'display, packaging, or signage':
        'Items that help present and sell products, such as packaging, labels, shelves, displays, market stall setup, or signs.',
    'point of sale setup':
        'Tools for taking payments and tracking sales, such as card reader, POS software, barcode labels, receipt printer, or payment setup.',
    'online store setup':
        'One-off setup for an ecommerce store, product pages, payment gateway, checkout, shipping rules, and basic integrations.',
    'opening inventory':
        'Initial products or materials needed to start selling online.',
    'launch ads and content':
        'Initial paid or organic content used to drive traffic, such as social ads, videos, photos, product copy, or influencer samples.',
    'tools and equipment':
        'Tools, safety gear, devices, or specialist equipment needed to deliver the work.',
    'vehicle setup or signage':
        'Vehicle-related setup, such as signage, storage, racks, fit-out, registration changes, or initial road-ready costs.',
    'licences and safety gear':
        'Required licences, certifications, compliance items, protective gear, or safety setup before work begins.',
};

function budgetRowDescription(row: BudgetRow): string {
    const label = budgetRowLabel(row);

    return (
        row.description ??
        budgetRowDescriptions[label.toLowerCase()] ??
        'Add this suggested line to your budget, then adjust the amount and confidence level if needed.'
    );
}

function inferBudgetTemplateKey(
    plan: BusinessPlanPayload,
    ideaValidation: IdeaValidationPayload,
): BudgetTemplateKey {
    const text = budgetSourceText(plan, ideaValidation);

    if (
        hasAny(text, [
            'food',
            'cafe',
            'coffee',
            'catering',
            'kitchen',
            'hospitality',
            'restaurant',
        ])
    ) {
        return 'food';
    }

    if (
        hasAny(text, [
            'subscription',
            'membership',
            'saas',
            'recurring',
            'software platform',
        ])
    ) {
        return 'subscription';
    }

    if (
        hasAny(text, [
            'online store',
            'ecommerce',
            'e-commerce',
            'shopify',
            'website sales',
            'direct to customer',
        ])
    ) {
        return 'online';
    }

    if (
        hasAny(text, [
            'retail',
            'stock',
            'inventory',
            'products',
            'store',
            'shop',
            'market stall',
        ])
    ) {
        return 'retail';
    }

    if (
        hasAny(text, [
            'trade',
            'tools',
            'installation',
            'repairs',
            'maintenance',
            'vehicle',
            'site work',
        ])
    ) {
        return 'trades';
    }

    if (
        hasAny(text, [
            'consulting',
            'coaching',
            'training',
            'advisory',
            'workshop',
            'mentor',
        ])
    ) {
        return 'consulting';
    }

    return 'service';
}

function budgetRowsFromPlan(
    plan: BusinessPlanPayload,
    ideaValidation: IdeaValidationPayload,
    templateKey: BudgetTemplateKey,
): BudgetTemplate {
    const text = budgetSourceText(plan, ideaValidation);
    const template = budgetTemplates[templateKey];
    const launchCosts = [...template.launch_costs];
    const monthlyFixedCosts = [...template.monthly_fixed_costs];
    const revenueForecast = [...template.revenue_forecast];
    const fundingSources = [...template.funding_sources];

    if (hasAny(text, ['website', 'domain', 'landing page'])) {
        launchCosts.push(
            budgetRow('Website, domain, or landing page', 900, 'guess'),
        );
    }

    if (hasAny(text, ['marketing', 'ads', 'launch campaign', 'social media'])) {
        launchCosts.push(budgetRow('Launch marketing campaign', 1200, 'guess'));
    }

    if (hasAny(text, ['licence', 'license', 'permit', 'compliance', 'legal'])) {
        launchCosts.push(
            budgetRow('Licences, permits, or compliance setup', 800, 'guess'),
        );
    }

    if (hasAny(text, ['software', 'system', 'crm', 'booking', 'accounting'])) {
        monthlyFixedCosts.push(
            budgetRow('Software and operating systems', 180, 'estimate'),
        );
    }

    if (hasAny(text, ['insurance', 'liability'])) {
        monthlyFixedCosts.push(budgetRow('Insurance', 200, 'guess'));
    }

    if (hasAny(text, ['rent', 'premises', 'workspace', 'office', 'storage'])) {
        monthlyFixedCosts.push(
            budgetRow('Premises, workspace, or storage', 700, 'guess'),
        );
    }

    if (hasAny(text, ['grant'])) {
        fundingSources.push(
            budgetRow('Potential grant funding', 3000, 'guess'),
        );
    }

    if (hasAny(text, ['loan', 'finance', 'lending'])) {
        fundingSources.push(
            budgetRow('Potential loan or finance', 5000, 'guess'),
        );
    }

    if (hasAny(text, ['pre-sale', 'presale', 'deposit'])) {
        fundingSources.push(
            budgetRow('Customer deposits or pre-sales', 1500, 'guess'),
        );
    }

    const revenueModel = ideaValidation?.revenue_model?.trim();

    if (revenueModel) {
        revenueForecast.push(
            budgetRow(
                `Revenue from ${revenueModel.slice(0, 90)}`,
                500,
                'guess',
                {
                    quantity: 3,
                    month: 1,
                    variable_cost_percent: 20,
                },
            ),
        );
    }

    return {
        ...template,
        launch_costs: dedupeBudgetRows(launchCosts),
        monthly_fixed_costs: dedupeBudgetRows(monthlyFixedCosts),
        revenue_forecast: dedupeBudgetRows(revenueForecast),
        funding_sources: dedupeBudgetRows(fundingSources),
    };
}

function budgetAssumptionsFromPlan(
    plan: BusinessPlanPayload,
): Partial<BudgetAssumptions> {
    const section = plan?.phases
        .flatMap((phase) => phase.sections)
        .find(
            (row) => row.requirement_key === BUDGET_ASSUMPTIONS_REQUIREMENT_KEY,
        );
    const text = `${section?.title ?? ''} ${section?.body ?? ''}`.toLowerCase();

    return {
        revenue_growth_percent: percentNear(text, [
            'revenue growth',
            'sales growth',
            'growth',
        ]),
        cost_inflation_percent: percentNear(text, [
            'cost inflation',
            'cpi',
            'inflation',
        ]),
        target_gross_profit_percent: percentNear(text, [
            'gross profit',
            'gp',
            'margin',
        ]),
        target_net_profit_before_tax_percent: percentNear(text, [
            'net profit before tax',
            'npbt',
            'before tax',
        ]),
        target_net_profit_after_tax_percent: percentNear(text, [
            'net profit after tax',
            'npat',
            'after tax',
        ]),
    };
}

function mergeBudgetAssumptions(
    current: BudgetAssumptions,
    suggested: Partial<BudgetAssumptions>,
): BudgetAssumptions {
    return {
        revenue_growth_percent:
            current.revenue_growth_percent ||
            suggested.revenue_growth_percent ||
            '',
        cost_inflation_percent:
            current.cost_inflation_percent ||
            suggested.cost_inflation_percent ||
            '',
        target_gross_profit_percent:
            current.target_gross_profit_percent ||
            suggested.target_gross_profit_percent ||
            '',
        target_net_profit_before_tax_percent:
            current.target_net_profit_before_tax_percent ||
            suggested.target_net_profit_before_tax_percent ||
            '',
        target_net_profit_after_tax_percent:
            current.target_net_profit_after_tax_percent ||
            suggested.target_net_profit_after_tax_percent ||
            '',
    };
}

function percentNear(text: string, labels: string[]): number | '' {
    for (const label of labels) {
        const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const after = new RegExp(
            `${escaped}[^0-9%]{0,60}(\\d+(?:\\.\\d+)?)\\s*%`,
        );
        const before = new RegExp(
            `(\\d+(?:\\.\\d+)?)\\s*%[^.]{0,60}${escaped}`,
        );
        const match = text.match(after) ?? text.match(before);

        if (match?.[1]) {
            return numberFromInput(match[1]);
        }
    }

    return '';
}

function budgetSourceText(
    plan: BusinessPlanPayload,
    ideaValidation: IdeaValidationPayload,
): string {
    return [
        ideaValidation?.problem,
        ideaValidation?.target_customer,
        ideaValidation?.solution,
        ideaValidation?.value_proposition,
        ideaValidation?.demand_signal,
        ideaValidation?.revenue_model,
        ...(plan?.phases ?? []).flatMap((phase) =>
            phase.sections.flatMap((section) => [section.title, section.body]),
        ),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

function hasAny(text: string, needles: string[]): boolean {
    return needles.some((needle) => text.includes(needle));
}

function mergeBudgetRows(
    currentRows: BudgetRow[],
    suggestedRows: BudgetRow[],
    revenue = false,
    timed = false,
) {
    return dedupeBudgetRows([
        ...currentRows
            .map((row) => normaliseBudgetRow(row, revenue, timed))
            .filter((row) => !isBlankBudgetRow(row)),
        ...suggestedRows.map((row) => ({
            ...normaliseBudgetRow(row, revenue, timed),
            confidence: row.confidence ?? 'guess',
        })),
    ]);
}

function dedupeBudgetRows(rows: BudgetRow[]) {
    const seen = new Set<string>();

    return rows.filter((row) => {
        const key = budgetRowLabel(row).toLowerCase();

        if (key === '' || seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

function isBlankBudgetRow(row: BudgetRow): boolean {
    return budgetRowLabel(row) === '' && numberFromInput(row.amount) === 0;
}

function confidenceSummary(form: BudgetFormState) {
    const rows = [
        ...form.launch_costs,
        ...form.monthly_fixed_costs,
        ...form.future_costs,
        ...form.revenue_forecast,
        ...form.funding_sources,
    ];
    const scenarioConfidence = form.funding_scenarios
        .filter(
            (row) =>
                String(row.name ?? '').trim() !== '' ||
                numberFromInput(row.amount) > 0,
        )
        .map((row) => row.confidence ?? 'estimate');

    return rows
        .filter((row) => !isBlankBudgetRow(row))
        .map((row) => row.confidence ?? 'estimate')
        .concat(scenarioConfidence)
        .reduce(
            (summary, confidence) => ({
                ...summary,
                [confidence]: summary[confidence] + 1,
            }),
            { known: 0, estimate: 0, guess: 0 },
        );
}

function updateBudgetRow(
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>,
    group: BudgetGroupKey,
    index: number,
    patch: Partial<BudgetRow>,
) {
    onFormChange((current) => ({
        ...current,
        [group]: current[group].map((row, rowIndex) =>
            rowIndex === index ? { ...row, ...patch } : row,
        ),
    }));
}

function updateFutureCostRow(
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>,
    index: number,
    patch: Partial<FutureCostRow>,
) {
    onFormChange((current) => ({
        ...current,
        future_costs: current.future_costs.map((row, rowIndex) =>
            rowIndex === index ? { ...row, ...patch } : row,
        ),
    }));
}

function updateFundingScenario(
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>,
    index: number,
    patch: Partial<FundingScenarioRow>,
) {
    onFormChange((current) => ({
        ...current,
        funding_scenarios: current.funding_scenarios.map((row, rowIndex) =>
            rowIndex === index ? { ...row, ...patch } : row,
        ),
    }));
}

function budgetToForm(budget: BudgetPayload | undefined): BudgetFormState {
    return {
        expected_runway_months:
            budget?.expected_runway_months === null ||
            budget?.expected_runway_months === undefined
                ? ''
                : String(budget.expected_runway_months),
        forecast_years: String(budget?.forecast_years ?? 3),
        assumptions: normaliseBudgetAssumptions(budget?.assumptions),
        launch_costs: rowsOrBlank(budget?.launch_costs, false, true),
        monthly_fixed_costs: rowsOrBlank(budget?.monthly_fixed_costs),
        future_costs: futureRowsOrBlank(budget?.future_costs),
        revenue_forecast: rowsOrBlank(budget?.revenue_forecast, true),
        funding_sources: rowsOrBlank(budget?.funding_sources),
        funding_scenarios: fundingScenariosOrBlank(budget?.funding_scenarios),
    };
}

function rowsOrBlank(
    rows: BudgetRow[] | undefined,
    revenue = false,
    timed = false,
) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseBudgetRow(row, revenue, timed))
        : [blankBudgetRow(revenue, timed)];
}

function futureRowsOrBlank(rows: FutureCostRow[] | undefined) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseFutureCostRow(row))
        : [blankFutureCostRow()];
}

function fundingScenariosOrBlank(rows: FundingScenarioRow[] | undefined) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseFundingScenario(row))
        : [blankFundingScenario()];
}

function blankBudgetRow(revenue = false, timed = false): BudgetRow {
    return revenue
        ? {
              label: '',
              amount: '',
              quantity: 1,
              month: 1,
              monthly_growth_percent: 0,
              variable_cost_percent: 0,
              unit_cost: '',
              gross_profit_percent: '',
              confidence: 'estimate',
          }
        : timed
          ? {
                label: '',
                amount: '',
                quantity: 1,
                month: 1,
                confidence: 'estimate',
            }
          : {
                label: '',
                amount: '',
                quantity: 1,
                confidence: 'estimate',
            };
}

function blankFutureCostRow(): FutureCostRow {
    return {
        label: '',
        amount: '',
        quantity: 1,
        year: 2,
        recurring: false,
        confidence: 'estimate',
    };
}

function blankFundingScenario(): FundingScenarioRow {
    return {
        name: '',
        type: 'bank_loan',
        amount: '',
        year: 1,
        interest_rate_percent: 0,
        term_years: 5,
        interest_only_months: 0,
        investor_equity_percent: 0,
        confidence: 'estimate',
    };
}

function cleanBudgetForm(form: BudgetFormState) {
    return {
        expected_runway_months:
            form.expected_runway_months === ''
                ? null
                : numberFromInput(form.expected_runway_months),
        forecast_years: normaliseForecastYears(form.forecast_years),
        assumptions: {
            revenue_growth_percent: signedNumberFromInput(
                form.assumptions.revenue_growth_percent,
            ),
            cost_inflation_percent: signedNumberFromInput(
                form.assumptions.cost_inflation_percent,
                -100,
                100,
            ),
            target_gross_profit_percent: numberFromInput(
                form.assumptions.target_gross_profit_percent,
            ),
            target_net_profit_before_tax_percent: numberFromInput(
                form.assumptions.target_net_profit_before_tax_percent,
            ),
            target_net_profit_after_tax_percent: numberFromInput(
                form.assumptions.target_net_profit_after_tax_percent,
            ),
        },
        launch_costs: cleanBudgetRows(form.launch_costs, false, true),
        monthly_fixed_costs: cleanBudgetRows(form.monthly_fixed_costs),
        future_costs: cleanFutureCostRows(form.future_costs),
        revenue_forecast: cleanBudgetRows(form.revenue_forecast, true),
        funding_sources: cleanBudgetRows(form.funding_sources),
        funding_scenarios: cleanFundingScenarios(form.funding_scenarios),
    };
}

function cleanBudgetRows(rows: BudgetRow[], revenue = false, timed = false) {
    return rows
        .filter(
            (row) =>
                budgetRowLabel(row) !== '' || numberFromInput(row.amount) > 0,
        )
        .map((row) => ({
            label: budgetRowLabel(row),
            amount: numberFromInput(row.amount),
            quantity: numberFromInput(row.quantity ?? 1) || 1,
            confidence: budgetRowConfidence(row.confidence),
            ...(timed || revenue
                ? { month: numberFromInput(row.month ?? 1) || 1 }
                : {}),
            ...(revenue
                ? {
                      monthly_growth_percent: signedNumberFromInput(
                          row.monthly_growth_percent ?? 0,
                      ),
                      variable_cost_percent: numberFromInput(
                          row.variable_cost_percent ?? 0,
                      ),
                      unit_cost: numberFromInput(row.unit_cost ?? 0),
                      gross_profit_percent:
                          row.gross_profit_percent === '' ||
                          row.gross_profit_percent === undefined
                              ? null
                              : numberFromInput(row.gross_profit_percent),
                  }
                : {}),
        }));
}

function cleanFutureCostRows(rows: FutureCostRow[]) {
    return rows
        .filter(
            (row) =>
                budgetRowLabel(row) !== '' || numberFromInput(row.amount) > 0,
        )
        .map((row) => ({
            label: budgetRowLabel(row),
            amount: numberFromInput(row.amount),
            quantity: numberFromInput(row.quantity ?? 1) || 1,
            year: Math.min(5, Math.max(2, numberFromInput(row.year ?? 2) || 2)),
            recurring: Boolean(row.recurring),
            confidence: budgetRowConfidence(row.confidence),
        }));
}

function cleanFundingScenarios(rows: FundingScenarioRow[]) {
    return rows
        .filter(
            (row) =>
                String(row.name ?? '').trim() !== '' ||
                numberFromInput(row.amount) > 0,
        )
        .map((row) => ({
            name: String(row.name ?? '').trim(),
            type:
                row.type === 'investor' || row.type === 'mixed'
                    ? row.type
                    : 'bank_loan',
            amount: numberFromInput(row.amount),
            year: Math.min(5, Math.max(1, numberFromInput(row.year ?? 1) || 1)),
            interest_rate_percent: numberFromInput(
                row.interest_rate_percent ?? 0,
            ),
            term_years: numberFromInput(row.term_years ?? 0),
            interest_only_months: numberFromInput(
                row.interest_only_months ?? 0,
            ),
            investor_equity_percent: numberFromInput(
                row.investor_equity_percent ?? 0,
            ),
            confidence: budgetRowConfidence(row.confidence),
        }));
}

function normaliseBudgetRow(
    row: BudgetRow,
    revenue = false,
    timed = false,
): BudgetRow {
    return {
        label: budgetRowLabel(row),
        amount: row.amount ?? '',
        quantity: numberFromInput(row.quantity ?? 1) || 1,
        confidence: budgetRowConfidence(row.confidence),
        ...(timed || revenue
            ? { month: numberFromInput(row.month ?? 1) || 1 }
            : {}),
        ...(revenue
            ? {
                  monthly_growth_percent: signedNumberFromInput(
                      row.monthly_growth_percent ?? 0,
                  ),
                  variable_cost_percent: numberFromInput(
                      row.variable_cost_percent ?? 0,
                  ),
                  unit_cost:
                      row.unit_cost === undefined || row.unit_cost === null
                          ? ''
                          : row.unit_cost,
                  gross_profit_percent:
                      row.gross_profit_percent === undefined ||
                      row.gross_profit_percent === null
                          ? ''
                          : row.gross_profit_percent,
              }
            : {}),
    };
}

function normaliseFutureCostRow(row: FutureCostRow): FutureCostRow {
    return {
        label: budgetRowLabel(row),
        amount: row.amount ?? '',
        quantity: numberFromInput(row.quantity ?? 1) || 1,
        year: Math.min(5, Math.max(2, numberFromInput(row.year ?? 2) || 2)),
        recurring: Boolean(row.recurring),
        confidence: budgetRowConfidence(row.confidence),
    };
}

function normaliseFundingScenario(row: FundingScenarioRow): FundingScenarioRow {
    return {
        name: String(row.name ?? '').trim(),
        type:
            row.type === 'investor' || row.type === 'mixed'
                ? row.type
                : 'bank_loan',
        amount: row.amount ?? '',
        year: Math.min(5, Math.max(1, numberFromInput(row.year ?? 1) || 1)),
        interest_rate_percent: row.interest_rate_percent ?? 0,
        term_years: row.term_years ?? 5,
        interest_only_months: row.interest_only_months ?? 0,
        investor_equity_percent: row.investor_equity_percent ?? 0,
        confidence: budgetRowConfidence(row.confidence),
    };
}

function normaliseBudgetAssumptions(
    assumptions: BudgetPayload['assumptions'] | undefined,
): BudgetAssumptions {
    return {
        revenue_growth_percent: assumptions?.revenue_growth_percent ?? '',
        cost_inflation_percent: assumptions?.cost_inflation_percent ?? '',
        target_gross_profit_percent:
            assumptions?.target_gross_profit_percent ?? '',
        target_net_profit_before_tax_percent:
            assumptions?.target_net_profit_before_tax_percent ?? '',
        target_net_profit_after_tax_percent:
            assumptions?.target_net_profit_after_tax_percent ?? '',
    };
}

function budgetRowLabel(row: BudgetRow): string {
    return String(row.label ?? '').trim();
}

function budgetRowConfidence(
    confidence: BudgetRow['confidence'] | null | undefined,
): NonNullable<BudgetRow['confidence']> {
    return confidence === 'known' || confidence === 'guess'
        ? confidence
        : 'estimate';
}

function numberFromInput(value: string | number | null | undefined): number {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value ?? '').replace(/[^0-9.-]/g, ''));

    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

function signedNumberFromInput(
    value: string | number | null | undefined,
    min = -100,
    max = 500,
): number {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value ?? '').replace(/[^0-9.-]/g, ''));

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.min(max, Math.max(min, parsed));
}

function normaliseForecastYears(value: string | number): number {
    const years = numberFromInput(value);

    return years === 1 || years === 2 || years === 5 ? years : 3;
}

function formatCurrency(value: number | null | undefined): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

function countLabel(
    count: number,
    singular: string,
    plural = `${singular}s`,
): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

function formatRunway(
    months: number | null | undefined,
    openEnded: boolean | undefined,
): string {
    if (months === null || months === undefined) {
        return '-';
    }

    return openEnded ? `${months}+ months` : `${months} months`;
}

function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : 'Not reached';
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{value || '-'}</dd>
        </div>
    );
}

function findSection(
    plan: BusinessPlanPayload,
    requirement: PlanRequirementPayload,
): PlanSectionPayload | null {
    return (
        plan?.phases
            .flatMap((phase) => phase.sections)
            .find(
                (section) =>
                    section.requirement_key === requirement.key ||
                    section.id === requirement.section_id,
            ) ?? null
    );
}

function budgetPlanSource(
    plan: BusinessPlanPayload,
    requirementKey: string,
): {
    requirement: PlanRequirementPayload | null;
    section: PlanSectionPayload | null;
} {
    const requirement =
        plan?.phases
            .flatMap((phase) => phase.requirements)
            .find((row) => row.key === requirementKey) ?? null;

    return {
        requirement,
        section: requirement ? findSection(plan, requirement) : null,
    };
}

function requirementId(requirement: PlanRequirementPayload): string {
    return `${requirement.phase_key}:${requirement.key}`;
}

function displayStageLabel(
    stage: string | null | undefined,
    label: string | null | undefined,
): string {
    if (stage === 'onboarding' || label === 'Onboarding') {
        return 'Getting started';
    }

    return label ?? '-';
}

function journeyLevelLabel(
    level: GamificationPayload['current_level'] | undefined,
): string {
    if (!level) {
        return 'Journey active';
    }

    if (level.stage === 'onboarding') {
        return level.phase
            ? `Getting started phase ${level.phase}`
            : 'Getting started';
    }

    return level.label;
}

function nextMilestoneLabel(
    milestone: NonNullable<GamificationPayload['next_milestone']>,
): string {
    return milestone.label === 'Idea validated'
        ? 'Idea validation'
        : milestone.label;
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function ideaValidationToForm(
    ideaValidation: IdeaValidationPayload,
): IdeaValidationForm {
    return {
        problem: ideaValidation?.problem ?? '',
        target_customer: ideaValidation?.target_customer ?? '',
        solution: ideaValidation?.solution ?? '',
        value_proposition: ideaValidation?.value_proposition ?? '',
        demand_signal: ideaValidation?.demand_signal ?? '',
        revenue_model: ideaValidation?.revenue_model ?? '',
    };
}

function IdeaValidationSnapshot({
    fields,
    revisionNumber,
    submittedAt,
}: {
    fields: { label: string; value: string }[];
    revisionNumber: number | null;
    submittedAt: string | null;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="text-xs font-medium text-muted-foreground">
                    Submitted idea validation
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {revisionNumber ? (
                        <Badge variant="outline">
                            Version {revisionNumber}
                        </Badge>
                    ) : null}
                    {submittedAt ? (
                        <Badge variant="outline">
                            Submitted {formatDate(submittedAt)}
                        </Badge>
                    ) : null}
                </div>
            </div>
            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {fields.map((field) => (
                    <div
                        key={field.label}
                        className="rounded-md border bg-card p-3"
                    >
                        <div className="text-xs font-medium text-muted-foreground">
                            {field.label}
                        </div>
                        <p className="mt-1 text-sm whitespace-pre-line">
                            {field.value || '-'}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function IdeaValidationHistory({
    versions,
    restoringVersionId,
    onRestore,
}: {
    versions: IdeaValidationVersion[];
    restoringVersionId: string | null;
    onRestore: (version: IdeaValidationVersion) => void;
}) {
    return (
        <div className="space-y-3 border-t pt-4">
            <h3 className="text-sm font-medium">Revision history</h3>
            <div className="grid gap-3 lg:grid-cols-2">
                {versions.map((version) => (
                    <div
                        key={version.id}
                        className="space-y-3 rounded-md border bg-muted/20 p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium">
                                    Version {version.revision_number}
                                </span>
                                {version.is_current ? (
                                    <Badge variant="secondary">Current</Badge>
                                ) : (
                                    <Badge variant="outline">
                                        {ideaVersionStatusLabel(version)}
                                    </Badge>
                                )}
                            </div>
                            {version.evaluated_at ? (
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(version.evaluated_at)}
                                </span>
                            ) : null}
                        </div>
                        <dl className="grid gap-2 text-sm">
                            <VersionDetail
                                label="Problem"
                                value={version.problem}
                            />
                            <VersionDetail
                                label="Target customer"
                                value={version.target_customer}
                            />
                            <VersionDetail
                                label="Demand signal"
                                value={version.demand_signal}
                            />
                        </dl>
                        {!version.is_current ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={restoringVersionId !== null}
                                onClick={() => onRestore(version)}
                            >
                                <RotateCcw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {restoringVersionId === version.id
                                    ? 'Restoring...'
                                    : 'Restore as new revision'}
                            </Button>
                        ) : null}
                    </div>
                ))}
            </div>
        </div>
    );
}

function VersionDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 whitespace-pre-line">{value || '-'}</dd>
        </div>
    );
}

function ideaVersionStatusLabel(version: IdeaValidationVersion): string {
    if (version.recalled_at) {
        return 'Recalled';
    }

    if (version.advisor_gate_status === 'approved') {
        return 'Approved';
    }

    if (version.advisor_gate_status === 'changes_requested') {
        return 'Changes requested';
    }

    return 'Advisor review';
}

const ideaFields = [
    {
        key: 'problem',
        label: 'Problem',
        minimum: 5,
        placeholder: 'What specific customer problem are you solving?',
    },
    {
        key: 'target_customer',
        label: 'Target customer',
        minimum: 3,
        placeholder: 'Who has this problem and how do you know?',
    },
    {
        key: 'solution',
        label: 'Solution',
        minimum: 10,
        placeholder: 'What will you offer and how will it work?',
    },
    {
        key: 'value_proposition',
        label: 'Value proposition',
        minimum: 10,
        placeholder: 'Why would the customer choose this over alternatives?',
    },
    {
        key: 'demand_signal',
        label: 'Demand signal',
        minimum: 5,
        placeholder: 'What evidence shows people want or need this?',
    },
    {
        key: 'revenue_model',
        label: 'Revenue model',
        minimum: 5,
        placeholder: 'How will the business earn, collect, and retain revenue?',
    },
] satisfies {
    key: keyof IdeaValidationForm;
    label: string;
    minimum: number;
    placeholder: string;
}[];

EntrepreneurPlan.layout = {
    breadcrumbs: [
        {
            title: 'Business Plan',
            href: '/portal/entrepreneur/plan',
        },
    ],
};
