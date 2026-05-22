# Financial Analysis Module

WO-44 adds the Phase 2 financial analysis adapter on top of the shared analysis
spine.

## Module Shape

`FinancialAnalysis` implements `AnalysisModule` with prompt id `analysis.financial`.
It does not call AI directly; `AnalysisRunner` still owns data-quality scoring,
document verification, prompt envelope construction, attribution validation, bias
inspection, finding persistence, red-flag promotion, and audit.

The module emits all four analytical lenses:

- Descriptive: latest revenue, margins, operating cash flow, and liquidity ratio.
- Diagnostic: profitability, operating-expense, cash-conversion, and liquidity
  drivers.
- Predictive: NZ economic overlay from OCR, CPI, GDP, and unemployment rows.
- Prescriptive: a financially measurable improvement opportunity for margin and
  cash conversion.

## Inputs

The preferred source is the latest `financial_snapshots` row for the client,
created by WO-37 accounting integrations. Snapshot-backed findings cite exact
metric paths such as:

- `financial_snapshot:{id}:profit_and_loss.revenue`
- `financial_snapshot:{id}:cash_flow.operating_cash_flow`
- `financial_snapshot:{id}:metrics.current_ratio`

The module also includes latest economic indicator rows where available, citing:

- `economic_indicator:{id}:{indicator}`

When no accounting snapshot exists, the module falls back to questionnaire
answers and stamps each finding with an accounting-fallback disclaimer. That path
does not create PV opportunities because the financial basis is not verified
enough for PV ranking.

## PV Linkage

`FinancialAnalysisRunner` is the WO-44 orchestration entry point. After the
analysis run completes, it creates one WO-42 `improvement_opportunities` row from
the prescriptive financial finding and writes that row id back to
`analysis_findings.pv_link_id`.

This keeps the analysis spine generic while allowing financial findings to feed
the PV waterfall without duplicating PV logic.

## Boundaries

WO-44 does not implement continuous monitoring; WO-38 owns scheduled pulls and
deterioration alerts. WO-44 does not add new schema. It reads accounting,
economic, questionnaire, and PV tables already created by earlier work orders.
