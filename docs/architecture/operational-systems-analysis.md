# Operational Analysis And Systems Review

WO-49 adds two Phase 2 analysis adapters on the shared analysis spine:

- `OperationalAnalysis` with prompt id `analysis.operational`
- `SystemsReview` with prompt id `analysis.systems`

Both modules use questionnaire evidence, cite every persisted finding, and rely
on the shared analysis runner for data quality, document verification,
attribution validation, bias inspection, red-flag promotion, and audit.

## Operational Analysis

Operational findings cover:

- SOPs and handovers
- process flow and bottlenecks
- capacity trajectory
- automation opportunities

The module emits descriptive, diagnostic, predictive, and prescriptive findings.
Evidence is cited as `questionnaire_answer:{id}`.

## Systems Review

Systems findings cover:

- technology gaps
- integration issues
- manual workarounds and duplicate entry
- upgrade sequencing opportunities

The module emits descriptive, diagnostic, predictive, and prescriptive findings.
Evidence is cited as `questionnaire_answer:{id}`.

## Boundaries

WO-49 adds no schema and does not create implementation tasks, vendor
recommendations, procurement workflows, or automated monitoring. Prescriptive
findings can flow into proposal focus areas, render under "What needs to be
fixed", and carry into signed-proposal strategic plan priorities and milestones.
Diagnostic operational and systems findings also render into the client report's
"What is wrong" section, so the problem statement and proposed fix stay tied
together across report, proposal, and strategic plan. Later work is still needed
to turn them into PV-ranked initiatives, delivery tasks, or automation backlogs.
