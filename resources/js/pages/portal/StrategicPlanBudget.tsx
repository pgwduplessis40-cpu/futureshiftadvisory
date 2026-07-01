import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    Info,
    LockKeyhole,
    Plus,
    Save,
    Send,
    TrendingUp,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, CSSProperties, ReactNode } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    engagement_type_label: string;
};

type BudgetRow = {
    label?: string;
    amount?: number | string;
    quantity?: number | string;
    month?: number | string;
    monthly_growth_percent?: number | string;
    variable_cost_percent?: number | string;
    unit_cost?: number | string;
    gross_profit_percent?: number | string;
    confidence?: 'known' | 'estimate' | 'guess';
};

type FutureCostRow = BudgetRow & {
    year?: number | string;
    recurring?: boolean;
};

type FundingScenarioRow = {
    name?: string;
    type?: 'bank_loan' | 'investor' | 'mixed';
    amount?: number | string;
    year?: number | string;
    interest_rate_percent?: number | string;
    term_years?: number | string;
    interest_only_months?: number | string;
    investor_equity_percent?: number | string;
    confidence?: 'known' | 'estimate' | 'guess';
};

type BusinessPlanSection = {
    key: string;
    title: string;
    prompt: string;
    answer: string;
};

type BusinessPlanSourceDraft = {
    key: string;
    title: string;
    source_label: string;
    source_url: string;
    source_help: string;
    body: string;
};

type BudgetPayload = {
    id: string;
    label: string;
    pathway: string;
    status: string;
    status_label: string;
    locked: boolean;
    horizon_months: number;
    expected_runway_months: number | null;
    source_financials: {
        unlocked?: boolean;
        count?: number;
        system_review?: string;
        items?: Array<{
            id: string;
            filename: string;
            detected_as: string;
            uploaded_at: string | null;
        }>;
    };
    client_goals: GoalPayload[];
    advisor_goals: GoalPayload[];
    business_plan_sections: BusinessPlanSection[];
    business_plan_source_drafts: BusinessPlanSourceDraft[];
    business_plan_prompts: Array<{
        key: string;
        title: string;
        prompt: string;
    }>;
    business_plan_readiness_score: number;
    business_plan_ready: boolean;
    business_plan_submitted_at: string | null;
    business_plan_approved_at: string | null;
    assumptions: Record<string, number | string | null>;
    implementation_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    future_costs: FutureCostRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
    funding_scenarios: FundingScenarioRow[];
    computed: {
        total_launch_costs?: number;
        monthly_fixed_costs?: number;
        total_funding?: number;
        available_after_launch?: number;
        break_even_year?: number | null;
        cash_flow_positive_year?: number | null;
        runway_months?: number | null;
        runway_open_ended?: boolean;
        annual_totals?: Array<Record<string, number>>;
    };
    flags: Array<{
        key: string;
        title: string;
        message: string;
        severity: string;
    }>;
    confidence: {
        score?: number;
        progress_score?: number;
        overall?: string;
        message?: string;
    };
    readiness_score: number;
    progress_score: number;
    submitted_at: string | null;
    approved_at: string | null;
    used_in_proposal_at: string | null;
    accepted_snapshot_at: string | null;
    update_url: string;
    submit_url: string;
    budget_pack_available: boolean;
    budget_pack_locked_reason: string | null;
};

type GoalPayload = {
    title: string;
    measure?: string | null;
    owner?: string;
    locked?: boolean;
};

type Props = {
    client: ClientPayload;
    budget: BudgetPayload;
    documentUploadUrl: string;
    onboardingUrl: string;
    dashboardUrl: string;
};

type BudgetForm = {
    business_plan_sections: BusinessPlanSection[];
    horizon_months: number;
    expected_runway_months: string;
    assumptions: {
        revenue_growth_percent: string;
        cost_inflation_percent: string;
        target_gross_profit_percent: string;
        target_net_profit_before_tax_percent: string;
        target_net_profit_after_tax_percent: string;
    };
    implementation_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    future_costs: FutureCostRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
    funding_scenarios: FundingScenarioRow[];
};

type WorkspaceTab = 'business_plan' | 'budget';

type BudgetGroupKey =
    | 'implementation_costs'
    | 'monthly_fixed_costs'
    | 'revenue_forecast'
    | 'funding_sources';

