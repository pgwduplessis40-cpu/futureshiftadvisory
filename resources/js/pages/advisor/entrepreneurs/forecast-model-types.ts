export type Calendar = {
    start_month: string;
    months: number;
};
export type CashOpening = {
    amount: string | null;
    source: string | null;
    verified: boolean | null;
};
export type Reserve = {
    monthly_fixed_costs: string | null;
    months_covered: number;
    includes_owner_pay: boolean | null;
    one_off_cash_costs: string | null;
    contingency_rate: string;
    basis: string | null;
    base_reserve: string | null;
    contingency: string | null;
    target: string | null;
    missing: string[];
};
export type ActualRow = {
    period: string;
    state: string | null;
    reconciled: boolean | null;
    closing_bank_cash: string | null;
    reviewed_by: string | null;
    reviewed_on: string | null;
    pre_trading_evidence: string | null;
};
export type StreamMonthRow = {
    period: string;
    booked_units: string | null;
    delivery_units: string | null;
    invoiced_units: string | null;
    founder_hours: string | null;
    additional_capacity_units: string | null;
    provider: string | null;
    provider_unit_cost: string | null;
    no_cost_basis: string | null;
};
export type StreamRow = {
    id: string;
    name: string;
    unit: string | null;
    price: string | null;
    preparation_hours_per_unit: string | null;
    delivery_hours_per_unit: string | null;
    /** Three states: true base case, false upside, null nobody has said. */
    in_base_case: boolean | null;
    evidence_status: string | null;
    schedule: StreamMonthRow[];
};
export type FounderMonthRow = {
    period: string;
    available_hours: string | null;
    administration_hours: string | null;
    business_development_hours: string | null;
};
export type SettlementRow = {
    id: string;
    source: string;
    date: string | null;
    amount: string | null;
    category: string;
    /** Three states: true verified, false not verified, null nobody has said. */
    verified: boolean | null;
};
export type TradeItemRow = {
    id: string;
    source: string;
    kind: string;
    raised_on: string | null;
    amount: string | null;
    /** null when nobody has recorded how the item settles, which is not the
     * same as an item recorded as settling with nothing. */
    settlements: SettlementRow[] | null;
};
export type OwnerPayRow = {
    effective_on: string;
    weekly_amount: string | null;
    trigger: string;
    threshold: string | null;
};
export type AssetRow = {
    id: string;
    source: string;
    cost: string | null;
    /** Three states: 'capital', 'expense', or null for not yet classified. */
    classification: string | null;
    policy_source: string | null;
    reviewed: boolean | null;
    paid_on: string | null;
    recognition_start_on: string | null;
    life_months: number | null;
    residual: string | null;
    method: string | null;
    opening_depreciation: string | null;
};
export type TaxPeriodRow = {
    start_on: string;
    end_on: string;
    accounting_profit: string | null;
    tax_adjustments: string | null;
};
export type TaxRow = {
    entity_basis: string | null;
    rate_percent: string | null;
    losses_eligible: boolean | null;
    policy_source: string | null;
    eligibility_source: string | null;
    version: string | null;
    periods: TaxPeriodRow[];
};
export type Gap = {
    reason: string;
    months: number;
    first_period: string;
};
/** What the engine makes of the reconstruction, as distinct from what has
 * been recorded into it. A complete-looking set of sections can still fail
 * to project. */
export type Projection = {
    engine_version: string;
    status: string;
    months_projected: number;
    base_revenue_months: number;
    missing: Gap[];
    errors: Gap[];
    /** Set when the engine refused the reconstruction outright. */
    refusal: string | null;
};
/** The dated cash the reconstruction implies. The deficit and the shortfall
 * are never summed: a deficit is already inside the shortfall. */
export type CashRun = {
    status: string;
    movements_counted: number;
    settled_before_opening: number;
    lowest_cash: string | null;
    lowest_cash_date: string | null;
    cash_deficit: string | null;
    reserve_target: string | null;
    reserve_shortfall: string | null;
    missing: Gap[];
    refusal: string | null;
};
export type Section = {
    key: string;
    label: string;
    captured: boolean;
    editable: boolean;
    detail: string;
};
export type ForecastModel = {
    revision: number;
    engine_version: string;
    readable: boolean;
    load_error: string | null;
    selected: boolean;
    selected_at: string | null;
    selected_by: string | null;
    calendar: Calendar | null;
    cash_opening: CashOpening;
    reserve: Reserve;
    actuals: ActualRow[];
    streams: StreamRow[];
    founder_months: FounderMonthRow[];
    funding_sources: FundingRow[];
    funding_inventory_complete: boolean | null;
    fixed_costs: FixedCostRow[];
    fixed_cost_schedule_complete: boolean | null;
    trade_items: TradeItemRow[];
    owner_pay_steps: OwnerPayRow[];
    assets: AssetRow[];
    tax: TaxRow;
    projection: Projection | null;
    cash: CashRun | null;
    owner_pay_source: string | null;
    owner_pay_first_payment_on: string | null;
    opening_eligible_losses: string | null;
    sections: Section[];
};
/** The shape the save form posts. Actual rows carry strings for every field
 * the advisor types, so an empty box stays empty rather than becoming zero. */
