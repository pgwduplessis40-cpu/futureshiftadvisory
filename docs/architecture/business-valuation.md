# Business Valuation

WO-41 implements PV Type 1: business valuation. It consumes the WO-40 PV foundation and WO-39 valuation multiple feed.

## Methods

`BusinessValuation` calculates three side-by-side methods:

- SDE multiple value
- EBITDA multiple value
- DCF value with explicit terminal value

SDE and EBITDA multiples come from active `valuation_multiples` rows for the selected industry. DCF uses `PvEngine` and persists its calculation in `pv_calculations`.

## Financial Inputs

The service prefers the latest connected `financial_snapshots` row for the client. It reads:

- `metrics.sde`
- `metrics.ebitda`
- `cash_flow.operating_cash_flow`

When no accounting snapshot exists, advisor/questionnaire financial inputs can be supplied. That path records a data-quality disclaimer on the valuation because the calculation is not tied to connected accounting data.

## Reconciliation

Each method produces low, mid, and high values. The reconciled range is the average of the three methods plus any explicit advisor adjustments.

Adjustments are recorded as structured rows with:

- label
- amount
- rationale

The adjustment contract is deliberately transparent. No adjustment is hidden inside the multiple or DCF calculations.

## Attribution

Every valuation records source attributions for:

- financial input source, either `financial_snapshot:{id}` or questionnaire source reference
- active SDE multiple row
- active EBITDA multiple row
- DCF discount-rate source attribution

WO-78 reuses this engine for due-diligence valuation through
`App\Services\Dd\Valuation`. That adapter forces target financial inputs and
adds FX normalisation, but the SDE, EBITDA, DCF, and PV maths stay here.
