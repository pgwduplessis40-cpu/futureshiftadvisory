import type {
    IdeaValidationVersion,
    SubmittedPlanVersion,
} from './plan-dashboard-panels';
export type ProfilePayload = {
    id: string;
    name: string;
    company_name: string | null;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
};

export type IdeaValidationPayload = {
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

export type IdeaValidationForm = {
    problem: string;
    target_customer: string;
    solution: string;
    value_proposition: string;
    demand_signal: string;
    revenue_model: string;
};

export const IDEA_VALIDATION_FIELD_MAX_LENGTH = 10000;
export const PLAN_SECTION_BODY_MAX_LENGTH = 25000;

export type BusinessPlanPayload = {
    id: string;
    title: string;
    status: string;
    completed_at: string | null;
    updated_at: string | null;
    requirements_complete: boolean;
    missing_requirements: string[];
    executive_summary: ExecutiveSummaryPayload;
    budget: BudgetPayload;
    latest_assessment: {
        id: string;
        round: number;
        status: string;
        overall_grade: string;
        finalised_at: string | null;
        url: string;
    } | null;
    history: SubmittedPlanVersion[];
    phases: PlanPhasePayload[];
} | null;

export type ExecutiveSummaryPayload = {
    present: boolean;
    generated: boolean;
    generated_by_ai: boolean;
    stale: boolean;
    usable: boolean;
    legacy_draft: boolean;
    can_generate: boolean;
    section_id: string | null;
    generated_at: string | null;
    source: string | null;
    model: string | null;
    prompt_hash: string | null;
    context_hash: string | null;
    stored_context_hash: string | null;
    status_label: string;
    readiness_reason: string | null;
};

export type PlanPhasePayload = {
    id: string;
    key: string;
    title: string;
    status: string;
    requirements: PlanRequirementPayload[];
    sections: PlanSectionPayload[];
};

export type PlanTemplatePhasePayload = {
    key: string;
    title: string;
    requirements: PlanRequirementPayload[];
};

export type PlanRequirementPayload = {
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

export type BudgetRow = {
    label: string;
    amount: string | number;
    quantity?: string | number;
    month?: string | number;
    cadence?: 'weekly' | 'fortnightly' | 'monthly' | 'quarterly' | 'annual';
    cadence_confirmed?: boolean;
    growth_percent?: string | number;
    monthly_growth_percent?: string | number;
    growth_cadence?: 'monthly' | 'annual';
    growth_cadence_confirmed?: boolean;
    monthly_capacity_units?: string | number;
    capacity_confirmed?: boolean;
    founder_capacity_units?: string | number;
    contractor_unit_cost?: string | number;
    contractor_cost_confirmed?: boolean;
    unit_label?: string;
    variable_cost_percent?: string | number;
    unit_cost?: string | number;
    gross_profit_percent?: string | number;
    confidence?: 'known' | 'estimate' | 'guess';
    source_type?:
        | 'bank_statement'
        | 'xero_ledger'
        | 'supplier_quote'
        | 'signed_contract'
        | 'pipeline_evidence'
        | 'owner_record'
        | 'unverified';
    source_reference?: string;
    source_confirmed?: boolean;
    description?: string;
};

export type BudgetAssumptions = {
    opening_cash_balance: string | number;
    debtor_days: string | number;
    creditor_days: string | number;
    revenue_growth_percent: string | number;
    year_two_revenue_basis: 'exit_run_rate' | 'year_one_average';
    cost_inflation_percent: string | number;
    target_gross_profit_percent: string | number;
    target_net_profit_before_tax_percent: string | number;
    target_net_profit_after_tax_percent: string | number;
    forecast_start_month?: string;
    opening_cash_verified?: boolean;
    working_capital_verified?: boolean;
    forecast_start_confirmed?: boolean;
    funding_position?: 'self_funded' | 'external_funding' | 'undecided';
    funding_position_confirmed?: boolean;
    funding_request_purpose?: string;
};

export type FutureCostRow = BudgetRow & {
    year?: string | number;
    recurring?: boolean;
    classification?: 'operating' | 'capital';
    useful_life_years?: string | number;
};

export type FundingScenarioRow = {
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

export type BudgetPayload = {
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

export type BudgetFlag = {
    key: string;
    title: string;
    message: string;
    severity: string;
    first_raised_at?: string;
    acknowledged_at?: string | null;
};

export type BudgetFormState = {
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

export type BudgetSetupMode = 'guided' | 'advanced';

export type BudgetGroupKey = keyof Pick<
    BudgetFormState,
    | 'launch_costs'
    | 'monthly_fixed_costs'
    | 'future_costs'
    | 'revenue_forecast'
    | 'funding_sources'
>;

export type PlanSectionPayload = {
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
    updated_at: string | null;
    guidance_url: string;
};

export type ReportPayload = {
    id: string;
    title: string;
    type: string;
    generated_at: string | null;
    view_url: string;
    download_url: string;
};

export type AdvisoryRequestPayload = {
    available: boolean;
    requested: boolean;
    request_url: string;
    thread_url: string | null;
    blockers: string[];
};

export type GamificationPayload = {
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
    points?: {
        total: number;
        milestone_count: number;
    };
    current_streak?: number;
    new_badge_count?: number;
    next_milestone?: {
        label: string;
        progress_percent: number;
    } | null;
    next_quest?: {
        label: string;
        points: number;
        description: string;
    } | null;
};

export type PackageAccessPayload = {
    package_scope: 'idea_validation' | 'plan_budget' | 'combo';
    package_scope_label: string;
    package_label: string;
    includes_idea_validation: boolean;
    includes_plan_budget: boolean;
    included_stages: string[];
    client_outcomes: string[];
    source_activation_id: string | null;
};

export type Props = {
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
        companyNameUpdate: string;
        sectionStore: string;
        budgetUpdate: string;
        budgetPack: string;
        budgetPackPdf: string;
        budgetFlagAcknowledge: string;
        budgetAdvisorNudgeDismiss: string;
        assistRequirement: string;
        executiveSummary: string;
        preview: string;
        submit: string;
        documentUpload: string;
        messages: string;
        advisoryRequest: string;
    };
};
