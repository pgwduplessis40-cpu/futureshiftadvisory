import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Bot,
    Eye,
    FileText,
    Plus,
    Trash2,
    Trophy,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import { BudgetCashChart } from '@/components/budget-cash-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatNzdCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { formatLabel } from './plan-dashboard-panels';
import type {
    BudgetAssumptions,
    BudgetFormState,
    BudgetGroupKey,
    BudgetPayload,
    BudgetRow,
    BudgetSetupMode,
    BusinessPlanPayload,
    FundingScenarioRow,
    FutureCostRow,
    GamificationPayload,
    IdeaValidationForm,
    IdeaValidationPayload,
    PlanRequirementPayload,
    PlanSectionPayload,
} from './plan-types';
import type { AutosaveState } from './plan-workspace-draft';
export type BudgetTemplateKey =
    | 'service'
    | 'consulting'
    | 'retail'
    | 'food'
    | 'online'
    | 'trades'
    | 'subscription';

export type BudgetTemplate = {
    title: string;
    description: string;
    expected_runway_months: number;
    launch_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
};

export const BUDGET_UNLOCK_REQUIREMENT_KEY = 'business-type-location';
export const BUDGET_ASSUMPTIONS_REQUIREMENT_KEY = 'financial-assumptions';

export const budgetTemplates: Record<BudgetTemplateKey, BudgetTemplate> = {
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

export function BudgetEditor({
    budget,
    form,
    plan,
    ideaValidation,
    gamification,
    saving,
    autosaveState,
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
    autosaveState: 'idle' | 'saving' | 'saved' | 'error';
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
                        milestone once the revenue model and funding
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
                            title="What one-off costs need funding?"
                            helper="Include setup, replacement, or growth items such as equipment, website, deposits, signage, licences, stock, or marketing."
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
                            fixedCost
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
                            title="What funding or cash is available?"
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
                    <div className="grid gap-4 sm:grid-cols-2">
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
                        <div className="min-w-0 sm:col-span-2">
                            <BudgetAssumptionsEditor
                                assumptions={form.assumptions}
                                onFormChange={onFormChange}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4">
                        <BudgetRowsEditor
                            title="Planned one-off costs"
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
                            fixedCost
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
                            label="Planned one-off costs"
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

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            size="sm"
                            onClick={onSave}
                            disabled={saving}
                        >
                            <Upload className="size-4" aria-hidden="true" />
                            {saving ? 'Saving' : 'Save budget'}
                        </Button>
                        {autosaveState !== 'idle' ? (
                            <span className="text-xs text-muted-foreground">
                                {sectionAutosaveStateLabel(autosaveState)}
                            </span>
                        ) : null}
                    </div>
                </>
            ) : null}
        </div>
    );
}