export type ActualInput = {
    period: string;
    state: string;
    reconciled: boolean;
    closing_bank_cash: string;
    reviewed_by: string;
    reviewed_on: string;
    pre_trading_evidence: string;
};
export type SaveForm = {
    expected_revision: number | null;
    start_month: string;
    months: number;
    opening_amount: string;
    opening_source: string;
    opening_verified: boolean;
    reserve_monthly_fixed_costs: string;
    reserve_months_covered: number;
    reserve_includes_owner_pay: boolean;
    reserve_one_off_cash_costs: string;
    reserve_contingency_rate: string;
    reserve_basis: string;
    actuals: ActualInput[];
};
/** Every driver box posts a string, including the ones that hold numbers, so
 * an empty box stays unknown instead of arriving as a confident zero. */
export type StreamMonthInput = {
    period: string;
    booked_units: string;
    delivery_units: string;
    invoiced_units: string;
    founder_hours: string;
    additional_capacity_units: string;
    provider: string;
    provider_unit_cost: string;
    no_cost_basis: string;
};
export type StreamInput = {
    id: string;
    name: string;
    unit: string;
    price: string;
    preparation_hours_per_unit: string;
    delivery_hours_per_unit: string;
    /** '' when nobody has classified the stream, else 'base' or 'upside'. */
    in_base_case: string;
    evidence_status: string;
    schedule: StreamMonthInput[];
};
export type FounderMonthInput = {
    period: string;
    available_hours: string;
    administration_hours: string;
    business_development_hours: string;
};
export type DriversForm = {
    expected_revision: number | null;
    streams: StreamInput[];
    founder_months: FounderMonthInput[];
};
/** Every yes/no question on the terms form posts '', 'yes' or 'no' rather
 * than a boolean, so that "nobody has answered" survives the wire as itself
 * instead of arriving indistinguishable from an answer of no. */
export type SettlementInput = {
    id: string;
    source: string;
    date: string;
    amount: string;
    category: string;
    verified: string;
};
export type TradeItemInput = {
    id: string;
    source: string;
    kind: string;
    raised_on: string;
    amount: string;
    settlements: SettlementInput[];
};
export type OwnerPayInput = {
    effective_on: string;
    weekly_amount: string;
    trigger: string;
    threshold: string;
};
export type AssetInputRow = {
    id: string;
    source: string;
    cost: string;
    classification: string;
    policy_source: string;
    reviewed: string;
    paid_on: string;
    recognition_start_on: string;
    life_months: string;
    residual: string;
    method: string;
    opening_depreciation: string;
};
export type TaxPeriodInput = {
    start_on: string;
    end_on: string;
    accounting_profit: string;
    tax_adjustments: string;
};
/** One named source of funding as the page shows it back. */
export type FundingRow = {
    id: string;
    source: string;
    identity: string | null;
    type: string | null;
    amount: string | null;
    commitment: string | null;
    available_from: string | null;
    drawn_on: string | null;
    restriction: string | null;
    verified: boolean | null;
};

/** The same source as the form posts it: every field a string, so a box left
 * empty stays an unanswered question rather than becoming an answer. */
export type FundingInput = {
    id: string;
    source: string;
    identity: string;
    type: string;
    amount: string;
    commitment: string;
    available_from: string;
    drawn_on: string;
    restriction: string;
    verified: string;
};

/** One recurring overhead as the page shows it back. */
export type FixedCostRow = {
    id: string;
    source: string;
    rate: string | null;
    quantity: string | null;
    frequency: string | null;
    first_invoice_on: string | null;
    last_invoice_on: string | null;
    creditor_days: number | null;
    reviewed: boolean | null;
};

/** The same cost as the form posts it: every field a string, so a box left
 * empty stays an unanswered question rather than becoming an answer. */
export type FixedCostInput = {
    id: string;
    source: string;
    rate: string;
    quantity: string;
    frequency: string;
    first_invoice_on: string;
    last_invoice_on: string;
    creditor_days: string;
    reviewed: string;
};

export type TermsForm = {
    expected_revision: number | null;
    funding_sources: FundingInput[];
    funding_inventory_complete: string;
    fixed_costs: FixedCostInput[];
    fixed_cost_schedule_complete: string;
    trade_items: TradeItemInput[];
    owner_pay_steps: OwnerPayInput[];
    owner_pay_source: string;
    owner_pay_first_payment_on: string;
    assets: AssetInputRow[];
    tax_entity_basis: string;
    tax_rate_percent: string;
    tax_losses_eligible: string;
    tax_policy_source: string;
    tax_eligibility_source: string;
    tax_version: string;
    tax_periods: TaxPeriodInput[];
    opening_eligible_losses: string;
};
export type ForecastModelProps = {
    model: ForecastModel | null;
    engine: { engine_version: string; components: Record<string, string> };
    can_edit: boolean;
    urls: {
        review: string;
        save: string;
        drivers: string;
        terms: string;
        selection: string;
        gate: string;
        workspace: string;
    };
};