export default function StrategicPlanBudget({
    client,
    budget,
    documentUploadUrl,
    onboardingUrl,
    dashboardUrl,
}: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [uploadKey, setUploadKey] = useState(0);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [highlightedSection, setHighlightedSection] = useState<string | null>(
        null,
    );
    const [activeTab, setActiveTab] = useState<WorkspaceTab>('business_plan');
    const form = useForm<BudgetForm>({
        business_plan_sections:
            budget.business_plan_sections.length > 0
                ? budget.business_plan_sections
                : budget.business_plan_prompts.map((prompt) => ({
                      ...prompt,
                      answer: '',
                  })),
        horizon_months: budget.horizon_months,
        expected_runway_months:
            budget.expected_runway_months === null
                ? ''
                : String(budget.expected_runway_months),
        assumptions: {
            revenue_growth_percent: String(
                budget.assumptions.revenue_growth_percent ?? '',
            ),
            cost_inflation_percent: String(
                budget.assumptions.cost_inflation_percent ?? '',
            ),
            target_gross_profit_percent: String(
                budget.assumptions.target_gross_profit_percent ?? '',
            ),
            target_net_profit_before_tax_percent: String(
                budget.assumptions.target_net_profit_before_tax_percent ?? '',
            ),
            target_net_profit_after_tax_percent: String(
                budget.assumptions.target_net_profit_after_tax_percent ?? '',
            ),
        },
        implementation_costs:
            budget.implementation_costs.length > 0
                ? budget.implementation_costs
                : [blankRow()],
        monthly_fixed_costs:
            budget.monthly_fixed_costs.length > 0
                ? budget.monthly_fixed_costs
                : [blankRow()],
        future_costs: budget.future_costs,
        revenue_forecast:
            budget.revenue_forecast.length > 0
                ? budget.revenue_forecast
                : [blankRow(true)],
        funding_sources:
            budget.funding_sources.length > 0
                ? budget.funding_sources
                : [blankRow()],
        funding_scenarios: budget.funding_scenarios,
    });

    const uploadFinancials = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const data = new FormData();
        data.append('file', file);
        data.append('category', 'financial_statement');
        data.append(
            'claim_value',
            `Financial upload for ${budget.label} starting point.`,
        );
        data.append('question_prompt', `${budget.label} financial upload`);

        const response = await fetch(documentUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: data,
        });

        setUploading(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setUploadError(payload?.message ?? 'Upload failed.');

            return;
        }

        setFile(null);
        setUploadKey((key) => key + 1);
        router.reload();
    };

    const save = () => {
        form.post(budget.update_url, { preserveScroll: true });
    };

    const submit = () => {
        router.post(budget.submit_url, {}, { preserveScroll: true });
    };

    const focusBudgetSection = (
        sectionId: string,
        tab: WorkspaceTab = 'budget',
    ) => {
        setActiveTab(tab);

        window.setTimeout(() => {
            const section = document.getElementById(sectionId);

            if (!section) {
                return;
            }

            if (!section.hasAttribute('tabindex')) {
                section.setAttribute('tabindex', '-1');
            }

            setHighlightedSection(sectionId);
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.focus({ preventScroll: true });
            window.history.replaceState(null, '', `#${sectionId}`);
            window.setTimeout(() => {
                setHighlightedSection((current) =>
                    current === sectionId ? null : current,
                );
            }, 2200);
        }, 0);
    };

    return (
        <>
            <Head title={budget.label} />

            <main className="flex-1 space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {budget.label}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>
                                {client.trading_name || client.legal_name}
                            </span>
                            <span aria-hidden="true">/</span>
                            <span>{client.engagement_type_label}</span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={dashboardUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dashboard
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={save}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Save
                        </Button>
                        <Button
                            type="button"
                            disabled={budget.locked}
                            onClick={submit}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Submit for review
                        </Button>
                    </div>
                </div>

                <section
                    id="budget-section-financials"
                    className={cn(
                        'grid scroll-mt-24 gap-4 rounded-md transition-[background-color,box-shadow,border-color] duration-300 md:grid-cols-2 xl:grid-cols-5',
                        highlightedSection === 'budget-section-financials' &&
                            'bg-amber-50/70 ring-2 ring-amber-400/70',
                    )}
                    style={budgetTargetHighlightStyle(
                        highlightedSection === 'budget-section-financials',
                    )}
                >
                    <MetricPanel
                        icon={BookOpen}
                        label={
                            budget.pathway === 'npo'
                                ? 'Operating plan'
                                : 'Business plan'
                        }
                        value={`${budget.business_plan_readiness_score}/100`}
                        detail={
                            budget.business_plan_ready
                                ? 'Ready for advisor review'
                                : 'Client working draft'
                        }
                    />
                    <MetricPanel
                        icon={TrendingUp}
                        label="Progress"
                        value={`${budget.progress_score}%`}
                        detail={budget.status_label}
                    />
                    <MetricPanel
                        icon={CheckCircle2}
                        label="Readiness"
                        value={`${budget.readiness_score}/100`}
                        detail={budget.confidence.overall ?? 'preliminary'}
                    />
                    <MetricPanel
                        icon={FileSpreadsheet}
                        label="Financials"
                        value={`${budget.source_financials.count ?? 0} files`}
                        detail={
                            budget.source_financials.system_review ??
                            'Upload required'
                        }
                    />
                    <MetricPanel
                        icon={FileText}
                        label="Budget Pack"
                        value={
                            budget.budget_pack_available ? 'Unlocked' : 'Locked'
                        }
                        detail={
                            budget.budget_pack_locked_reason ??
                            'Accepted proposal snapshot'
                        }
                    />
                </section>

                <ProgressBand
                    score={budget.readiness_score}
                    message={
                        budget.confidence.message ??
                        'Budget confidence updates as evidence and assumptions improve.'
                    }
                />

                {budget.locked ? (
                    <div className="space-y-4">
                        <WorkspaceTabs
                            activeTab={activeTab}
                            label={budget.label}
                            onChange={setActiveTab}
                        />
                        {activeTab === 'business_plan' ? (
                            <div
                                id="budget-section-business-plan"
                                className={cn(
                                    'scroll-mt-24 rounded-md transition-[background-color,box-shadow,border-color] duration-300',
                                    highlightedSection ===
                                        'budget-section-business-plan' &&
                                        'bg-amber-50/70 ring-2 ring-amber-400/70',
                                )}
                                style={budgetTargetHighlightStyle(
                                    highlightedSection ===
                                        'budget-section-business-plan',
                                )}
                            >
                                <BusinessPlanEditor
                                    label={budget.label}
                                    sections={form.data.business_plan_sections}
                                    sourceDrafts={
                                        budget.business_plan_source_drafts
                                    }
                                    onSectionsChange={(sections) =>
                                        form.setData(
                                            'business_plan_sections',
                                            sections,
                                        )
                                    }
                                />
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        disabled={form.processing}
                                        onClick={save}
                                    >
                                        <Save
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {form.processing
                                            ? 'Saving'
                                            : 'Save plan'}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <LockedFinancialsPanel
                                file={file}
                                uploadKey={uploadKey}
                                uploading={uploading}
                                uploadError={uploadError}
                                onboardingUrl={onboardingUrl}
                                onFileChange={setFile}
                                onUpload={() => void uploadFinancials()}
                            />
                        )}
                    </div>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <section className="space-y-5 rounded-md border bg-background p-4">
                            <WorkspaceTabs
                                activeTab={activeTab}
                                label={budget.label}
                                onChange={setActiveTab}
                            />
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        {activeTab === 'business_plan'
                                            ? budget.pathway === 'npo'
                                                ? 'Operating plan workspace'
                                                : 'Business plan workspace'
                                            : 'Budget workspace'}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {activeTab === 'business_plan'
                                            ? 'Use source drafts on the left, then write the final client-owned plan on the right.'
                                            : 'All amounts are GST exclusive. GST is added only when final payment is sent to Stripe.'}
                                    </p>
                                </div>
                                <Badge variant="outline">
                                    {budget.status_label}
                                </Badge>
                            </div>

                            {activeTab === 'business_plan' ? (
                                <div
                                    id="budget-section-business-plan"
                                    className={cn(
                                        'scroll-mt-24 rounded-md transition-[background-color,box-shadow,border-color] duration-300',
                                        highlightedSection ===
                                            'budget-section-business-plan' &&
                                            'bg-amber-50/70 ring-2 ring-amber-400/70',
                                    )}
                                    style={budgetTargetHighlightStyle(
                                        highlightedSection ===
                                            'budget-section-business-plan',
                                    )}
                                >
                                    <BusinessPlanEditor
                                        label={budget.label}
                                        sections={
                                            form.data.business_plan_sections
                                        }
                                        sourceDrafts={
                                            budget.business_plan_source_drafts
                                        }
                                        onSectionsChange={(sections) =>
                                            form.setData(
                                                'business_plan_sections',
                                                sections,
                                            )
                                        }
                                    />
                                </div>
                            ) : (
                                <>
                                    <div
                                        id="budget-section-settings"
                                        className={cn(
                                            'grid scroll-mt-24 gap-3 rounded-md transition-[background-color,box-shadow,border-color] duration-300 md:grid-cols-3',
                                            highlightedSection ===
                                                'budget-section-settings' &&
                                                'bg-amber-50/70 ring-2 ring-amber-400/70',
                                        )}
                                        style={budgetTargetHighlightStyle(
                                            highlightedSection ===
                                                'budget-section-settings',
                                        )}
                                    >
                                        <label className="grid gap-1 text-sm">
                                            <span className="font-medium">
                                                Budget horizon
                                            </span>
                                            <select
                                                value={form.data.horizon_months}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'horizon_months',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-10 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value={12}>
                                                    12 months
                                                </option>
                                                <option value={24}>
                                                    24 months
                                                </option>
                                                <option value={36}>
                                                    36 months
                                                </option>
                                            </select>
                                        </label>
                                        <label className="grid gap-1 text-sm">
                                            <span className="font-medium">
                                                Required runway months
                                            </span>
                                            <input
                                                type="number"
                                                min={0}
                                                max={60}
                                                value={
                                                    form.data
                                                        .expected_runway_months
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'expected_runway_months',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-10 rounded-md border bg-background px-3 text-sm"
                                            />
                                        </label>
                                        <div className="rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
                                            Client and advisor see the same
                                            budget numbers. Advisor goals are
                                            visible but locked to the client.
                                        </div>
                                    </div>

                                    <AssumptionsEditor
                                        highlighted={
                                            highlightedSection ===
                                            'budget-section-assumptions'
                                        }
                                        assumptions={form.data.assumptions}
                                        onChange={(key, value) =>
                                            form.setData('assumptions', {
                                                ...form.data.assumptions,
                                                [key]: value,
                                            })
                                        }
                                    />

                                    <BudgetRowsEditor
                                        highlighted={
                                            highlightedSection ===
                                            'budget-section-implementation_costs'
                                        }
                                        title="Implementation costs"
                                        helper="One-off costs needed to deliver the plan, complete the acquisition, or start the advisory pathway."
                                        group="implementation_costs"
                                        rows={form.data.implementation_costs}
                                        onRowsChange={(rows) =>
                                            form.setData(
                                                'implementation_costs',
                                                rows,
                                            )
                                        }
                                    />
                                    <BudgetRowsEditor
                                        highlighted={
                                            highlightedSection ===
                                            'budget-section-monthly_fixed_costs'
                                        }
                                        title="Monthly operating costs"
                                        helper="Recurring costs that affect affordability and payment terms."
                                        group="monthly_fixed_costs"
                                        rows={form.data.monthly_fixed_costs}
                                        onRowsChange={(rows) =>
                                            form.setData(
                                                'monthly_fixed_costs',
                                                rows,
                                            )
                                        }
                                    />
                                    <BudgetRowsEditor
                                        highlighted={
                                            highlightedSection ===
                                            'budget-section-revenue_forecast'
                                        }
                                        title="Revenue forecast"
                                        helper="Expected revenue lines over the budget horizon."
                                        group="revenue_forecast"
                                        rows={form.data.revenue_forecast}
                                        onRowsChange={(rows) =>
                                            form.setData(
                                                'revenue_forecast',
                                                rows,
                                            )
                                        }
                                        revenue
                                    />
                                    <BudgetRowsEditor
                                        highlighted={
                                            highlightedSection ===
                                            'budget-section-funding_sources'
                                        }
                                        title="Funding sources"
                                        helper="Cash, funding, finance, grants, deposits, or other sources available to fund the plan."
                                        group="funding_sources"
                                        rows={form.data.funding_sources}
                                        onRowsChange={(rows) =>
                                            form.setData(
                                                'funding_sources',
                                                rows,
                                            )
                                        }
                                    />
                                </>
                            )}

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={save}
                                >
                                    <Save
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {form.processing
                                        ? 'Saving'
                                        : activeTab === 'business_plan'
                                          ? 'Save plan'
                                          : 'Save budget'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={submit}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Submit for advisor review
                                </Button>
                            </div>
                        </section>

                        <aside className="space-y-4">
                            <SummaryPanel budget={budget} />
                            <GoalsPanel
                                clientGoals={budget.client_goals}
                                advisorGoals={budget.advisor_goals}
                            />
                            <FlagsPanel
                                flags={budget.flags}
                                onboardingUrl={onboardingUrl}
                                onSelectSection={focusBudgetSection}
                            />
                        </aside>
                    </div>
                )}
            </main>
        </>
    );
}

function LockedFinancialsPanel({
    file,
    uploadKey,
    uploading,
    uploadError,
    onboardingUrl,
    onFileChange,
    onUpload,
}: {
    file: File | null;
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    onboardingUrl: string;
    onFileChange: (file: File | null) => void;
    onUpload: () => void;
}) {
    return (
        <section className="grid gap-4 rounded-md border bg-background p-4 lg:grid-cols-[1fr_420px]">
            <div className="space-y-3">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    Budget locked until financials are uploaded
                </div>
                <p className="text-sm text-muted-foreground">
                    Upload a P&L or management accounts file. The system will
                    unlock a preliminary budget shell and request extra files if
                    the financial base is incomplete.
                </p>
                <Button asChild variant="outline">
                    <Link href={onboardingUrl}>
                        <FileText className="size-4" aria-hidden="true" />
                        Open onboarding documents
                    </Link>
                </Button>
            </div>
            <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                <FileDropzone
                    key={uploadKey}
                    id="strategic_budget_financial_upload"
                    files={file ? [file] : []}
                    label="Upload P&L or management accounts"
                    onFilesChange={(files) => onFileChange(files[0] ?? null)}
                />
                <InputError message={uploadError ?? undefined} />
                <Button
                    type="button"
                    disabled={!file || uploading}
                    onClick={onUpload}
                >
                    <Upload className="size-4" aria-hidden="true" />
                    {uploading ? 'Uploading' : 'Upload financials'}
                </Button>
            </div>
        </section>
    );
}

function WorkspaceTabs({
    activeTab,
    label,
    onChange,
}: {
    activeTab: WorkspaceTab;
    label: string;
    onChange: (tab: WorkspaceTab) => void;
}) {
    const planLabel =
        label === 'Operating Plan & Budget'
            ? 'Operating Plan'
            : 'Business Plan';

    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label={label}
        >
            <WorkspaceTabButton
                active={activeTab === 'business_plan'}
                onClick={() => onChange('business_plan')}
            >
                <BookOpen className="size-4" aria-hidden="true" />
                {planLabel}
            </WorkspaceTabButton>
            <WorkspaceTabButton
                active={activeTab === 'budget'}
                onClick={() => onChange('budget')}
            >
                <FileSpreadsheet className="size-4" aria-hidden="true" />
                Budget
            </WorkspaceTabButton>
        </div>
    );
}

function WorkspaceTabButton({
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
                'flex flex-1 items-center justify-center gap-2 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function BusinessPlanEditor({
    label,
    sections,
    sourceDrafts,
    onSectionsChange,
}: {
    label: string;
    sections: BusinessPlanSection[];
    sourceDrafts: BusinessPlanSourceDraft[];
    onSectionsChange: (sections: BusinessPlanSection[]) => void;
}) {
    const sourceByKey = new Map(
        sourceDrafts.map((draft) => [draft.key, draft]),
    );
    const planLabel =
        label === 'Operating Plan & Budget'
            ? 'operating plan'
            : 'business plan';

    const updateSection = (index: number, answer: string) => {
        onSectionsChange(
            sections.map((section, current) =>
                current === index ? { ...section, answer } : section,
            ),
        );
    };

    return (
        <section className="space-y-4">
            {sections.map((section, index) => {
                const sourceDraft = sourceByKey.get(section.key);

                return (
                    <article
                        key={section.key}
                        className="grid gap-3 rounded-md border bg-muted/20 p-3 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
                    >
                        <div className="space-y-2 rounded-md border bg-background p-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">
                                    Source draft
                                </h3>
                                {sourceDraft?.source_url ? (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-xs"
                                            >
                                                <Link
                                                    href={
                                                        sourceDraft.source_url
                                                    }
                                                >
                                                    {sourceDraft.source_label}
                                                    <ExternalLink
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            side="bottom"
                                            className="max-w-xs"
                                        >
                                            {sourceDraft.source_help}
                                        </TooltipContent>
                                    </Tooltip>
                                ) : (
                                    <Badge variant="outline">
                                        {sourceDraft?.source_label ??
                                            'Source draft'}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Use this as a starting point. It stays linked to
                                the original source context.
                            </p>
                            <div className="min-h-28 rounded-md bg-muted/30 p-3 text-sm whitespace-pre-wrap text-muted-foreground">
                                {sourceDraft?.body ||
                                    'No source draft available yet.'}
                            </div>
                        </div>

                        <label className="grid gap-2">
                            <span className="text-sm font-medium">
                                {section.title}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {section.prompt}
                            </span>
                            <textarea
                                value={section.answer}
                                onChange={(event) =>
                                    updateSection(index, event.target.value)
                                }
                                rows={8}
                                placeholder={`Write the final ${planLabel} section here.`}
                                className="min-h-44 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </label>
                    </article>
                );
            })}
        </section>
    );
}

function MetricPanel({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: ReactNode;
    detail: ReactNode;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" aria-hidden={true} />
                {label}
            </div>
            <div className="mt-2 text-lg font-semibold">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{detail}</div>
        </section>
    );
}

function ProgressBand({ score, message }: { score: number; message: string }) {
    return (
        <section className="space-y-2 rounded-md border bg-muted/20 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="font-medium">Confidence meter</span>
                <span className="text-muted-foreground">{score}/100</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full',
                        score >= 80
                            ? 'bg-emerald-600'
                            : score >= 55
                              ? 'bg-amber-500'
                              : 'bg-red-500',
                    )}
                    style={{ width: `${Math.max(4, Math.min(100, score))}%` }}
                />
            </div>
            <p className="text-sm text-muted-foreground">{message}</p>
        </section>
    );
}

function AssumptionsEditor({
    highlighted,
    assumptions,
    onChange,
}: {
    highlighted: boolean;
    assumptions: BudgetForm['assumptions'];
    onChange: (key: keyof BudgetForm['assumptions'], value: string) => void;
}) {
    const fields: Array<{
        key: keyof BudgetForm['assumptions'];
        label: string;
        help: string;
    }> = [
        {
            key: 'revenue_growth_percent',
            label: 'Revenue growth %',
            help: 'Expected sales growth applied across the forecast. It helps test whether the plan can fund itself over the selected budget horizon.',
        },
        {
            key: 'cost_inflation_percent',
            label: 'Cost inflation %',
            help: 'Expected increase in operating costs. It affects affordability, runway, and whether payment terms remain realistic.',
        },
        {
            key: 'target_gross_profit_percent',
            label: 'Target GP %',
            help: 'Target gross profit margin after direct costs. It helps assess whether revenue lines are strong enough to support delivery.',
        },
        {
            key: 'target_net_profit_before_tax_percent',
            label: 'Target NPBT %',
            help: 'Target net profit before tax. It shows whether the proposed work leaves enough operating profit before tax obligations.',
        },
        {
            key: 'target_net_profit_after_tax_percent',
            label: 'Target NPAT %',
            help: 'Target net profit after tax. It is used as a post-tax affordability and sustainability check.',
        },
    ];

    return (
        <section
            id="budget-section-assumptions"
            className={cn(
                'scroll-mt-24 space-y-3 rounded-md border bg-muted/20 p-3 transition-[background-color,box-shadow,border-color] duration-300',
                highlighted &&
                    'border-amber-400 bg-amber-50/70 ring-2 ring-amber-400/70',
            )}
            style={budgetTargetHighlightStyle(highlighted)}
        >
            <h2 className="text-sm font-medium">Financial assumptions</h2>
            <div className="grid gap-3 md:grid-cols-5">
                {fields.map((field) => (
                    <label key={field.key} className="grid gap-1 text-sm">
                        <span className="flex items-center gap-1.5">
                            {field.label}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        className="inline-flex size-4 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        aria-label={`${field.label} explanation`}
                                    >
                                        <Info
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="top" className="max-w-xs">
                                    {field.help}
                                </TooltipContent>
                            </Tooltip>
                        </span>
                        <input
                            type="number"
                            min={0}
                            value={assumptions[field.key]}
                            onChange={(event) =>
                                onChange(field.key, event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                        />
                    </label>
                ))}
            </div>
        </section>
    );
}

function BudgetRowsEditor({
    highlighted,
    title,
    helper,
    group,
    rows,
    onRowsChange,
    revenue = false,
}: {
    highlighted: boolean;
    title: string;
    helper: string;
    group: BudgetGroupKey;
    rows: BudgetRow[];
    onRowsChange: (rows: BudgetRow[]) => void;
    revenue?: boolean;
}) {
    const update = (index: number, patch: Partial<BudgetRow>) => {
        onRowsChange(
            rows.map((row, current) =>
                current === index ? { ...row, ...patch } : row,
            ),
        );
    };

    return (
        <section
            id={`budget-section-${group}`}
            className={cn(
                'scroll-mt-24 space-y-3 rounded-md border bg-muted/20 p-3 transition-[background-color,box-shadow,border-color] duration-300',
                highlighted &&
                    'border-amber-400 bg-amber-50/70 ring-2 ring-amber-400/70',
            )}
            style={budgetTargetHighlightStyle(highlighted)}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-medium">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {helper}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => onRowsChange([...rows, blankRow(revenue)])}
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div
                        key={`${group}-${index}`}
                        className={cn(
                            'grid gap-2',
                            revenue
                                ? 'lg:grid-cols-[minmax(0,1fr)_repeat(6,minmax(72px,0.45fr))_120px]'
                                : 'lg:grid-cols-[minmax(0,1fr)_repeat(2,minmax(80px,0.35fr))_120px]',
                        )}
                    >
                        <BudgetInput
                            label="Item"
                            value={row.label ?? ''}
                            onChange={(value) =>
                                update(index, { label: value })
                            }
                        />
                        <BudgetInput
                            label="Amount"
                            type="number"
                            value={row.amount ?? ''}
                            onChange={(value) =>
                                update(index, { amount: value })
                            }
                        />
                        <BudgetInput
                            label="Qty"
                            type="number"
                            value={row.quantity ?? 1}
                            onChange={(value) =>
                                update(index, { quantity: value })
                            }
                        />
                        {revenue ? (
                            <>
                                <BudgetInput
                                    label="Start"
                                    type="number"
                                    value={row.month ?? 1}
                                    onChange={(value) =>
                                        update(index, { month: value })
                                    }
                                />
                                <BudgetInput
                                    label="Growth %"
                                    type="number"
                                    value={row.monthly_growth_percent ?? 0}
                                    onChange={(value) =>
                                        update(index, {
                                            monthly_growth_percent: value,
                                        })
                                    }
                                />
                                <BudgetInput
                                    label="GP %"
                                    type="number"
                                    value={row.gross_profit_percent ?? ''}
                                    onChange={(value) =>
                                        update(index, {
                                            gross_profit_percent: value,
                                        })
                                    }
                                />
                                <BudgetInput
                                    label="Unit cost"
                                    type="number"
                                    value={row.unit_cost ?? ''}
                                    onChange={(value) =>
                                        update(index, { unit_cost: value })
                                    }
                                />
                            </>
                        ) : null}
                        <label className="grid gap-1 text-xs">
                            <span>Confidence</span>
                            <select
                                value={row.confidence ?? 'estimate'}
                                onChange={(event) =>
                                    update(index, {
                                        confidence: event.target.value as
                                            | 'known'
                                            | 'estimate'
                                            | 'guess',
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="known">Known</option>
                                <option value="estimate">Estimate</option>
                                <option value="guess">Guess</option>
                            </select>
                        </label>
                    </div>
                ))}
            </div>
        </section>
    );
}

function BudgetInput({
    label,
    value,
    type = 'text',
    onChange,
}: {
    label: string;
    value: string | number;
    type?: 'text' | 'number';
    onChange: (value: string) => void;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span>{label}</span>
            <input
                type={type}
                value={value}
                min={type === 'number' ? 0 : undefined}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 rounded-md border bg-background px-2 text-sm"
            />
        </label>
    );
}

function SummaryPanel({ budget }: { budget: BudgetPayload }) {
    const computed = budget.computed ?? {};

    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <h2 className="text-sm font-medium">Budget summary</h2>
            <div className="grid gap-2">
                <SummaryMetric
                    label="Implementation costs"
                    value={formatCurrency(computed.total_launch_costs ?? 0)}
                />
                <SummaryMetric
                    label="Monthly costs"
                    value={formatCurrency(computed.monthly_fixed_costs ?? 0)}
                />
                <SummaryMetric
                    label="Funding"
                    value={formatCurrency(computed.total_funding ?? 0)}
                />
                <SummaryMetric
                    label="Available after setup"
                    value={formatCurrency(computed.available_after_launch ?? 0)}
                />
                <SummaryMetric
                    label="Break-even"
                    value={formatYear(computed.break_even_year)}
                />
                <SummaryMetric
                    label="Cash positive"
                    value={formatYear(computed.cash_flow_positive_year)}
                />
            </div>
        </section>
    );
}

function GoalsPanel({
    clientGoals,
    advisorGoals,
}: {
    clientGoals: GoalPayload[];
    advisorGoals: GoalPayload[];
}) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <h2 className="text-sm font-medium">Goals</h2>
            <GoalList title="Client goals" goals={clientGoals} />
            <GoalList title="Advisor goals" goals={advisorGoals} locked />
        </section>
    );
}