export function BudgetRowsEditor({
    title,
    helper,
    group,
    rows,
    onFormChange,
    quickAdds = [],
    revenue = false,
    timed = false,
    fixedCost = false,
}: {
    title: string;
    helper?: string;
    group: BudgetGroupKey;
    rows: BudgetRow[];
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
    quickAdds?: BudgetRow[];
    revenue?: boolean;
    timed?: boolean;
    fixedCost?: boolean;
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
                                blankBudgetRow(revenue, timed, fixedCost),
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
                                    className="max-w-full text-left whitespace-normal"
                                    title={budgetRowDescription(row)}
                                    onClick={() =>
                                        onFormChange((current) => ({
                                            ...current,
                                            [group]: mergeBudgetRows(
                                                current[group],
                                                [row],
                                                revenue,
                                                timed,
                                                fixedCost,
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
                            'grid gap-3 md:grid-cols-[minmax(13rem,1.35fr)_minmax(7rem,0.75fr)_minmax(5rem,0.55fr)_minmax(8rem,0.8fr)_auto]',
                            timed &&
                                !revenue &&
                                'md:grid-cols-[minmax(13rem,1.35fr)_minmax(7rem,0.75fr)_minmax(5rem,0.55fr)_minmax(5rem,0.55fr)_minmax(8rem,0.8fr)_auto]',
                            revenue &&
                                'xl:grid-cols-[minmax(12rem,1.2fr)_repeat(8,minmax(5rem,0.55fr))_minmax(8rem,0.8fr)_auto]',
                            fixedCost &&
                                'xl:grid-cols-[minmax(12rem,1.2fr)_repeat(4,minmax(6rem,0.6fr))_minmax(8rem,0.8fr)_auto]',
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
                        {fixedCost ? (
                            <>
                                <label className="grid gap-1 text-xs">
                                    <span className="text-muted-foreground">
                                        Billing cadence
                                    </span>
                                    <select
                                        value={row.cadence ?? 'monthly'}
                                        onChange={(event) =>
                                            updateBudgetRow(
                                                onFormChange,
                                                group,
                                                index,
                                                {
                                                    cadence: event.target
                                                        .value as BudgetRow['cadence'],
                                                    cadence_confirmed: true,
                                                },
                                            )
                                        }
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="weekly">Weekly</option>
                                        <option value="fortnightly">
                                            Fortnightly
                                        </option>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">
                                            Quarterly
                                        </option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </label>
                                <BudgetEvidenceInputs
                                    row={row}
                                    onChange={(changes) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            changes,
                                        )
                                    }
                                />
                            </>
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
                                    value={
                                        row.growth_percent ??
                                        row.monthly_growth_percent ??
                                        0
                                    }
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { growth_percent: value },
                                        )
                                    }
                                />
                                <label className="grid gap-1 text-xs">
                                    <span className="text-muted-foreground">
                                        Growth cadence
                                    </span>
                                    <select
                                        value={row.growth_cadence ?? 'monthly'}
                                        onChange={(event) =>
                                            updateBudgetRow(
                                                onFormChange,
                                                group,
                                                index,
                                                {
                                                    growth_cadence: event.target
                                                        .value as BudgetRow['growth_cadence'],
                                                    growth_cadence_confirmed: true,
                                                },
                                            )
                                        }
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </label>
                                <BudgetInput
                                    label="Max units / month"
                                    type="number"
                                    min={0}
                                    value={row.monthly_capacity_units ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                monthly_capacity_units: value,
                                                capacity_confirmed:
                                                    value !== '',
                                            },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Founder units / month"
                                    type="number"
                                    min={0}
                                    value={row.founder_capacity_units ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { founder_capacity_units: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Unit label"
                                    value={row.unit_label ?? 'units'}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { unit_label: value },
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
                                    label="Contractor cost / unit"
                                    type="number"
                                    min={0}
                                    value={row.contractor_unit_cost ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                contractor_unit_cost: value,
                                                contractor_cost_confirmed:
                                                    value !== '',
                                            },
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
                                <BudgetEvidenceInputs
                                    row={row}
                                    revenue
                                    onChange={(changes) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            changes,
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

export function BudgetEvidenceInputs({
    row,
    revenue = false,
    onChange,
}: {
    row: BudgetRow;
    revenue?: boolean;
    onChange: (changes: Partial<BudgetRow>) => void;
}) {
    return (
        <>
            <label className="grid gap-1 text-xs">
                <span className="text-muted-foreground">Evidence source</span>
                <select
                    value={row.source_type ?? 'unverified'}
                    onChange={(event) =>
                        onChange({
                            source_type: event.target
                                .value as BudgetRow['source_type'],
                            source_confirmed: false,
                        })
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="unverified">Select source</option>
                    <option value="bank_statement">Bank statement</option>
                    <option value="xero_ledger">Xero ledger</option>
                    <option value="supplier_quote">Supplier quote</option>
                    <option value="signed_contract">Signed contract</option>
                    <option value="pipeline_evidence">
                        {revenue ? 'Pipeline evidence' : 'Pricing evidence'}
                    </option>
                    <option value="owner_record">Owner record</option>
                </select>
            </label>
            <BudgetInput
                label="Evidence reference"
                value={row.source_reference ?? ''}
                onChange={(value) =>
                    onChange({
                        source_reference: value,
                        source_confirmed: false,
                    })
                }
            />
            <label className="flex items-end gap-2 pb-2 text-xs text-muted-foreground">
                <input
                    type="checkbox"
                    name="source_confirmed"
                    aria-label={`Confirm ${revenue ? 'revenue' : 'cost'} evidence source`}
                    checked={Boolean(row.source_confirmed)}
                    onChange={(event) =>
                        onChange({ source_confirmed: event.target.checked })
                    }
                />
                Source checked
            </label>
        </>
    );
}

export function BudgetAssumptionsEditor({
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
            label: 'Annual revenue growth %',
            helper: 'Applied from year two onward; use Monthly growth % on a revenue row for the first-year ramp.',
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
        <div className="space-y-3">
            <label className="grid max-w-md gap-1 text-xs">
                <span className="text-muted-foreground">
                    Forecast start month
                </span>
                <input
                    type="month"
                    value={assumptions.forecast_start_month ?? ''}
                    onChange={(event) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                forecast_start_month: event.target.value,
                                forecast_start_confirmed: false,
                            },
                        }))
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                />
                <span className="text-[11px] leading-snug text-muted-foreground">
                    This anchors Month 1 to the written milestones and cash
                    forecast.
                </span>
                <span className="flex items-center gap-2 text-[11px] leading-snug text-muted-foreground">
                    <input
                        type="checkbox"
                        name="forecast_start_confirmed"
                        aria-label="Confirm forecast start month"
                        checked={Boolean(assumptions.forecast_start_confirmed)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    forecast_start_confirmed:
                                        event.target.checked,
                                },
                            }))
                        }
                    />
                    I have checked Month 1 against the written milestones.
                </span>
            </label>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-3">
                {(
                    [
                        ['opening_cash_balance', 'Opening cash', 0],
                        ['debtor_days', 'Debtor days', 0],
                        ['creditor_days', 'Creditor days', 0],
                    ] as const
                ).map(([key, label, min]) => (
                    <label key={key} className="grid min-w-0 gap-1 text-xs">
                        <span className="text-muted-foreground">{label}</span>
                        <input
                            type="number"
                            min={min}
                            value={assumptions[key]}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        [key]: event.target.value,
                                    },
                                }))
                            }
                            className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
                        />
                    </label>
                ))}
            </div>
            <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="opening_cash_verified"
                        aria-label="Confirm opening cash against current bank records"
                        checked={Boolean(assumptions.opening_cash_verified)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    opening_cash_verified: event.target.checked,
                                },
                            }))
                        }
                    />
                    Opening cash checked against current bank records
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="working_capital_verified"
                        aria-label="Confirm debtor and creditor days against actual terms"
                        checked={Boolean(assumptions.working_capital_verified)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    working_capital_verified:
                                        event.target.checked,
                                },
                            }))
                        }
                    />
                    Debtor and creditor days checked against actual terms
                </label>
            </div>
            <label className="grid max-w-md gap-1 text-xs">
                <span className="text-muted-foreground">
                    Revenue after Year 1
                </span>
                <select
                    value={assumptions.year_two_revenue_basis}
                    onChange={(event) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                year_two_revenue_basis:
                                    event.target.value === 'year_one_average'
                                        ? 'year_one_average'
                                        : 'exit_run_rate',
                            },
                        }))
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="exit_run_rate">
                        Carry forward the Year 1 exit run-rate
                    </option>
                    <option value="year_one_average">
                        Use the Year 1 average monthly revenue
                    </option>
                </select>
                <span className="text-[11px] leading-snug text-muted-foreground">
                    Carry-forward avoids an artificial Month 12 to Month 13
                    drop. Use the average only for a deliberate seasonal or
                    averaged forecast.
                </span>
            </label>
            <div className="grid gap-3 rounded-md border bg-muted/20 p-3 text-xs md:grid-cols-[minmax(14rem,0.8fr)_minmax(16rem,1.2fr)]">
                <label className="grid gap-1">
                    <span className="text-muted-foreground">
                        Funding position
                    </span>
                    <select
                        value={assumptions.funding_position ?? 'undecided'}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    funding_position:
                                        event.target.value === 'self_funded'
                                            ? 'self_funded'
                                            : event.target.value ===
                                                'external_funding'
                                              ? 'external_funding'
                                              : 'undecided',
                                    funding_position_confirmed: false,
                                },
                            }))
                        }
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                    >
                        <option value="undecided">Confirm the position</option>
                        <option value="self_funded">Self-funded</option>
                        <option value="external_funding">
                            External funding request
                        </option>
                    </select>
                    <span className="flex items-center gap-2 leading-snug text-muted-foreground">
                        <input
                            type="checkbox"
                            name="funding_position_confirmed"
                            aria-label="Confirm the funding position matches the written plan"
                            checked={Boolean(
                                assumptions.funding_position_confirmed,
                            )}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        funding_position_confirmed:
                                            event.target.checked,
                                    },
                                }))
                            }
                        />
                        I confirm this matches the written plan.
                    </span>
                </label>
                <BudgetInput
                    label="Funding purpose (required for an external request)"
                    value={assumptions.funding_request_purpose ?? ''}
                    onChange={(value) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                funding_request_purpose: value,
                            },
                        }))
                    }
                />
            </div>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-3">
                {fields.map((field) => (
                    <label
                        key={field.key}
                        className="grid min-w-0 gap-1 text-xs"
                    >
                        <span className="text-muted-foreground">
                            {field.label}
                        </span>
                        <input
                            type="number"
                            min={
                                field.key === 'revenue_growth_percent' ||
                                field.key === 'cost_inflation_percent'
                                    ? -100
                                    : 0
                            }
                            max={
                                field.key === 'cost_inflation_percent'
                                    ? 100
                                    : 500
                            }
                            value={String(assumptions[field.key] ?? '')}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        [field.key]: event.target.value,
                                    },
                                }))
                            }
                            className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
                        />
                        <span className="text-[11px] leading-snug text-muted-foreground">
                            {field.helper}
                        </span>
                    </label>
                ))}
            </div>
        </div>
    );
}

