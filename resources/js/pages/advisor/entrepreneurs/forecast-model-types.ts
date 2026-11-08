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
export type ForecastModelProps = {
    model: ForecastModel | null;
    engine: { engine_version: string; components: Record<string, string> };
    can_edit: boolean;
    urls: {
        review: string;
        save: string;
        drivers: string;
        selection: string;
        gate: string;
        workspace: string;
    };
};
