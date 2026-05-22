# Client knowledge assessment

WO-35 records an advisor-assessed client knowledge profile and injects the resulting calibration into Phase 2 analysis prompts.

## Assessment model

`knowledge_assessments` stores three 1-5 scores:

- `financial_literacy`
- `strategic_awareness`
- `leadership`

Each row belongs to a client, stores the advisor who assessed it, and persists the derived `calibration` payload as JSONB. Client-scoped RLS applies, matching the existing advisor/client access model.

## Prompt calibration

`KnowledgeCalibration` reads the latest assessment for a client and returns a calibration block for `AnalysisRunner`. The runner injects that block into every analysis prompt under `knowledge_calibration`, so downstream prompt hashes and stored run metadata reflect the client knowledge profile used at execution time.

When no assessment exists, the service returns a standard default block with `source=default`. When an assessment exists, the block carries:

- language depth
- financial detail level
- strategic framing
- leadership context
- advisor review note
- raw scores
- source assessment id and timestamp

The calibration changes prompt language and depth only. It does not change data-quality gates, source attribution, bias monitoring, red-flag promotion, or finding persistence rules.

## Advisor surface

The advisor client detail page exposes a compact assessment panel. Advisors can record the three scores and immediately see the latest calibration labels used by future analysis runs.

## Coaching-signal boundary

If the leadership score is at or below the Phase 2 raw-observation threshold, WO-35 writes one `coaching_signals` row with signal type `leadership_capability_gap`.

That row is intentionally neutral evidence for Phase 3:

- `raw_observation_only=true`
- `auto_referral=false`
- no coach referral is generated
- no Phase 2 service reads the row for detection, calibration, thresholding, or action

This preserves the Phase 2 boundary from `PLAN-PHASE2.md`: raw observations may be stored now, while coaching referral signal detection and calibration remain Phase 3 work.