function GoalList({
    title,
    goals,
    locked = false,
}: {
    title: string;
    goals: GoalPayload[];
    locked?: boolean;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground uppercase">
                {title}
                {locked ? <LockKeyhole className="size-3" /> : null}
            </div>
            {goals.length === 0 ? (
                <p className="text-sm text-muted-foreground">No goals yet.</p>
            ) : (
                goals.map((goal, index) => (
                    <div
                        key={`${title}-${index}`}
                        className="rounded-md border p-3"
                    >
                        <div className="text-sm font-medium">{goal.title}</div>
                        {goal.measure ? (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {goal.measure}
                            </p>
                        ) : null}
                    </div>
                ))
            )}
        </div>
    );
}

function FlagsPanel({
    flags,
    onboardingUrl,
    onSelectSection,
}: {
    flags: BudgetPayload['flags'];
    onboardingUrl: string;
    onSelectSection: (sectionId: string, tab?: WorkspaceTab) => void;
}) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <h2 className="text-sm font-medium">Next steps</h2>
            {flags.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No budget warnings at the moment.
                </p>
            ) : (
                flags.map((flag) => {
                    const action = nextStepAction(flag.key, onboardingUrl);
                    const content = (
                        <>
                            <AlertTriangle
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    flag.severity === 'high'
                                        ? 'text-red-600'
                                        : 'text-amber-600',
                                )}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block font-medium">
                                    {flag.title}
                                </span>
                                <span className="mt-1 block text-muted-foreground">
                                    {flag.message}
                                </span>
                                <span className="mt-2 block text-xs font-medium text-foreground">
                                    {action.label}
                                </span>
                            </span>
                        </>
                    );

                    if ('href' in action) {
                        return (
                            <Link
                                key={flag.key}
                                href={action.href}
                                className="flex w-full gap-2 rounded-md border bg-muted/20 p-3 text-left text-sm transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                {content}
                            </Link>
                        );
                    }

                    return (
                        <button
                            key={flag.key}
                            type="button"
                            className="flex w-full gap-2 rounded-md border bg-muted/20 p-3 text-left text-sm transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            onClick={() =>
                                onSelectSection(action.sectionId, action.tab)
                            }
                        >
                            {content}
                        </button>
                    );
                })
            )}
        </section>
    );
}

