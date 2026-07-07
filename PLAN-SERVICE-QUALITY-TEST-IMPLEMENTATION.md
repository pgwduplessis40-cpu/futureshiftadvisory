# Service Quality Review - Test Implementation Plan

Prepared: 2026-07-07

## Purpose

This plan converts the attached service quality and technical review into a staged implementation path for the test environment. The first test rollout should protect client-facing defensibility: ROI, PV waterfall, pack readiness, proposal evidence, and calculation correctness. Broader advisory-depth improvements follow once those release blockers are stable.

Production deployment is out of scope for this plan. Test must use non-production credentials, existing `.env.testing` safeguards, and the live-to-test runbook in `CODEX_LIVE_TO_TEST_SYNC.codex.md`.

## Review Outcome

The main findings in the review are supported by the current codebase:

- PV rows have no active/superseded lifecycle. `ImprovementPv::rank()` and `RiskCostPv::rank()` create rows, while `FeeCalculator`, `PvWaterfallBuilder`, and practice health reporting sum client rows without an active filter.
- The Standard Advisory pack gate currently allows generation when `analysisCompleted > 0`, so a partial analysis set can look ready.
- Analysis modules call the AI runner but largely build deterministic template findings, using the AI response mostly for uncertainty and integrity plumbing.
- Budget calculation has an important fixed-cost timing defect, and several budget assumptions are either dead inputs or disclosed simplifications that need clearer modelling before client use.
- Proposal and strategic-plan generation use the right linkage architecture, but focus areas can duplicate, attributions do not reach the client artifact, and one strategic-plan section maps the wrong source field.
- Several smaller correctness issues are concrete and testable: `PvEngine::presentValue()` period handling, Anthropic client resilience, dropped-finding visibility, and internal jargon in client-facing text.

## Implementation Principles

- Keep historical calculation rows for audit, but ensure only current active rows drive ROI, waterfall, fee, proposal, and practice-health totals.
- Preserve source attributions and uncertainty all the way into client-facing artifacts.
- Treat test as a live-like environment: back up the database, run migrations forward, rebuild assets, clear caches, and run smoke scenarios with advisor-visible outcomes.
- Implement release blockers in small branches/work orders. Avoid bundling valuation methodology redesign with the PV lifecycle fix.
- Prefer additive migrations with reversible application behavior over destructive cleanup.

## Workstream 0 - Baseline And Test Readiness

Goal: create a reproducible baseline before any service-quality changes land in test.

Tasks:

- Confirm test branch, commit hash, database name, PHP/Node versions, and current migration status.
- Back up the test database before applying new migrations.
- Confirm test `.env` has no production secrets and AI/external integrations are fake or sandboxed.
- Run baseline verification:
  - `npm run lint:check`
  - `npm run types:check`
  - `npm run build`
  - `php -d memory_limit=2048M vendor/phpunit/phpunit/phpunit --no-coverage`
- Capture focused baseline failures separately from new-regression failures.

Acceptance criteria:

- A dated baseline note exists with current commit, migration status, and test results.
- Test environment can be restored from backup if a migration or data change is wrong.

## Workstream 1 - PV Lifecycle And ROI Defensibility

Priority: release blocker.

Problem:

Repeated analysis/report generation can create duplicate active improvement/risk PV rows. Those rows inflate fee ROI, waterfall target PV, and proposal PV summaries.

Implementation:

- Add additive lifecycle fields to `improvement_opportunities` and `risk_costs`, such as:
  - `source_fingerprint`
  - `superseded_at`
  - `superseded_reason`
  - `superseded_by_user_id` or `superseded_by_run_id` if the current call context can provide it
- Generate a stable fingerprint from:
  - `client_id`
  - `analysis_finding_id` when present
  - otherwise normalized title plus primary source reference
  - PV type/context
- In `ImprovementPv::rank()` and `RiskCostPv::rank()`, wrap updates and inserts in a transaction:
  - derive incoming fingerprints
  - supersede existing active rows with matching fingerprints
  - insert the new rows as active
- Add query scopes on the models for active rows.
- Update all PV consumers to use active rows only:
  - `FeeCalculator`
  - `PvWaterfallBuilder`
  - `PracticeHealthReport`
  - proposal PV summaries
  - report sections that sum improvement/risk PV
- Add a one-time data migration to mark older duplicate rows superseded while keeping the latest row active for each fingerprint.

Tests:

- Re-running financial analysis for the same client leaves active improvement PV totals unchanged.
- Re-composing a due-diligence report leaves active risk-cost PV totals unchanged.
- Fee ROI and waterfall target PV remain stable after repeated runs.
- Historical superseded rows remain queryable for audit.
- Existing PV waterfall, fee, proposal, and report tests continue to pass.

Test environment smoke:

- Pick or seed one Standard Advisory client with a financial finding.
- Run analysis twice.
- Generate a fee calculation and proposal.
- Confirm improvement PV, target PV, and ROI are identical after each repeat.

## Workstream 2 - Advisory Pack Readiness Gate

