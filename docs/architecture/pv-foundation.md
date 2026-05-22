# PV Foundation

WO-40 creates the shared present-value foundation used by later business valuation, improvement opportunity, and risk-cost work.

## Scope

This work order supplies:

- `PvEngine` for discounting cash flows and terminal values
- `DiscountRateResolver` for the four approved discount-rate methods
- `pv_calculations` as the persisted, client-scoped calculation ledger
- `PvType` and `DiscountMethod` enums

WO-40 does not implement the three PV product types. Business valuation lands in WO-41; improvement and risk-cost PV land in WO-42.

## Discount Methods

`DiscountRateResolver` supports:

- `ocr_linked`: latest OCR economic indicator plus a risk premium
- `industry_wacc`: advisor-reviewed industry WACC assumption
- `advisor_configured`: explicit advisor rate with rationale
- `client_inputted`: explicit client rate with rationale

Every resolved rate returns a rationale and source attributions. OCR-linked rates cite the exact `economic_indicators` row, so the rate automatically changes when the latest OCR indicator changes.

## Calculation Ledger

`pv_calculations` stores:

- client
- PV type
- discount method, rate, and rationale
- input cash-flow payload
- result payload
- `as_at`
- optional creator
- source attributions

Client-scoped RLS applies. Later work orders link their domain rows back to this ledger rather than recalculating silently.

## Math Contract

Cash flows are discounted by period:

```text
PV = cash_flow / (1 + discount_rate) ^ period
```

Terminal value uses Gordon growth:

```text
terminal = cash_flow * (1 + growth_rate) / (discount_rate - growth_rate)
discounted_terminal = terminal / (1 + discount_rate) ^ period
```

Growth rate must be below the discount rate. Rates are stored as decimals, for example `0.12` for 12%.