export function FutureCostsEditor({
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
                        className="grid gap-3 xl:grid-cols-[minmax(12rem,1.2fr)_repeat(5,minmax(5rem,0.55fr))_minmax(8rem,0.8fr)_auto]"
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
                        <label className="grid gap-1 text-xs">
                            <span className="text-muted-foreground">
                                Treatment
                            </span>
                            <select
                                value={row.classification ?? 'operating'}
                                onChange={(event) =>
                                    updateFutureCostRow(onFormChange, index, {
                                        classification:
                                            event.target.value === 'capital'
                                                ? 'capital'
                                                : 'operating',
                                        recurring:
                                            event.target.value === 'capital'
                                                ? false
                                                : row.recurring,
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="operating">
                                    Operating cost
                                </option>
                                <option value="capital">Capital asset</option>
                            </select>
                        </label>
                        <BudgetInput
                            label="Useful life years"
                            type="number"
                            min={1}
                            value={row.useful_life_years ?? 3}
                            onChange={(value) =>
                                updateFutureCostRow(onFormChange, index, {
                                    useful_life_years: value,
                                })
                            }
                        />
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

export function FundingScenariosEditor({
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
                        className="grid gap-3 md:grid-cols-[minmax(13rem,1.35fr)_minmax(8rem,0.85fr)_minmax(7rem,0.75fr)_repeat(3,minmax(5rem,0.55fr))_minmax(8rem,0.8fr)_auto]"
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

export function BudgetSourceDetail({
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

export function BudgetInput({
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

export function BudgetConfidenceSelect({
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

export function BudgetMetric({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-md border bg-background p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

export function AdvisorBudgetPreview({
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

export function AdvisorPreviewItem({
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

export function budgetRow(
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

export function budgetRowDescription(row: BudgetRow): string {
    const label = budgetRowLabel(row);

    return (
        row.description ??
        budgetRowDescriptions[label.toLowerCase()] ??
        'Add this suggested line to your budget, then adjust the amount and confidence level if needed.'
    );
}

export function inferBudgetTemplateKey(
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

export function budgetRowsFromPlan(
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

export function budgetAssumptionsFromPlan(
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

export function mergeBudgetAssumptions(
    current: BudgetAssumptions,
    suggested: Partial<BudgetAssumptions>,
): BudgetAssumptions {
    return {
        opening_cash_balance: current.opening_cash_balance,
        opening_cash_verified: current.opening_cash_verified,
        debtor_days: current.debtor_days,
        creditor_days: current.creditor_days,
        working_capital_verified: current.working_capital_verified,
        revenue_growth_percent:
            current.revenue_growth_percent ||
            suggested.revenue_growth_percent ||
            '',
        forecast_start_month: current.forecast_start_month,
        forecast_start_confirmed: current.forecast_start_confirmed,
        funding_position: current.funding_position,
        funding_position_confirmed: current.funding_position_confirmed,
        funding_request_purpose: current.funding_request_purpose,
        year_two_revenue_basis: current.year_two_revenue_basis,
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

export function percentNear(text: string, labels: string[]): number | '' {
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

export function budgetSourceText(
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

export function hasAny(text: string, needles: string[]): boolean {
    return needles.some((needle) => text.includes(needle));
}

export function mergeBudgetRows(
    currentRows: BudgetRow[],
    suggestedRows: BudgetRow[],
    revenue = false,
    timed = false,
    fixedCost = false,
) {
    return dedupeBudgetRows([
        ...currentRows
            .map((row) => normaliseBudgetRow(row, revenue, timed, fixedCost))
            .filter((row) => !isBlankBudgetRow(row)),
        ...suggestedRows.map((row) => ({
            ...normaliseBudgetRow(row, revenue, timed, fixedCost),
            confidence: row.confidence ?? 'guess',
        })),
    ]);
}

export function dedupeBudgetRows(rows: BudgetRow[]) {
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

export function isBlankBudgetRow(row: BudgetRow): boolean {
    return budgetRowLabel(row) === '' && numberFromInput(row.amount) === 0;
}

export function confidenceSummary(form: BudgetFormState) {
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

export function updateBudgetRow(
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

export function updateFutureCostRow(
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

export function updateFundingScenario(
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

export function budgetToForm(
    budget: BudgetPayload | undefined,
): BudgetFormState {
    return {
        expected_runway_months:
            budget?.expected_runway_months === null ||
            budget?.expected_runway_months === undefined
                ? ''
                : String(budget.expected_runway_months),
        forecast_years: String(budget?.forecast_years ?? 3),
        assumptions: normaliseBudgetAssumptions(budget?.assumptions),
        launch_costs: rowsOrBlank(budget?.launch_costs, false, true),
        monthly_fixed_costs: rowsOrBlank(
            budget?.monthly_fixed_costs,
            false,
            false,
            true,
        ),
        future_costs: futureRowsOrBlank(budget?.future_costs),
        revenue_forecast: rowsOrBlank(budget?.revenue_forecast, true),
        funding_sources: rowsOrBlank(budget?.funding_sources),
        funding_scenarios: fundingScenariosOrBlank(budget?.funding_scenarios),
    };
}

export function rowsOrBlank(
    rows: BudgetRow[] | undefined,
    revenue = false,
    timed = false,
    fixedCost = false,
) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseBudgetRow(row, revenue, timed, fixedCost))
        : [blankBudgetRow(revenue, timed, fixedCost)];
}

export function futureRowsOrBlank(rows: FutureCostRow[] | undefined) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseFutureCostRow(row))
        : [blankFutureCostRow()];
}

export function fundingScenariosOrBlank(
    rows: FundingScenarioRow[] | undefined,
) {
    return rows && rows.length > 0
        ? rows.map((row) => normaliseFundingScenario(row))
        : [blankFundingScenario()];
}

export function blankBudgetRow(
    revenue = false,
    timed = false,
    fixedCost = false,
): BudgetRow {
    return revenue
        ? {
              label: '',
              amount: '',
              quantity: 1,
              month: 1,
              growth_percent: 0,
              growth_cadence: 'annual',
              growth_cadence_confirmed: false,
              monthly_capacity_units: '',
              capacity_confirmed: false,
              founder_capacity_units: '',
              contractor_unit_cost: '',
              contractor_cost_confirmed: false,
              unit_label: 'units',
              variable_cost_percent: 0,
              unit_cost: '',
              gross_profit_percent: '',
              confidence: 'estimate',
              source_type: 'unverified',
              source_reference: '',
              source_confirmed: false,
          }
        : timed
          ? {
                label: '',
                amount: '',
                quantity: 1,
                month: 1,
                confidence: 'estimate',
            }
          : fixedCost
            ? {
                  label: '',
                  amount: '',
                  quantity: 1,
                  cadence: 'monthly',
                  cadence_confirmed: false,
                  confidence: 'estimate',
                  source_type: 'unverified',
                  source_reference: '',
                  source_confirmed: false,
              }
            : {
                  label: '',
                  amount: '',
                  quantity: 1,
                  confidence: 'estimate',
              };
}

export function blankFutureCostRow(): FutureCostRow {
    return {
        label: '',
        amount: '',
        quantity: 1,
        year: 2,
        recurring: false,
        classification: 'operating',
        useful_life_years: 3,
        confidence: 'estimate',
    };
}

export function blankFundingScenario(): FundingScenarioRow {
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

export function cleanBudgetForm(form: BudgetFormState) {
    return {
        expected_runway_months:
            form.expected_runway_months === ''
                ? null
                : numberFromInput(form.expected_runway_months),
        forecast_years: normaliseForecastYears(form.forecast_years),
        assumptions: {
            opening_cash_balance: numberFromInput(
                form.assumptions.opening_cash_balance,
            ),
            opening_cash_verified: Boolean(
                form.assumptions.opening_cash_verified,
            ),
            debtor_days: numberFromInput(form.assumptions.debtor_days),
            creditor_days: numberFromInput(form.assumptions.creditor_days),
            working_capital_verified: Boolean(
                form.assumptions.working_capital_verified,
            ),
            forecast_start_month: form.assumptions.forecast_start_month ?? '',
            forecast_start_confirmed: Boolean(
                form.assumptions.forecast_start_confirmed,
            ),
            funding_position:
                form.assumptions.funding_position === 'self_funded' ||
                form.assumptions.funding_position === 'external_funding'
                    ? form.assumptions.funding_position
                    : 'undecided',
            funding_position_confirmed: Boolean(
                form.assumptions.funding_position_confirmed,
            ),
            funding_request_purpose: String(
                form.assumptions.funding_request_purpose ?? '',
            ).trim(),
            revenue_growth_percent: signedNumberFromInput(
                form.assumptions.revenue_growth_percent,
            ),
            year_two_revenue_basis:
                form.assumptions.year_two_revenue_basis === 'year_one_average'
                    ? 'year_one_average'
                    : 'exit_run_rate',
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
        monthly_fixed_costs: cleanBudgetRows(
            form.monthly_fixed_costs,
            false,
            false,
            true,
        ),
        future_costs: cleanFutureCostRows(form.future_costs),
        revenue_forecast: cleanBudgetRows(form.revenue_forecast, true),
        funding_sources: cleanBudgetRows(form.funding_sources),
        funding_scenarios: cleanFundingScenarios(form.funding_scenarios),
    };
}

export function cleanBudgetRows(
    rows: BudgetRow[],
    revenue = false,
    timed = false,
    fixedCost = false,
) {
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
            ...(fixedCost
                ? {
                      cadence:
                          row.cadence === 'weekly' ||
                          row.cadence === 'fortnightly' ||
                          row.cadence === 'quarterly' ||
                          row.cadence === 'annual'
                              ? row.cadence
                              : 'monthly',
                      cadence_confirmed: Boolean(row.cadence_confirmed),
                  }
                : {}),
            ...(fixedCost || revenue
                ? {
                      source_type:
                          row.source_type === 'bank_statement' ||
                          row.source_type === 'xero_ledger' ||
                          row.source_type === 'supplier_quote' ||
                          row.source_type === 'signed_contract' ||
                          row.source_type === 'pipeline_evidence' ||
                          row.source_type === 'owner_record'
                              ? row.source_type
                              : 'unverified',
                      source_reference: String(
                          row.source_reference ?? '',
                      ).trim(),
                      source_confirmed: Boolean(row.source_confirmed),
                  }
                : {}),
            ...(revenue
                ? {
                      growth_percent: signedNumberFromInput(
                          row.growth_percent ?? row.monthly_growth_percent ?? 0,
                      ),
                      growth_cadence:
                          row.growth_cadence === 'annual'
                              ? 'annual'
                              : 'monthly',
                      growth_cadence_confirmed: Boolean(
                          row.growth_cadence_confirmed,
                      ),
                      monthly_capacity_units:
                          row.monthly_capacity_units === '' ||
                          row.monthly_capacity_units === undefined
                              ? null
                              : numberFromInput(row.monthly_capacity_units),
                      capacity_confirmed: Boolean(row.capacity_confirmed),
                      founder_capacity_units:
                          row.founder_capacity_units === '' ||
                          row.founder_capacity_units === undefined
                              ? null
                              : numberFromInput(row.founder_capacity_units),
                      contractor_unit_cost:
                          row.contractor_unit_cost === '' ||
                          row.contractor_unit_cost === undefined
                              ? null
                              : numberFromInput(row.contractor_unit_cost),
                      contractor_cost_confirmed: Boolean(
                          row.contractor_cost_confirmed,
                      ),
                      unit_label:
                          String(row.unit_label ?? 'units').trim() || 'units',
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

export function cleanFutureCostRows(rows: FutureCostRow[]) {
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
            classification:
                row.classification === 'capital' ? 'capital' : 'operating',
            useful_life_years: Math.min(
                20,
                Math.max(1, numberFromInput(row.useful_life_years ?? 3) || 3),
            ),
            confidence: budgetRowConfidence(row.confidence),
        }));
}

export function cleanFundingScenarios(rows: FundingScenarioRow[]) {
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

export function normaliseBudgetRow(
    row: BudgetRow,
    revenue = false,
    timed = false,
    fixedCost = false,
): BudgetRow {
    return {
        label: budgetRowLabel(row),
        amount: row.amount ?? '',
        quantity: numberFromInput(row.quantity ?? 1) || 1,
        confidence: budgetRowConfidence(row.confidence),
        ...(timed || revenue
            ? { month: numberFromInput(row.month ?? 1) || 1 }
            : {}),
        ...(fixedCost
            ? {
                  cadence:
                      row.cadence === 'weekly' ||
                      row.cadence === 'fortnightly' ||
                      row.cadence === 'quarterly' ||
                      row.cadence === 'annual'
                          ? row.cadence
                          : 'monthly',
                  cadence_confirmed: Boolean(row.cadence_confirmed),
              }
            : {}),
        ...(fixedCost || revenue
            ? {
                  source_type:
                      row.source_type === 'bank_statement' ||
                      row.source_type === 'xero_ledger' ||
                      row.source_type === 'supplier_quote' ||
                      row.source_type === 'signed_contract' ||
                      row.source_type === 'pipeline_evidence' ||
                      row.source_type === 'owner_record'
                          ? row.source_type
                          : 'unverified',
                  source_reference: String(row.source_reference ?? '').trim(),
                  source_confirmed: Boolean(row.source_confirmed),
              }
            : {}),
        ...(revenue
            ? {
                  growth_percent: signedNumberFromInput(
                      row.growth_percent ?? row.monthly_growth_percent ?? 0,
                  ),
                  growth_cadence:
                      row.growth_cadence === 'annual' ? 'annual' : 'monthly',
                  growth_cadence_confirmed: Boolean(
                      row.growth_cadence_confirmed,
                  ),
                  monthly_capacity_units:
                      row.monthly_capacity_units === undefined ||
                      row.monthly_capacity_units === null
                          ? ''
                          : row.monthly_capacity_units,
                  capacity_confirmed: Boolean(row.capacity_confirmed),
                  founder_capacity_units:
                      row.founder_capacity_units === undefined ||
                      row.founder_capacity_units === null
                          ? ''
                          : row.founder_capacity_units,
                  contractor_unit_cost:
                      row.contractor_unit_cost === undefined ||
                      row.contractor_unit_cost === null
                          ? ''
                          : row.contractor_unit_cost,
                  contractor_cost_confirmed: Boolean(
                      row.contractor_cost_confirmed,
                  ),
                  unit_label: String(row.unit_label ?? 'units'),
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

export function normaliseFutureCostRow(row: FutureCostRow): FutureCostRow {
    return {
        label: budgetRowLabel(row),
        amount: row.amount ?? '',
        quantity: numberFromInput(row.quantity ?? 1) || 1,
        year: Math.min(5, Math.max(2, numberFromInput(row.year ?? 2) || 2)),
        recurring: Boolean(row.recurring),
        classification:
            row.classification === 'capital' ? 'capital' : 'operating',
        useful_life_years: numberFromInput(row.useful_life_years ?? 3) || 3,
        confidence: budgetRowConfidence(row.confidence),
    };
}

export function normaliseFundingScenario(
    row: FundingScenarioRow,
): FundingScenarioRow {
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

export function normaliseBudgetAssumptions(
    assumptions: BudgetPayload['assumptions'] | undefined,
): BudgetAssumptions {
    return {
        opening_cash_balance: assumptions?.opening_cash_balance ?? '',
        opening_cash_verified: Boolean(assumptions?.opening_cash_verified),
        debtor_days: assumptions?.debtor_days ?? '',
        creditor_days: assumptions?.creditor_days ?? '',
        working_capital_verified: Boolean(
            assumptions?.working_capital_verified,
        ),
        revenue_growth_percent: assumptions?.revenue_growth_percent ?? '',
        forecast_start_month: assumptions?.forecast_start_month ?? '',
        forecast_start_confirmed: Boolean(
            assumptions?.forecast_start_confirmed,
        ),
        funding_position:
            assumptions?.funding_position === 'self_funded' ||
            assumptions?.funding_position === 'external_funding'
                ? assumptions.funding_position
                : 'undecided',
        funding_position_confirmed: Boolean(
            assumptions?.funding_position_confirmed,
        ),
        funding_request_purpose: assumptions?.funding_request_purpose ?? '',
        year_two_revenue_basis:
            assumptions?.year_two_revenue_basis === 'year_one_average'
                ? 'year_one_average'
                : 'exit_run_rate',
        cost_inflation_percent: assumptions?.cost_inflation_percent ?? '',
        target_gross_profit_percent:
            assumptions?.target_gross_profit_percent ?? '',
        target_net_profit_before_tax_percent:
            assumptions?.target_net_profit_before_tax_percent ?? '',
        target_net_profit_after_tax_percent:
            assumptions?.target_net_profit_after_tax_percent ?? '',
    };
}

export function budgetRowLabel(row: BudgetRow): string {
    return String(row.label ?? '').trim();
}

export function budgetRowConfidence(
    confidence: BudgetRow['confidence'] | null | undefined,
): NonNullable<BudgetRow['confidence']> {
    return confidence === 'known' || confidence === 'guess'
        ? confidence
        : 'estimate';
}

export function numberFromInput(
    value: string | number | null | undefined,
): number {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value ?? '').replace(/[^0-9.-]/g, ''));

    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

export function signedNumberFromInput(
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

export function normaliseForecastYears(value: string | number): number {
    const years = numberFromInput(value);

    return years === 1 || years === 2 || years === 5 ? years : 3;
}

export function formatCurrency(value: number | null | undefined): string {
    return formatNzdCurrency(value);
}

export function countLabel(
    count: number,
    singular: string,
    plural = `${singular}s`,
): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function formatRunway(
    months: number | null | undefined,
    openEnded: boolean | undefined,
): string {
    if (months === null || months === undefined) {
        return '-';
    }

    return openEnded ? `${months}+ months` : `${months} months`;
}

export function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : 'Not reached';
}

export function findSection(
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

export function budgetPlanSource(
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

export function requirementId(requirement: PlanRequirementPayload): string {
    return `${requirement.phase_key}:${requirement.key}`;
}

export function planWorkspaceKey(profileId: string): string {
    return `fsa:entrepreneur-plan-workspace:${profileId}:v1`;
}

export function sectionAutosaveStateLabel(state: AutosaveState): string {
    const labels = {
        idle: '',
        saving: 'Saving draft',
        saved: 'Draft saved',
        error: 'Draft not saved',
    };

    return labels[state];
}

export function ideaValidationToForm(
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
