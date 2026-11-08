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
export type ForecastModelProps = {
    model: ForecastModel | null;
    engine: { engine_version: string; components: Record<string, string> };
    can_edit: boolean;
    urls: {
        review: string;
        save: string;
        selection: string;
        gate: string;
        workspace: string;
    };
};