Priority: release blocker.

Problem:

The pack can currently generate when only one module has completed. Failed, stale, or missing modules are not sufficiently surfaced.

Implementation:

- Define the required Standard Advisory module set from `App\Enums\AnalysisModule`.
- Change readiness logic so `can_generate_pack` requires:
  - required questionnaire response exists
  - document verification blockers are clear
  - every required analysis module has a latest successful run
  - no required run is stale beyond the agreed freshness window
- Add an explicit advisor waiver path for intentional partial packs:
  - records who waived, which module(s), reason, and timestamp
  - prints or exposes the waiver state in advisor-facing readiness
  - does not silently treat missing modules as complete
- Surface module states in readiness payload:
  - complete
  - missing
  - failed
  - stale
  - waived
- Keep generation blocked if a module failed and no waiver exists.

Tests:

- One completed module does not allow pack generation.
- All required modules completed allows pack generation.
- Failed module blocks generation and is visible in readiness.
- Stale module blocks generation or requires waiver.
- Waiver permits generation and is audit logged.

Test environment smoke:

- Run one module for a client and confirm pack generation is blocked.
- Complete or fake all required modules and confirm readiness becomes true.
- Create one waiver and confirm it is visible before generating a test pack.

## Workstream 3 - Budget Calculation And Evidence Gate

Priority: high.

Problem:

The budget engine is strong, but fixed monthly costs ignore their start month, some inputs imply modelling that does not exist, and the unlock/evidence path relies too heavily on file names and virus-scan status.

Implementation:

- Fix `BudgetCalculator::fixedCostsForMonth()` so fixed costs respect each row's start month.
- Decide and implement the `investor_equity_percent` behavior:
  - either model founder dilution/equity sold in scenario outputs
  - or remove/hide the input from client-facing flows until dilution modelling exists
- Add an annual tax calculation mode or loss carry-forward within the forecast year.
- Add automatic sensitivity scenarios:
  - base case
  - revenue downside, for example minus 20 percent
  - cost upside, for example plus 10 percent
  - combined downside
- Rework budget unlock so uploaded financials must be verified, not merely named like a P&L.
- Reconcile budget assumptions against the latest `FinancialSnapshot` when available, and flag material differences.
- Keep missing assumptions from producing confident outputs; either block finalization or mark the output as draft/incomplete.

Tests:

- Fixed cost with `month = 6` starts in month 6, not month 1.
- Investor equity either changes dilution output or is not accepted as a meaningful scenario input.
- Seasonal losses offset later taxable profit when annual/loss-carry-forward mode is active.
- Verified financial document unlocks budget; disputed or unverified document does not.
- Financial snapshot discrepancy creates a budget warning.
- Downside scenario appears in computed output and analytics.

Test environment smoke:

- Create a budget with a month-6 fixed cost and confirm month-1 cash is not distorted.
- Upload or seed one verified financial document and one disputed document; confirm only the verified one unlocks budget readiness.
- Compare base and downside scenario charts in the advisor view.

## Workstream 4 - Proposal And Strategic Plan Evidence

Priority: high.

Problem:

The proposal pipeline correctly links findings to client commitments, but repeated historical findings can duplicate focus areas, attributions are not shown to clients, and one strategic-plan section uses the wrong field.

Implementation:

- Deduplicate proposal focus areas by finding fingerprint:
  - module
  - normalized title
  - lens/severity
  - source reference or `analysis_finding_id`
- Preserve severity ordering for engaged clients.
- For prospect-first proposals, either:
  - expose website-led ordering as an advisor-controlled option
  - or disclose it clearly in advisor-only metadata
- Carry finding attributions into proposal HTML/PDF:
  - short source labels near each focus area
  - source detail in an appendix if needed
- Fix `StrategicPlanService` so "Budget and affordability" uses budget/payment-term content, not `evidence_documents`.
- Replace internal jargon in client-facing output:
  - `WO-42 improvement PV`
  - `phase_3_signoff_enabled`
  - other implementation labels that appear in PDFs or acceptance terms

Tests:

- Duplicate historical findings produce one proposal focus area.
- Proposal PDF/HTML includes source attribution for factual focus-area claims.
- Strategic plan budget section uses the correct source text.
- Client-facing proposal text contains no internal implementation identifiers.

Test environment smoke:

- Seed duplicate financial findings and generate a proposal.
- Confirm only one focus area is shown and its citation/source appears.
- Sign the proposal and generate the strategic plan; confirm budget section content is correct.

## Workstream 5 - Technical Correctness And Integrity Plumbing

Priority: medium-high.

Implementation:

- Route `AnthropicClaudeClient` through `ResilientHttp`:
  - use configured retry/timeout/circuit behavior
  - send the Anthropic key using the required header only
  - retain existing AI usage tracking
- Fix `PvEngine::presentValue()` so non-numeric list keys use sequential periods, matching `discountedCashFlows()`.
- Update risk-cost modelling to distinguish recurring annual risk from one-off statutory penalties.
- Surface dropped-finding counts:
  - add run-level metadata/counts
  - show advisor-facing warning when findings were dropped for missing attribution
  - keep audit events