function nextStepAction(
    key: string,
    onboardingUrl: string,
):
    | {
          label: string;
          sectionId: string;
          tab?: WorkspaceTab;
          href?: never;
      }
    | { label: string; href: string; sectionId?: never } {
    if (['financial_upload_required', 'partial_financials'].includes(key)) {
        return {
            label: 'Open financial uploads',
            href: onboardingUrl,
        };
    }

    if (key === 'implementation_costs_missing') {
        return {
            label: 'Complete implementation costs',
            sectionId: 'budget-section-implementation_costs',
            tab: 'budget',
        };
    }

    if (key === 'revenue_forecast_missing' || key === 'no_break_even') {
        return {
            label: 'Complete revenue forecast',
            sectionId: 'budget-section-revenue_forecast',
            tab: 'budget',
        };
    }

    if (key === 'missing_assumptions' || key === 'tax_not_configured') {
        return {
            label: 'Complete financial assumptions',
            sectionId: 'budget-section-assumptions',
            tab: 'budget',
        };
    }

    if (key === 'funding_sources_missing') {
        return {
            label: 'Complete funding sources',
            sectionId: 'budget-section-funding_sources',
            tab: 'budget',
        };
    }

    if (key === 'monthly_fixed_costs_missing') {
        return {
            label: 'Complete monthly operating costs',
            sectionId: 'budget-section-monthly_fixed_costs',
            tab: 'budget',
        };
    }

    if (key === 'business_plan_incomplete') {
        return {
            label: 'Complete plan sections',
            sectionId: 'budget-section-business-plan',
            tab: 'business_plan',
        };
    }

    return {
        label: 'Review budget workspace',
        sectionId: 'budget-section-settings',
        tab: 'budget',
    };
}

function budgetTargetHighlightStyle(
    highlighted: boolean,
): CSSProperties | undefined {
    if (!highlighted) {
        return undefined;
    }

    return {
        backgroundColor: 'rgba(254, 243, 199, 0.82)',
        borderColor: 'rgba(217, 119, 6, 0.9)',
        boxShadow:
            '0 0 0 3px rgba(245, 158, 11, 0.38), 0 12px 28px rgba(146, 64, 14, 0.12)',
    };
}

function SummaryMetric({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function blankRow(revenue = false): BudgetRow {
    return revenue
        ? {
              label: '',
              amount: '',
              quantity: 1,
              month: 1,
              monthly_growth_percent: 0,
              gross_profit_percent: '',
              confidence: 'estimate',
          }
        : {
              label: '',
              amount: '',
              quantity: 1,
              confidence: 'estimate',
          };
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : '-';
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