- Confirm bias signal handling:
  - if runner-level bias inspection is not entering the governed learning queue, add the correct candidate path or document why the monitor command is the intended route.

Tests:

- Anthropic client uses resilience wrapper and preserves usage event behavior.
- `presentValue([100, 100], rate)` and `discountedCashFlows([100, 100], rate)` agree on periods.
- One-off penalty risk is not repeated across all duration years unless explicitly marked recurring.
- Dropped attribution count appears on the run/readiness payload.
- Bias signal route is covered by either candidate-generation or monitor tests.

## Workstream 6 - AI Analysis Depth

Priority: medium, after blockers.

Problem:

Current module findings are deterministic templates while the AI response text is mostly discarded.

Implementation path:

- Choose one test pilot module first, preferably Financial Analysis.
- Extend the AI response contract to parse structured findings with:
  - lens
  - severity
  - title
  - body
  - attributions
  - confidence/uncertainty
  - benchmark/trend references
- Keep deterministic calculations for factual metrics, but let structured AI output contribute the interpretation once it passes attribution validation.
- Add trend analysis from multiple `FinancialSnapshot` rows.
- Add industry-relative thresholds where reference data exists; otherwise show a missing-benchmark warning.
- If a module cannot safely map AI output yet, disable the paid live AI call for that module and mark it as deterministic until upgraded.

Tests:

- Structured fake AI response becomes persisted findings with attributions.
- Missing attribution is rejected and surfaced, not silently hidden.
- Financial analysis includes multi-period trend when snapshots exist.
- Industry benchmark missing state is explicit.
- AI-disabled deterministic module does not call the live client.

## Workstream 7 - Valuation Methodology

Priority: medium, staged separately because it changes advisor methodology.

Implementation:

- Add recorded method weights or primary-method selection for SDE, EBITDA, and DCF.
- Store rationale for the selected method and weights.
- Add snapshot staleness checks and disclaimers.
- Add explicit EBITDA fallback disclaimer when EBITDA is missing and net profit is used.
- Add enterprise-to-equity bridge fields:
  - debt
  - surplus cash
  - normalised working capital
  - other advisor adjustments
- Add DCF sensitivity table for discount rate and terminal growth.
- Align succession target-exit PV and business valuation terminal-value assumptions, or document why they intentionally differ.

Tests:

- Valuation records method weights and rationale.
- Stale snapshot triggers disclaimer.
- EBITDA fallback triggers disclaimer.
- Equity bridge changes equity value while preserving enterprise value.
- Sensitivity table is generated and source-attributed.
- Succession/valuation comparison explains terminal-value treatment.

## Test Environment Deployment Runbook

Use this once each workstream branch is ready for test.

1. Confirm no production secrets are present in test `.env`.
2. Put the test environment in a known state:
   - record current Git commit
   - back up the test database
   - record pending migrations
3. Deploy branch to test:
   - `git fetch`
   - `git checkout <branch>`
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci`
   - `npm run build`
   - `php artisan migrate --force`
   - `php artisan config:clear`
   - `php artisan route:clear`
   - `php artisan view:clear`
   - `php artisan event:clear || true`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
   - `php artisan event:cache || true`
   - `php artisan queue:restart || true`
4. Run focused automated tests for the workstream.
5. Run full automated verification:
   - `npm run lint:check`
   - `npm run types:check`
   - `npm run build`
   - `php -d memory_limit=2048M vendor/phpunit/phpunit/phpunit --no-coverage`
6. Run smoke tests through the advisor UI.
7. Capture screenshots/PDFs for any client-facing artifact changes.
8. Record defects and either patch the same branch or split follow-up work by severity.

## Recommended Test Order

1. PV lifecycle and ROI stability.
2. Pack readiness gate.
3. Proposal/strategic-plan evidence fixes.
4. Budget calculation/evidence gate.
5. Technical correctness and integrity plumbing.
6. AI analysis depth pilot.
7. Valuation methodology.

## Release Decision Gates

Do not promote beyond test until all are true:

- Re-running analysis/report generation does not inflate active PV, target PV, fee ROI, or proposal ROI.
- Advisory packs cannot be generated from incomplete analysis without explicit, recorded waiver.
- Client-facing proposal/plan artifacts show source attributions for factual findings.
- Budget fixed-cost timing is correct and disputed/unverified documents do not unlock budget readiness.
- Focused and full automated test suites pass or have documented, accepted non-regression failures.
- Test smoke scenarios have been performed with dated evidence.

## Rollback Notes

- Keep lifecycle migrations additive and preserve historical rows. If rollback is needed, application queries can temporarily ignore the new active filter only after confirming duplicate totals will not reach clients.
- Back up test before data migrations that supersede duplicate PV rows.
- For client-facing PDF/template changes, retain generated before/after artifacts for comparison.
- For AI client changes, keep `.env.testing` with empty `ANTHROPIC_API_KEY` and use fake clients in tests.

