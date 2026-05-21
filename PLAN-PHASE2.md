# Future Shift Advisory — Phase 2 Implementation Plan (Intelligence)

**Source spec:** `docs/spec/Future_Shift_Advisory_App_Specification_v2.4.docx` (definitive, May 2026)
**Plan scope:** Phase 2 — Intelligence (Months 3–6) in implementation detail. Phases 3–4 remain forward roadmap.
**Predecessor:** Phase 1 — Foundation (WO-01…WO-30) — **complete** on branch `featureApp`. See `IMPLEMENTATION.md`.
**Work orders:** WO-31 … WO-64 (continues the single global sequence from Phase 1).
**Plan version:** 1.0

> This document does **not** replace `PLAN.md`. `PLAN.md` is the Phase 1 plan and stays as-is. This is the Phase 2 companion. When Phase 2 begins, mirror the §0 process rules below.

---

## 0. How to use this plan

1. Read §1–§7 once before starting. They carry forward the non-negotiable principles and lock the new Phase 2 architecture (the analysis spine, the PV engine, the data feeds, the report composer).
2. Implement **Work Orders** in §8 in numeric order. Dependencies are explicit. Phase 2 has more parallelisable branches than Phase 1 — see the dependency graph in §8.0.
3. Follow the same commit discipline used in Phase 1 (per `IMPLEMENTATION.md`): commit each completed WO directly on `featureApp` with `WO-<id>: <slug>` unless the owner changes the branching rule.
4. Every WO ships with its tests. A WO is not done until acceptance criteria are demonstrably true and `composer test`, `npm run lint:check`, `npm run types:check`, `npm run format:check` all pass.
5. **Do not invent features beyond the spec.** Phase 3/4 features stay out (see §2.2). If you find a gap, raise it in the commit/PR body or add a risk row to §12.
6. Every analysis-bearing WO **must** route through the Phase 2 analysis spine (§7.1) so the AI Integrity Principle, Document Verification gate, and Data Quality gate are enforced uniformly — never re-implemented per module.
7. Update `IMPLEMENTATION.md` after each WO (snapshot count, commit log, per-WO detail, verification results), exactly as Phase 1 did.

---

## 1. Non-negotiable principles (carried forward — still binding)

Everything in `CLAUDE.md` and `PLAN.md` §1 remains in force. Phase 2 turns these from *foundations* into *behaviours*, so they get **more** load-bearing, not less:

- **AI Integrity Principle (spec §3).** Every analysis finding, score, fee suggestion, report sentence, and red-flag must be honest, evidence-based (source-attributed), accurate (NZ-current), free from bias, truthful. Phase 1 built the enforcement primitives (`SourceAttribution`, `BiasDetector`, `Uncertainty`, the `AiClient` single exit). Phase 2 wires them into every output. **Missing attribution remains a hard failure**, now across dozens of analysis surfaces.
- **Document AI Verification (spec §9).** Every analysis that relies on uploaded documents consults the existing `DocumentVerificationGate`. **Accuracy discrepancies pause the affected analysis** — Phase 2 is the first phase where there are real analyses to pause. Findings carry their document-support status in every report.
- **Data Quality gate (spec §10 intro).** `DataQualityScorer` runs before every analysis. Medium/Low data attaches a disclaimer to every affected finding and report line; Insufficient blocks the run.
- **No silent learning (spec §23).** Phase 2 activates the four Phase-2-tagged learning layers, but only through the governed `learning_updates` queue. No model or framework changes its own behaviour without a queued, owner-approvable update. The approval **UI** is still Phase 3; Phase 2 only *produces* candidates and runs the scheduled cadence.
- **Security baseline (spec §4).** All external calls (RBNZ, Stats NZ, MBIE, Xero, MYOB, QuickBooks, …) go through `ResilientHttp`. All AI calls through `AiClient`. All audit through `AuditWriter`. All encryption through `KeyEnvelope`. PQC/HSM remain Phase 4 (SD-01/SD-02).
- **Liability disclaimer (spec §16 intro).** Report-grade outputs carry the standard disclaimer (the registry primitive exists from Phase 1).

---

## 2. Phase 2 goal & scope

### 2.1 Goal (Months 3–6)

Turn the Phase 1 data-capture foundation into a working **intelligence platform**: the advisor can run real analyses on a client, see PV-quantified findings with cited evidence and document-verification status, get AI red-flag alerts, generate a fee proposal with an ROI ratio, and produce Client / Advisor / Stakeholder reports — all governed by the AI Integrity Principle and fed by live NZ economic data and connected accounting systems.

Concretely, by end of Phase 2:

- Every analysis module produces governed `analysis_runs` whose findings carry attribution, document-verification status, data-quality disclaimers, and an analytical-framework lens (descriptive/diagnostic/predictive/prescriptive).
- The PV engine computes business valuation (3 methods), improvement-opportunity PV, and risk-cost PV, under any of the 4 discount-rate methods, and renders the waterfall chart on the client dashboard, in reports, and in fee proposals.
- Financial analysis pulls from connected Xero/MYOB/QuickBooks and runs continuous health monitoring with early-warning alerts.
- Fee calculator + proposal generation + release control + expiry work end-to-end (sign-off and payment remain Phase 3).
- Client, Advisor, and Stakeholder reports generate as branded PDFs (Stakeholder also as PowerPoint). DD and Entrepreneur-Assessment report **types** are scaffolded in the engine but filled by Phase 3.
- Live NZ economic indicators (OCR, CPI, min/living wage, unemployment, GDP) feed the dashboard, analysis, and PV.
- Bias detection, feedback capture, AI red-flag alerts, client knowledge assessment, scenario planning, succession planning, regulatory compliance checker, regulatory impact, and insurance risk flags are live.
- The four Phase-2 learning layers run on their scheduled cadence, producing governed `learning_updates` candidates only.

### 2.2 Out of scope (Phase 3–4 — do not build)

- **Digital proposal sign-off flow (7-step), payment authority, Stripe/Windcave, monthly payment processing, receipts** — Phase 3. (Phase 2 stops at proposal *generation + release + expiry*.)
- **Due Diligence module, virtual data room, 8 workstreams, DD report, post-acquisition pipeline** — Phase 3. (Phase 2 report engine scaffolds the DD report *type* only.)
- **Broker portal, Coach portal, panel agreements, referral workflows, coaching signal detection** — Phase 3.
- **Entrepreneur module beyond Phase 1 profile** (readiness, idea validation, plan builder, rating framework, assessment, conversion) — Phase 3. (Phase 2 report engine scaffolds the Entrepreneur-Assessment report *type* only.)
- **Goals & milestones tracker, proof-of-completion** — Phase 3. (So PV-into-milestones integration waits; Phase 2 integrates PV into dashboard, reports, proposals only.)
- **Learning update queue admin UI, rollback UI, T&C version manager UI** — Phase 3. (Phase 2 produces queue candidates; no approval UI.)
- **Cross-client intelligence, anonymous benchmarking community, shared intelligence layer, peer network, predictive validity testing, conversion-outcome learning** — Phase 4.
- **Voice assistant, mobile app, advisor API layer, multi-advisor scaling, PQC/HSM** — Phase 4.
- **Testimonial capture, voice-to-text, bulk communications, document expiry tracking, offline PWA** — Phase 3.

> NZ-benchmarking in Phase 2 means *industry benchmark reference data* (MBIE/NZ Business Brokers multiples, wage rates), **not** the anonymous cross-client benchmarking community (Phase 4). Keep the distinction sharp.

---

## 3. What Phase 2 builds on (Phase 1 inventory)

Phase 2 reuses these Phase 1 building blocks directly. Do not re-create them.

| Phase 1 asset | Location | Phase 2 use |
|---|---|---|
| `AiClient` (analyse / verifyDocument / scoreCriterion / summarise / redFlag) | `app/Services/Ai/Contracts/AiClient.php` | Every analysis module calls `analyse()`; red-flag module calls `redFlag()` |
| `PromptEnvelope` (carries `input`, `dataQualitySummary`, `sourceReferences`, integrity preamble) | `app/Services/Ai/Contracts/PromptEnvelope.php` | The analysis spine builds one envelope per run |
| `AiResponse` (text, attributions, uncertainty, biasSignals, model, prompt hash) | `app/Services/Ai/Contracts/AiResponse.php` | Findings persist from this; attribution enforced |
| `SourceAttribution`, `BiasDetector`, `IntegrityPreamble`, `PromptRegistry` | `app/Services/Ai/Integrity`, `…/Prompts` | Bias layer (WO-33) extends `BiasDetector`; every module registers prompts |
| `FakeAiClient` | `app/Services/Ai/Fake` | Deterministic test path for every analysis WO |
| `DocumentVerificationGate`, `DocumentVerifier`, `DocumentVerification` | `app/Services/Documents`, `app/Services/Ai/Verification` | Analysis spine calls `ensureClear()` before rendering |
| `DataQualityScorer`, `DataQualityScore`, `DataQualitySignal` | `app/Services/DataQuality` | Gate before every run; disclaimer source |
| `ResilientHttp`, `RetryPolicy`, `CircuitBreaker`, `HealthRecorder` | `app/Services/Integration/Resilience` | Every live data feed (econ, accounting) |
| Integration scaffolds: Rbnz, StatsNz, Mbie, Xero, Myob, QuickBooks, … | `app/Services/Integration/*` | WO-36/37 fill these contracts in |
| `IntegrationServiceProvider`, `FEATURE_*_LIVE` flags | `app/Providers`, `.env` | New `FEATURE_*` flags follow the same pattern |
| `PdfRenderer` + `BrowsershotRenderer` | `app/Services/Pdf` | Report engine (WO-57+) |
| `AuditWriter`, `WritesAudit` | `app/Services/Audit` | Every analysis run, fee proposal, report generation audited |
| `learning_updates`, `learning_update_implementations` | tables (WO-04) | Phase 2 learning layers write candidates here |
| `ChannelResolver`, `ChannelAwareNotification`, digests | `app/Services/Notifications` | Red-flag alerts, monitoring alerts, briefings route through here |
| `Client`, `Document`, `Questionnaire*`, `EngagementType` | `app/Models`, `app/Enums` | Analysis inputs |
| RLS harness (`EnforceClientScope`, `fsa_*` functions, `FORCE ROW LEVEL SECURITY`) | WO-02 | Every new client-scoped table gets a policy + RLS test |

**Stack additions for Phase 2** (all to be added via their WO):
- `PptxGenerator` interface + a `PhpPptxRenderer` (PowerPoint export for Stakeholder report) — spec §27 (PptxGenJS/python-pptx). Use a PHP library or a Node sidecar via Browsershot-style invocation; decide in WO-58.
- Charting for the PV waterfall and trend charts — render server-side into the PDF (via the existing Browsershot HTML→PDF path) and client-side in React (a charting lib, e.g. Recharts) for the dashboard.

---

## 4. New database schema (Phase 2)

Grouped by WO. Same conventions as Phase 1: `uuid` PKs via `gen_random_uuid()`, `jsonb` columns, `client_id` + RLS policy on every client-scoped table, audit through `AuditWriter`.

### 4.1 Analysis spine (WO-31/32/34)
- `analysis_runs` — `client_id`, `module` (enum: financial, website_audit, competitor, swot, hr, operational, systems, compliance, regulatory_impact, insurance_risk, knowledge_assessment, scenario, succession), `status` (queued, running, blocked_documents, blocked_data_quality, completed, failed), `framework_lenses` (jsonb: which of descriptive/diagnostic/predictive/prescriptive produced output), `data_quality_snapshot` (jsonb), `ai_model`, `prompt_version`, `prompt_hash`, `tokens_in`, `tokens_out`, `started_at`, `completed_at`, `created_by_user_id`
- `analysis_findings` — `analysis_run_id`, `client_id`, `lens` (descriptive|diagnostic|predictive|prescriptive), `severity` (info, low, medium, high, critical), `title`, `body`, `attributions` (jsonb — claim/source_reference pairs), `document_support` (enum: verified, advisory_flag, accuracy_discrepancy, none), `uncertainty` (high|medium|low|none), `data_quality_disclaimer` (text|null), `bias_signals` (jsonb), `pv_link_id` (nullable → improvement/risk PV row)
- `analysis_feedback` — `analysis_finding_id`, `advisor_user_id`, `decision` (confirm, correct, rate, add_context), `rating` (nullable int), `corrected_body` (nullable), `note`, `created_at` — feeds the learning queue (WO-32)
- `red_flags` — `client_id`, `analysis_finding_id` (nullable — some flags are monitor-derived), `category` (financial, compliance, key_person, insurance, viability, regulatory), `severity`, `headline`, `detail`, `surfaced_at`, `acknowledged_at`, `acknowledged_by_user_id`, `resolved_at`

### 4.2 Economic & market data (WO-36/39)
- `economic_indicators` — `indicator` (ocr, cpi, minimum_wage, living_wage, unemployment, gdp), `value` (numeric), `unit`, `effective_date`, `source`, `source_url`, `fetched_at`, `superseded_at` (nullable). Append-style; latest per indicator is `superseded_at IS NULL`.
- `exchange_rates` — `base` (NZD), `quote`, `rate`, `as_at`, `source` (RBNZ), `fetched_at` — DD foreign-currency use is Phase 3, but the feed lands here.
- `valuation_multiples` — `industry_code`, `metric` (ebitda, sde), `multiple_low`, `multiple_mid`, `multiple_high`, `source` (mbie, nz_business_brokers), `quarter`, `fetched_at`, `superseded_at` — quarterly refresh via learning queue.

### 4.3 Accounting connections (WO-37/38)
- `accounting_connections` — `client_id`, `provider` (xero, myob, quickbooks), `status` (connected, error, revoked), `external_org_id`, `token_envelope` (KeyEnvelope), `scopes` (jsonb), `connected_by_user_id`, `last_synced_at`, `last_error` (jsonb)
- `financial_snapshots` — `client_id`, `accounting_connection_id`, `period_start`, `period_end`, `pulled_at`, `pl` (jsonb), `balance_sheet` (jsonb), `cash_flow` (jsonb), `source_hash` — immutable per pull; continuous-monitoring compares consecutive snapshots.
- `financial_alerts` — `client_id`, `snapshot_id`, `metric`, `direction`, `magnitude`, `citation` (jsonb: which figures), `severity`, `surfaced_at`, `acknowledged_at`

### 4.4 PV module (WO-40/41/42/43)
- `pv_calculations` — `client_id`, `type` (business_valuation, improvement_opportunity, risk_cost), `discount_method` (ocr_linked, industry_wacc, advisor_configured, client_inputted), `discount_rate`, `discount_rate_rationale`, `inputs` (jsonb), `result` (jsonb), `as_at`, `created_by_user_id`, `source_attributions` (jsonb)
- `business_valuations` — `pv_calculation_id`, `sde_value`, `ebitda_value`, `dcf_value`, `reconciled_low`, `reconciled_mid`, `reconciled_high`, `adjustments` (jsonb)
- `improvement_opportunities` — `client_id`, `analysis_finding_id` (nullable), `pv_calculation_id`, `annual_benefit`, `duration_years`, `pv_of_impact`, `rank`
- `risk_costs` — `client_id`, `analysis_finding_id` (nullable), `pv_calculation_id`, `financial_impact`, `probability`, `duration_years`, `statutory_penalty_range` (jsonb), `pv_of_cost`, `rank`

### 4.5 Fee & proposal (WO-55/56)
- `fee_calculations` — `client_id`, `method` (hours_based, outcome_based, entrepreneur), `inputs` (jsonb), `suggested_low`, `suggested_mid`, `suggested_high`, `improvement_pv_total`, `risk_cost_pv_total`, `roi_ratio`, `justification` (jsonb — PV-referenced), `created_by_user_id`
- `proposals` — `client_id`, `fee_calculation_id`, `status` (draft, released, awaiting_signature, signed, expired, renewed), `scope` (jsonb), `services` (jsonb), `pv_summary` (jsonb), `roi_ratio`, `released_at`, `expires_at`, `recalled_at`, `released_by_user_id`, `pdf_path` — *signing/payment fields are Phase 3*; `awaiting_signature`/`signed` states are reachable only when Phase 3 lands.
- `consents` — generic consent ledger (`user_id`, `client_id`, `consent_type` insurance_referral|coach_referral|marketing, `granted`, `granted_at`, `revoked_at`, `source`). Created here because the proposal references insurance/coach consent election (spec §13), even though broker/coach portals are Phase 3.

### 4.6 Reporting (WO-57/58/59/60)
- `reports` — `client_id`, `type` (client, advisor, stakeholder, due_diligence, entrepreneur_assessment), `status` (draft, generated, shared), `format` (pdf, pptx), `pv_snapshot` (jsonb), `findings_snapshot` (jsonb), `disclaimers` (jsonb), `generated_pdf_path`, `generated_pptx_path`, `generated_by_user_id`, `generated_at` — DD + entrepreneur_assessment types are engine-scaffolded only in Phase 2.
- `report_sections` — `report_id`, `order`, `heading`, `body`, `attributions` (jsonb), `document_support_note`, `data_quality_note`
- `industry_briefings` — `client_id`, `period` (month), `body`, `sources` (jsonb), `status` (draft, advisor_reviewed, sent), `reviewed_by_user_id`, `sent_at`
- `pre_meeting_briefs` — `client_id`, `meeting_at`, `body`, `red_flag_ids` (jsonb), `generated_at`, `reviewed_by_user_id`

### 4.7 Knowledge assessment & analytics (WO-35/61/62)
- `knowledge_assessments` — `client_id`, `financial_literacy` (1–5), `strategic_awareness` (1–5), `leadership` (1–5), `calibration` (jsonb — derived language/depth settings), `assessed_at`, `assessed_by_user_id`
- `funnel_events` — `flow` (onboarding, questionnaire, proposal, …), `step`, `client_id` (nullable), `user_id` (nullable), `entered_at`, `completed_at`, `abandoned` (bool) — drives drop-off analytics.
- Practice-health is computed on the fly from existing tables + PV; no new table required beyond a cached `practice_health_snapshots` (`generated_at`, `metrics` jsonb) for the monthly view.

### 4.8 Learning-layer scheduling (WO-32/36/50/63)
- `learning_layer_runs` — `layer_id` (matches spec §23 layer numbering), `ran_at`, `candidates_created`, `window` (jsonb), `status` — observability for the scheduled cadence; the candidates themselves are `learning_updates` rows from Phase 1.

---

## 5. Configuration & feature flags (Phase 2 additions)

Follow the Phase 1 pattern (`FEATURE_*_LIVE` default off; resolver falls back to stub/fixture).

```
# Economic & market data (WO-36/39)
FEATURE_RBNZ_LIVE=false
FEATURE_STATS_NZ_LIVE=false
FEATURE_MBIE_LIVE=false

# Accounting (WO-37/38)
FEATURE_XERO_LIVE=false
FEATURE_MYOB_LIVE=false
FEATURE_QUICKBOOKS_LIVE=false
XERO_CLIENT_ID=
XERO_CLIENT_SECRET=
MYOB_CLIENT_ID=
MYOB_CLIENT_SECRET=
QUICKBOOKS_CLIENT_ID=
QUICKBOOKS_CLIENT_SECRET=

# Analysis engine
FEATURE_CONTINUOUS_MONITORING=false   # daily/weekly accounting pull scheduler
ANALYSIS_DEFAULT_FRAMEWORK_LENSES=descriptive,diagnostic,predictive,prescriptive

# PV defaults
PV_DEFAULT_DISCOUNT_METHOD=ocr_linked
PV_OCR_RISK_PREMIUM=0.05              # default risk premium over OCR (advisor-overridable)

# Reporting
FEATURE_PPTX_EXPORT=true
```

When `ANTHROPIC_API_KEY` is empty (Phase 1 behaviour), analysis modules still run but every finding is degraded (`uncertainty=high`, `text="AI unavailable — analysis deferred"`) and an advisor notice is raised — identical to the Phase 1 `FakeAiClient` degraded path. No analysis silently fabricates content.

---

## 6. Cross-cutting dependency map

```
WO-31 Analysis spine ─┬─> WO-44 Financial ──> WO-38 Continuous monitoring
                      ├─> WO-45 Website audit
                      ├─> WO-46 Competitor
                      ├─> WO-47 SWOT/TOWS/MAPS
                      ├─> WO-48 HR & people
                      ├─> WO-49 Operational/systems
                      ├─> WO-50 Compliance checker ──> WO-51 Regulatory impact
                      ├─> WO-52 Insurance risk flags
                      ├─> WO-53 Scenario planning
                      └─> WO-54 Succession planning
WO-31 ──> WO-32 Feedback capture ──> (learning queue)
WO-31 ──> WO-33 Bias detection (full)
WO-31 ──> WO-34 Red-flag alerts
WO-31 ──> WO-35 Knowledge assessment (calibrates prompts)

WO-36 Economic feeds ──> WO-40 PV engine + discount methods
WO-37 Accounting ──────> WO-44 Financial, WO-38 Monitoring
WO-39 Valuation multiples ─> WO-41 Business valuation PV
WO-40 ──> WO-41 Business valuation ─┐
WO-40 ──> WO-42 Improvement/Risk PV ─┴─> WO-43 PV integration + waterfall

WO-43 PV + WO-44 Financial ──> WO-55 Fee calculator ──> WO-56 Proposal
(analyses + PV) ──> WO-57 Report engine ──> WO-58 Stakeholder ──> WO-59 Trajectory
WO-57 ──> WO-60 Briefings + pre-meeting brief
(flows) ──> WO-61 Funnel analytics ─┐
WO-43 + proposals ──> WO-62 Practice health ─┴─> WO-63 Advisor dashboard Phase 2 panels
WO-20 (Phase 1) ──> WO-64 Wellbeing monthly pulse + analytics
```

Parallelisable once WO-31 + WO-36 + WO-37 land: the analysis modules (WO-44…54) and the PV chain (WO-40…43) can proceed largely in parallel.

---

## 7. Cross-cutting foundations (build first, in WO-31)

### 7.1 The analysis spine — the integrity-bearing primitive

Every Phase 2 analysis module is a thin adapter over a shared pipeline. The module supplies: (a) which client, (b) which inputs to gather (questionnaire answers, documents, financial snapshots, economic context), (c) which prompt(s) to register, (d) how to map the AI output into findings. The spine owns the integrity guarantees so no module can skip them.

```
AnalysisRunner::run(client, module, options)
  1. DataQualityScorer->score(client)           // gate: Insufficient => abort with reason
  2. DocumentVerificationGate->ensureClear(client) // pause if accuracy_discrepancy outstanding
  3. Gather inputs (questionnaire, docs, financial_snapshots, economic_indicators)
  4. Build PromptEnvelope (input + dataQualitySummary + sourceReferences + integrity preamble)
  5. AiClient->analyse(envelope)                // single sanctioned AI exit
  6. SourceAttribution->assertPresent(response) // hard fail if a factual claim lacks a source
  7. BiasDetector->inspect(response)            // attach bias signals; queue candidate if systematic
  8. Map response -> analysis_findings (lens, severity, attribution, document_support,
                     uncertainty, data_quality_disclaimer)
  9. Persist analysis_run + findings; audit 'analysis.completed'
 10. Promote critical findings -> red_flags (WO-34)
```

Key rules baked into the spine:
- **Four analytical lenses (spec §11).** Descriptive, Diagnostic, Predictive, Prescriptive are applied to every output. The prompt asks for all four; findings are tagged by lens.
- **No finding without attribution.** Step 6 is non-negotiable. A finding whose source_reference is empty is dropped and logged as an `AiIntegrityViolation`, never shown.
- **Document support on every finding.** Step 8 stamps each finding with its document-verification status so reports can render the notation (spec §19).
- **Data-quality disclaimer.** Medium/Low attaches the disclaimer text to each finding; reports surface it.
- **Pause, never suppress.** If `DocumentVerificationGate` reports an outstanding accuracy discrepancy for the client, the run returns `blocked_documents` and the advisor is notified — output is not rendered.

### 7.2 Analytical framework helper
A small value object enumerates the four lenses and is used by prompt construction and finding tagging. Reports group findings by lens where useful.

### 7.3 Feedback → learning loop (WO-32)
Every finding can be confirmed / corrected / rated / annotated by an advisor (spec §11 "Feedback Capture"). Each feedback row may produce a `learning_updates` candidate (e.g. systematic advisor corrections of a module's wording). **No automatic change** — the candidate sits in the governed queue.

### 7.4 PV engine as a shared service (WO-40)
`PvEngine` is consumed by analysis modules (improvement/risk PV per finding), the dashboard (business PV baseline + target), reports (waterfall), and the fee calculator (ROI ratio). Discount-rate resolution is centralised: `DiscountRateResolver` returns a rate + rationale for a given method, pulling OCR from `economic_indicators` for the default method. Every PV result records its `source_attributions` so the AI Integrity Principle extends to numbers, not just prose.

### 7.5 Report composer (WO-57)
A single `ReportComposer` takes a set of `analysis_findings` + PV snapshot + disclaimers and renders `report_sections`. Each section automatically carries: AI source attribution, document-support note, data-quality note. The five report *types* differ only in which findings/sections they include and what they redact (e.g. Client report excludes recommendations + fee detail; Stakeholder excludes FSA methodology/IP). This guarantees the integrity notations appear consistently across all reports (spec §19).

### 7.6 Economic & accounting feeds (WO-36/37)
Both follow the Phase 1 integration pattern exactly: fill the existing `Rbnz/StatsNz/Mbie/Xero/Myob/QuickBooks` contracts, gate with `FEATURE_*_LIVE`, wrap in `ResilientHttp`, record `integration_calls`, expose health via the existing dashboard. When live flags are off, fixtures provide deterministic data so analysis and PV are testable without credentials.

---

## 8. Work Orders (Phase 2)

> Format per WO: **ID** · **Title** · spec refs · goal · depends-on · key files · acceptance criteria · tests · out-of-scope.

### 8.0 Ordering
Strict prerequisites: **WO-31 first** (the spine), then **WO-36 + WO-37** (data feeds), then PV chain (WO-40→43) and analysis modules (WO-44→54) in parallel, then commerce (WO-55→56), reporting (WO-57→60), and practice management (WO-61→64). WO-32/33/34/35 attach to the spine and can follow WO-31 immediately.

---

### WO-31 — Analysis spine (runs, findings, integrity pipeline)
**Spec refs:** §10 intro; §11
**Goal:** Build the shared `AnalysisRunner` pipeline (§7.1), the `analysis_runs` / `analysis_findings` / `analysis_feedback` tables, the four-lens framework helper, and the integrity guarantees every module inherits.
**Depends on:** Phase 1 (WO-04, WO-18, WO-19).
**Key files:** `app/Services/Analysis/AnalysisRunner.php`, `app/Services/Analysis/AnalyticalFramework.php`, `app/Services/Analysis/Contracts/AnalysisModule.php`, `app/Models/AnalysisRun.php`, `AnalysisFinding.php`, `AnalysisFeedback.php`, migrations + RLS policies, `app/Enums/AnalysisModule.php`, `AnalysisLens.php`, `FindingSeverity.php`.
**Acceptance:** A demo module run produces findings each carrying a non-empty attribution, a document-support status, an uncertainty, and (when data is Medium/Low) a disclaimer; a run is `blocked_documents` when an accuracy discrepancy is outstanding; a run is aborted on Insufficient data quality; every run is audited.
**Tests:** spine pipeline with `FakeAiClient`; integrity-violation drop path; doc-verification block; data-quality abort; RLS isolation on `analysis_runs`/`analysis_findings`.
**Out of scope:** the real modules (their own WOs).

### WO-32 — AI feedback capture loop
**Spec refs:** §11 (Feedback Capture); §23 layer "AI feedback capture on every analysis output"
**Goal:** Advisor confirm/correct/rate/add-context on every finding; persist `analysis_feedback`; emit governed `learning_updates` candidates on systematic patterns. Activate the feedback learning layer cadence.
**Depends on:** WO-31.
**Key files:** `app/Services/Analysis/FeedbackRecorder.php`, controller + Inertia UI on finding cards, `learning_layer_runs` write, `app/Console/Commands/RunFeedbackLearningLayer.php`.
**Acceptance:** feedback persists and is audited; a threshold of corrections to one module produces exactly one `learning_updates` candidate in `detected` status (never auto-applied); the layer run is recorded.
**Tests:** feedback persistence; candidate creation threshold; no auto-implementation.
**Out of scope:** learning approval UI (Phase 3).

### WO-33 — Bias detection layer (full)
**Spec refs:** §3 (Free from Bias); §11 (Bias Detection Layer); §23
**Goal:** Extend the Phase 1 heuristic `BiasDetector` into a monitoring layer that examines outputs across runs for systematic demographic/industry/business-type patterns, logs signals, and queues governed candidates. SIEM-style alerting on detected systematic bias.
**Depends on:** WO-31.
**Key files:** `app/Services/Ai/Integrity/BiasMonitor.php`, scheduled aggregation command, `learning_updates` candidate emission, advisor/super-admin notification on systematic signal.
**Acceptance:** per-output signals recorded for every analysis; a synthetic systematic skew (e.g. consistently higher scores for one industry) produces a queued candidate + an alert; nothing is auto-corrected.
**Tests:** per-output logging; systematic-skew detection with seeded data; no silent correction.
**Out of scope:** automated calibration from accumulated data (Phase 4).

### WO-34 — AI red-flag alerts
**Spec refs:** §10 (AI Red Flag Alerts); §22 (Red Flag Alerts panel)
**Goal:** Promote critical findings (and monitor-derived signals) into `red_flags`; surface on the advisor dashboard; urgent notification via `ChannelResolver`. Continuous — re-evaluated as new data arrives.
**Depends on:** WO-31, Phase 1 WO-12.
**Key files:** `app/Services/Analysis/RedFlagPromoter.php`, `app/Models/RedFlag.php`, dashboard panel, urgent `ChannelAwareNotification`.
**Acceptance:** a critical finding creates a red flag and an urgent notification that bypasses channel preference; acknowledge/resolve flow audited; no duplicate flag spam for the same underlying finding.
**Tests:** promotion, urgency bypass, dedupe, acknowledge/resolve.
**Out of scope:** cross-client pattern alerts (Phase 4).

### WO-35 — Client knowledge assessment
**Spec refs:** §10 (Client Knowledge Assessment)
**Goal:** Capture financial-literacy / strategic-awareness / leadership scores; derive a calibration that adjusts AI output language and depth (injected into `PromptEnvelope`).
**Depends on:** WO-31.
**Key files:** `knowledge_assessments` table + model, `app/Services/Analysis/KnowledgeCalibration.php` (feeds prompt construction), advisor UI.
**Acceptance:** an assessment changes the calibration block passed into subsequent runs' envelopes (verifiable in the prompt hash/inputs); leadership-gap surfaces a coaching signal record (consumed by Phase 3 coach referral).
**Tests:** calibration injection; coaching-signal emission.
**Out of scope:** coach referral workflow (Phase 3).

### WO-36 — NZ economic indicators feed
**Spec refs:** §24; §11 (Legislative/economic currency); §23 layer "Economic indicator auto-update"
**Goal:** Fill the `Rbnz` + `StatsNz` + `Mbie` contracts (OCR, exchange rates, CPI, GDP, unemployment, minimum wage, living wage). Persist to `economic_indicators` / `exchange_rates`. Scheduled refresh writes governed candidates for material changes; dashboard tiles show live values with change alerts.
**Depends on:** Phase 1 WO-05, WO-13.
**Key files:** live clients in `app/Services/Integration/{Rbnz,StatsNz,Mbie}`, `economic_indicators`/`exchange_rates` tables + models, `app/Console/Commands/RefreshEconomicIndicators.php`, dashboard tiles.
**Acceptance:** fixtures yield deterministic indicators with `FEATURE_*_LIVE=false`; live mode without creds degrades gracefully via the resilience layer (cached + badge); a refresh records the source + timestamp; OCR change queues a PV-discount-rate candidate (no auto-apply).
**Tests:** fixture feed; resilience fallback; refresh idempotency; OCR-change candidate.
**Out of scope:** full WACC automation (Phase 4).

### WO-37 — Accounting API integration (Xero / MYOB / QuickBooks)
**Spec refs:** §25 (Accounting API-first); §11
**Goal:** OAuth connect flow per provider; fill the contracts; pull P&L / balance sheet / cash flow into immutable `financial_snapshots`. Tokens stored via `KeyEnvelope`.
**Depends on:** Phase 1 WO-05, WO-06 (KeyEnvelope), WO-14 (clients).
**Key files:** live clients in `app/Services/Integration/{Xero,Myob,QuickBooks}`, `accounting_connections` + `financial_snapshots` tables/models, connect/callback controllers, `app/Services/Accounting/FinancialSnapshotPuller.php`.
**Acceptance:** connect flow stores an encrypted token; a pull creates an immutable snapshot stamped with source; revoke marks the connection revoked; all calls go through `ResilientHttp` and are logged; fixtures back tests.
**Tests:** connect/callback (mocked OAuth), snapshot pull from fixture, token never stored in plaintext, revoke.
**Out of scope:** continuous scheduling (WO-38); analysis (WO-44).

### WO-38 — Continuous financial health monitoring
**Spec refs:** §10 (Continuous Financial Health Monitoring); §11
**Goal:** Scheduled daily/weekly pulls from connected accounting; compare consecutive snapshots; raise early-warning `financial_alerts` with specific figure citations.
**Depends on:** WO-37.
**Key files:** `app/Console/Commands/RunFinancialMonitoring.php`, `app/Services/Accounting/HealthMonitor.php`, `financial_alerts` table/model, alert notification.
**Acceptance:** a seeded deterioration between snapshots raises an alert citing the exact metrics; alert routes through `ChannelResolver`; `FEATURE_CONTINUOUS_MONITORING` gates the scheduler.
**Tests:** deterioration detection + citation; no false alert on stable data; channel routing.
**Out of scope:** the financial analysis narrative (WO-44).

### WO-39 — Valuation multiple data feed
**Spec refs:** §12 (NZ-benchmarked multiples); §24 (NZ Transaction Data); §23 layer "Valuation multiple refresh"
**Goal:** Persist EBITDA/SDE multiples by industry from MBIE / NZ Business Brokers reference data into `valuation_multiples`; quarterly refresh produces governed candidates.
**Depends on:** WO-36.
**Key files:** `valuation_multiples` table/model, `app/Services/Pv/ValuationMultipleProvider.php`, quarterly refresh command.
**Acceptance:** lookup returns low/mid/high by industry; quarterly refresh supersedes prior rows and queues a candidate; fixtures back tests.
**Tests:** lookup, supersession, candidate emission.
**Out of scope:** the valuation calc (WO-41).

### WO-40 — PV engine + discount-rate methods
**Spec refs:** §12 (4 discount methods)
**Goal:** `PvEngine` (discounting math) + `DiscountRateResolver` for all four methods: OCR-linked (default, auto from `economic_indicators` + risk premium), industry WACC, advisor-configured (rate + rationale recorded), client-inputted. Every PV records source attributions.
**Depends on:** WO-36.
**Key files:** `app/Services/Pv/PvEngine.php`, `DiscountRateResolver.php`, `pv_calculations` table/model, `app/Enums/PvType.php`, `DiscountMethod.php`.
**Acceptance:** each method returns a rate + rationale; OCR-linked auto-updates when the OCR indicator changes; PV math verified against known fixtures; results carry attributions.
**Tests:** four methods; OCR auto-update; discounting correctness; attribution presence.
**Out of scope:** the three PV *types* (WO-41/42).

### WO-41 — PV Type 1: Business valuation
**Spec refs:** §12 (Type 1)
**Goal:** SDE multiple, EBITDA multiple (from WO-39), DCF (3–7yr + terminal value); reconciled recommended range; auto-applied valuation adjustments; all three side-by-side.
**Depends on:** WO-40, WO-39.
**Key files:** `app/Services/Pv/BusinessValuation.php`, `business_valuations` table/model.
**Acceptance:** three methods compute and reconcile into a low/mid/high range; adjustments applied and recorded; uses connected financials when available, questionnaire data otherwise (with data-quality disclaimer).
**Tests:** three-method calc; reconciliation; adjustment application; disclaimer on low data.
**Out of scope:** DD valuation workstream (Phase 3 reuses this engine).

### WO-42 — PV Type 2 & 3: Improvement opportunity + risk cost
**Spec refs:** §12 (Type 2, Type 3)
**Goal:** Improvement-opportunity PV (annual benefit × duration discounted, ranked) and risk-cost PV (impact × probability × duration discounted, with NZ statutory penalty ranges, ranked).
**Depends on:** WO-40.
**Key files:** `app/Services/Pv/ImprovementPv.php`, `RiskCostPv.php`, `improvement_opportunities` + `risk_costs` tables/models; link to `analysis_findings`.
**Acceptance:** improvement opportunities ranked by PV of impact; risk costs ranked by PV of cost with statutory ranges applied; both link back to originating findings.
**Tests:** ranking, statutory range application, finding linkage.
**Out of scope:** none.

### WO-43 — PV integration + waterfall chart
**Spec refs:** §12.1
**Goal:** Surface PV everywhere it belongs in Phase 2: client dashboard (current PV baseline, target PV north-star), advisor report (full waterfall: current → improvements → target), progress views. Build the waterfall chart (server-side for PDF, client-side for dashboard).
**Depends on:** WO-41, WO-42.
**Key files:** dashboard PV widgets, `resources/js/components/pv/WaterfallChart.tsx`, server-side chart for reports.
**Acceptance:** dashboard shows baseline + target PV; waterfall renders in the advisor report and on the dashboard; numbers reconcile with the PV tables.
**Tests:** dashboard data assembly; waterfall data correctness.
**Out of scope:** PV-into-milestones (Phase 3 goals/milestones tracker).

### WO-44 — Financial analysis module
**Spec refs:** §10 (Financial Analysis)
**Goal:** Profitability, cash flow, drivers, ratios, root-cause — NZ-benchmarked, economic-overlay — as an analysis module on the spine, consuming `financial_snapshots` (or questionnaire data with disclaimer) and `economic_indicators`.
**Depends on:** WO-31, WO-37, WO-36.
**Key files:** `app/Services/Analysis/Modules/FinancialAnalysis.php`, prompt registration.
**Acceptance:** produces findings across all four lenses with attribution + document-support; flags drivers; references economic context; improvement opportunities feed WO-42.
**Tests:** module run with fixtures + `FakeAiClient`; disclaimer when no accounting connection; finding→PV linkage.
**Out of scope:** continuous monitoring (WO-38).

### WO-45 — Website audit module
**Spec refs:** §10 (Website Audit)
**Goal:** SEO, content, UX, CTAs, mobile performance, NZ search ranking — analysis module on the spine.
**Depends on:** WO-31.
**Key files:** `app/Services/Analysis/Modules/WebsiteAudit.php`.
**Acceptance:** findings with attribution; NZ-ranking context; runs on the spine with all gates.
**Tests:** module run; gate enforcement.
**Out of scope:** ongoing automated monitoring (Phase 4 cross-client alerts).

### WO-46 — Competitor analysis module
**Spec refs:** §10 (Competitor Analysis)
**Goal:** Up to 6 competitors; product/pricing/visibility/gap analysis.
**Depends on:** WO-31.
**Key files:** `app/Services/Analysis/Modules/CompetitorAnalysis.php`, competitor input capture.
**Acceptance:** handles up to 6 competitors; gap findings with attribution.
**Tests:** module run; 6-competitor bound.
**Out of scope:** ongoing monitoring + cross-client industry alerts (Phase 4).

### WO-47 — SWOT / TOWS / MAPS module
**Spec refs:** §10 (SWOT/TOWS/MAPS)
**Goal:** AI-generated matrices from all client data; visual matrices; referenced by PV.
**Depends on:** WO-31, WO-43.
**Key files:** `app/Services/Analysis/Modules/StrategicMatrices.php`, matrix React components.
**Acceptance:** matrices generated with attribution; PV-referenced where relevant.
**Tests:** module run; matrix assembly.
**Out of scope:** none.

### WO-48 — HR & people analysis
**Spec refs:** §10 (HR and People)
**Goal:** CV/JD analysis, staff structure, NZ compliance, wage benchmarking, **Holidays Act liability quantification** — with document verification on uploaded HR docs.
**Depends on:** WO-31, WO-36 (wage rates).
**Key files:** `app/Services/Analysis/Modules/HrAnalysis.php`, Holidays Act liability calculator.
**Acceptance:** wage compliance checked against current minimum/living wage; Holidays Act liability quantified; CV/JD findings cross-referenced against uploaded docs (document-support stamped).
**Tests:** wage compliance; Holidays Act calc; document cross-reference.
**Out of scope:** none.

### WO-49 — Operational analysis + systems review
**Spec refs:** §10 (Operational, Systems Review)
**Goal:** SOPs/processes/bottlenecks/automation + technology gaps/integration/upgrade opportunities, as analysis modules.
**Depends on:** WO-31.
**Key files:** `app/Services/Analysis/Modules/OperationalAnalysis.php`, `SystemsReview.php`.
**Acceptance:** findings with attribution across lenses.
**Tests:** module runs.
**Out of scope:** none.

### WO-50 — NZ compliance checker + legislative currency
**Spec refs:** §10 (NZ Compliance Checker); §11/§24 (Legislative currency); §23 layer "NZ legislative currency monitoring"
**Goal:** Check against ERA, H&S Act, Holidays Act, Privacy Act, Companies Act — severity-rated, legislatively current. Fill NZ Parliament / WorkSafe / IRD feed contracts; scheduled monitoring queues governed update candidates when law changes.
**Depends on:** WO-31, WO-13 scaffolds.
**Key files:** `app/Services/Analysis/Modules/ComplianceChecker.php`, `app/Console/Commands/MonitorLegislativeCurrency.php`, feed clients.
**Acceptance:** compliance findings severity-rated with statute citation; a detected legislative change queues a candidate (no auto-apply); document verification on employment agreements / insurance certs / H&S policy.
**Tests:** severity rating; statute citation; legislative-change candidate; document cross-reference.
**Out of scope:** DD regulatory workstream (Phase 3).

### WO-51 — Regulatory change impact assessment
**Spec refs:** §10 (Regulatory Impact Assessment); §20 (Regulatory Impact Actions)
**Goal:** When NZ legislation changes (from WO-50 monitoring), assess per-client financial impact + compliance actions.
**Depends on:** WO-50, WO-43 (PV for financial impact).
**Key files:** `app/Services/Analysis/RegulatoryImpactAssessor.php`.
**Acceptance:** a legislative-change event produces per-client impact findings with PV-quantified cost and recommended actions; auto-generates compliance action items.
**Tests:** impact generation; PV linkage.
**Out of scope:** the milestones tracker that would hold the actions (Phase 3) — Phase 2 records actions on the finding.

### WO-52 — Insurance risk flag detection
**Spec refs:** §10 (Insurance Risk Flags); §9 (insurance cert verification)
**Goal:** Detect coverage gaps; verify coverage amounts/expiry/types against uploaded certificates; produce flags that will feed broker referral (Phase 3).
**Depends on:** WO-31.
**Key files:** `app/Services/Analysis/Modules/InsuranceRiskFlags.php`.
**Acceptance:** coverage gaps flagged; certificate figures verified (document-support stamped); flags recorded for future broker referral.
**Tests:** gap detection; certificate verification.
**Out of scope:** broker referral workflow (Phase 3).

### WO-53 — Scenario planning
**Spec refs:** §10/§20 (Scenario Planning)
**Goal:** Up to 5 named scenarios side-by-side; best/expected/worst case; PV impact per scenario; NZ economic overlay. Client-viewable per spec §7.
**Depends on:** WO-31, WO-43, WO-36.
**Key files:** `app/Services/Analysis/ScenarioPlanner.php`, `scenarios` table, scenario React UI (advisor + client read-only).
**Acceptance:** up to 5 scenarios; PV impact computed per scenario; economic overlay applied; client portal shows named scenarios with PV impact.
**Tests:** 5-scenario bound; PV-per-scenario; client visibility scope.
**Out of scope:** none.

### WO-54 — Succession planning
**Spec refs:** §10/§20 (Succession Planning)
**Goal:** Exit-readiness score, options assessment, owner-dependency reduction plan, target exit PV. (DD integration for sale is Phase 3.)
**Depends on:** WO-31, WO-43.
**Key files:** `app/Services/Analysis/SuccessionPlanner.php`, `succession_plans` table.
**Acceptance:** readiness score; options; owner-dependency plan; target exit PV set; owner-readiness gap emits a coaching signal (Phase 3 consumer).
**Tests:** readiness scoring; PV target; coaching-signal emission.
**Out of scope:** DD integration (Phase 3).

### WO-55 — Fee calculator
**Spec refs:** §13 (Hours-Based, Outcome-Based, Entrepreneur Fee)
**Goal:** Hours-based (hours × rate, per-service, retainer conversion); outcome-based (AI-suggested range from improvement PV + risk-cost PV + revenue + complexity, **PV-referenced justification**); separate entrepreneur fee structure.
**Depends on:** WO-43, WO-44.
**Key files:** `app/Services/Fees/FeeCalculator.php`, `fee_calculations` table/model, `app/Enums/FeeMethod.php`.
**Acceptance:** outcome-based suggestion references improvement PV + risk PV directly in its justification; ROI ratio = total improvement PV vs fee; entrepreneur structure is a distinct, lower-entry path.
**Tests:** three methods; ROI ratio; PV-referenced justification.
**Out of scope:** payment (Phase 3).

### WO-56 — Fee proposal generation + release control + expiry
**Spec refs:** §13 (Proposal Contents, Expiry, Release Control); §19
**Goal:** Generate a proposal (scope, services, fee, PV summary with ROI, insurance/coach consent election, acceptance section); manual "Release to Client" with recall; expiry countdown (default 30 days, configurable) with renewal; status lifecycle Draft→Released→Awaiting Signature→Signed→Expired→Renewed (Signed/payment reachable only in Phase 3); branded PDF.
**Depends on:** WO-55, Phase 1 WO-11 (PdfRenderer), WO-12.
**Key files:** `app/Services/Proposals/ProposalBuilder.php`, `proposals` + `consents` tables/models, advisor proposal controller + UI, expiry scheduler.
**Acceptance:** proposal PDF includes PV summary + ROI; release is a manual advisor action, recallable before signature; expiry countdown shown, expired proposals require re-release; all transitions audited; consent election captured.
**Tests:** generation, release/recall, expiry transition, renewal, audit.
**Out of scope:** 7-step sign-off flow + payment (Phase 3).

### WO-57 — Report engine + Client & Advisor reports
**Spec refs:** §19 (report types, AI source attribution, document-verification notation, data-quality disclaimer)
**Goal:** `ReportComposer` (§7.5) + the Client report (findings/issues/current valuation range; no recommendations or fee detail) and Advisor report (full picture + PV waterfall + implementation plan + fee proposal with ROI). Branded PDF via `PdfRenderer`.
**Depends on:** WO-43, WO-44, WO-56.
**Key files:** `app/Services/Reports/ReportComposer.php`, `reports` + `report_sections` tables/models, report type classes, `app/Enums/ReportType.php`.
**Acceptance:** every report section carries attribution + document-support note + data-quality note; Client report redacts recommendations/fees; Advisor report includes the waterfall + ROI; PDFs branded.
**Tests:** composition; Client redaction; Advisor completeness; notation presence on every section.
**Out of scope:** DD + Entrepreneur-Assessment reports (engine scaffolds the *types*; Phase 3 fills them).

### WO-58 — Stakeholder report + PowerPoint export
**Spec refs:** §19 (Stakeholder Report; PDF + PowerPoint)
**Goal:** Board/bank/investor-ready standalone report with no FSA methodology/IP; export as both PDF and PowerPoint.
**Depends on:** WO-57.
**Key files:** `app/Services/Reports/Types/StakeholderReport.php`, `app/Services/Pptx/Contracts/PptxGenerator.php` + a renderer, export controller.
**Acceptance:** Stakeholder report excludes FSA methodology/IP; one-click PDF and PowerPoint; both carry the liability disclaimer.
**Tests:** IP redaction; PDF + PPTX generation (renderer faked in tests).
**Out of scope:** other report types.

### WO-59 — Business health trajectory report
**Spec refs:** §19 (Business Health Trajectory Report)
**Goal:** Whole-engagement journey; metrics trended start→current; PV at each milestone; auto-generated narrative, advisor-reviewed.
**Depends on:** WO-57, WO-43.
**Key files:** `app/Services/Reports/Types/TrajectoryReport.php`, trend charts.
**Acceptance:** trends from engagement start; PV at milestones; narrative generated and advisor-reviewable before share.
**Tests:** trend assembly; PV-at-milestone; review gate.
**Out of scope:** none.

### WO-60 — Industry intelligence briefings + pre-meeting brief
**Spec refs:** §19 (Industry Intelligence Briefings; Automated Pre-Meeting Brief)
**Goal:** Monthly per-client AI-generated industry briefing (NZ sources cited, advisor-reviewed before send); 24h-before automated pre-meeting brief (last actions, completions, red flags, financial changes; one page; advisor-reviewed).
**Depends on:** WO-57, WO-34 (red flags), WO-38 (financial changes).
**Key files:** `app/Services/Reports/IndustryBriefingGenerator.php`, `PreMeetingBriefGenerator.php`, `industry_briefings` + `pre_meeting_briefs` tables, schedulers.
**Acceptance:** monthly briefing generated with cited NZ sources, held for advisor review; pre-meeting brief generated 24h before a scheduled meeting summarising the right signals; both route via `ChannelResolver` after review.
**Tests:** briefing generation + review gate; pre-meeting trigger timing; content assembly.
**Out of scope:** calendar two-way sync (Phase 2 reads meeting times from existing data; full Google/Outlook sync remains scaffolded).

### WO-61 — Funnel analytics
**Spec refs:** §7 (Funnel Analytics); §22
**Goal:** Track drop-off across all multi-step flows (onboarding, questionnaire, proposal); surface to advisor dashboard. Monthly UX-improvement suggestions queue governed candidates (spec §23 layer).
**Depends on:** WO-31, Phase 1 flows.
**Key files:** `app/Services/Analytics/FunnelTracker.php`, `funnel_events` table, dashboard panel.
**Acceptance:** step entry/completion/abandon recorded for each flow; drop-off surfaced; monthly suggestion candidate queued.
**Tests:** event capture; drop-off computation; candidate emission.
**Out of scope:** none.

### WO-62 — Practice health report (PV portfolio)
**Spec refs:** §12.1 (Practice Health Report); §22
**Goal:** Portfolio view — total PV delivered across all active clients, revenue under management, plus Phase 2 metrics; on-demand and monthly.
**Depends on:** WO-43, WO-56.
**Key files:** `app/Services/Reports/PracticeHealthReport.php`, `practice_health_snapshots` cache, super-admin/advisor view.
**Acceptance:** total PV across active clients; revenue under management; monthly snapshot cached; respects RLS (advisor sees own portfolio, super-admin all).
**Tests:** portfolio aggregation; RLS scoping; snapshot caching.
**Out of scope:** DD pipeline + entrepreneur conversion metrics (Phase 3 adds those columns).

### WO-63 — Advisor dashboard Phase 2 panels
**Spec refs:** §22
**Goal:** Extend the Phase 1 dashboard shell (WO-28) with: proposal status panel (by stage + expiry alerts), economic indicators tiles (live OCR/CPI/etc with change alerts), red-flag alerts panel, practice-health summary, and questionnaire AI-optimisation suggestions (spec §23 layer — quarterly, governed candidates only).
**Depends on:** WO-34, WO-36, WO-56, WO-62.
**Key files:** dashboard panel components, `app/Console/Commands/RunQuestionnaireOptimisationLayer.php`.
**Acceptance:** each panel pulls live, RLS-scoped data; economic tiles show change alerts; questionnaire-optimisation produces governed candidates (no auto-apply).
**Tests:** panel data assembly; RLS scoping; optimisation candidate emission.
**Out of scope:** payment status panel data (Phase 3 — placeholder slot remains), broker/coach/DD/entrepreneur panels (Phase 3), learning queue UI (Phase 3).

### WO-64 — Wellbeing monthly pulse + analytics
**Spec refs:** §6 (Wellbeing Check-In); §26 Phase 2 row
**Goal:** Extend the Phase 1 wellbeing primitive (WO-20) with the monthly optional pulse scheduling, advisor-only trend analytics, and the low-score coaching signal (consumed by Phase 3 coach referral).
**Depends on:** Phase 1 WO-20, WO-12.
**Key files:** monthly pulse scheduler, advisor trend view, coaching-signal emission (reuses the `coaching_signals` scaffold from WO-20).
**Acceptance:** monthly prompt scheduled; responses advisor-only; two-consecutive-months low score emits a coaching signal per spec §15.4 thresholds.
**Tests:** scheduling; visibility scope; signal threshold.
**Out of scope:** coach referral workflow (Phase 3).

---

## 9. Acceptance & Definition of Done (per WO)

Same bar as Phase 1 (`PLAN.md` §9), plus Phase-2-specific gates:

1. All listed files exist; no orphans.
2. Every acceptance criterion demonstrably true (cite test names in the commit/PR body).
3. New tables documented in `docs/architecture/schema.md`; new client-scoped tables have an RLS policy **and** an RLS test.
4. `composer test`, Pint, ESLint, Prettier, `tsc --noEmit` all pass.
5. No `TODO`/`FIXME` without a linked WO; no `dd()`/`dump()`/`console.log()`.
6. No Phase 3/4 feature introduced.
7. **Every analysis-bearing surface routes through the spine (§7.1).** No module re-implements the integrity/verification/data-quality gates.
8. **Every finding/number carries attribution.** AI Integrity Principle holds across prose and PV figures.
9. **Every external call goes through `ResilientHttp`; every AI call through `AiClient`.** New integrations gated by `FEATURE_*_LIVE` with fixtures for tests.
10. Architecture doc added/updated for the WO under `docs/architecture/`.

## 10. Test strategy (Phase 2 additions)

- **Spine contract test** — a fixture module proves the integrity pipeline (attribution enforced, doc-verification block, data-quality abort, lens tagging) once; modules then test only their mapping.
- **`FakeAiClient` everywhere** — every analysis/report/fee test uses the fake; a guard test asserts the live client is never bound in `testing`.
- **PV correctness tests** — discounting math and reconciliation verified against hand-computed fixtures.
- **Integration contract tests** — each new live client and its fixture stub satisfy the same contract test (as Phase 1).
- **RLS tests** — every new client-scoped table (`analysis_runs`, `analysis_findings`, `pv_calculations`, `proposals`, `reports`, `financial_snapshots`, …) gets a cross-client isolation test.
- **Governed-learning guard** — a test asserts Phase 2 learning layers only ever create `learning_updates` in `detected` status; nothing auto-implements.
- **Report notation tests** — assert every `report_section` carries attribution + document-support + data-quality notes.
- **Redaction tests** — Client report excludes recommendations/fees; Stakeholder excludes FSA methodology/IP.

## 11. Phase 3 / 4 — what comes after (unchanged roadmap)

See `PLAN.md` §11 for the full Phase 3/4 list. Phase 2 deliberately leaves these seams: proposal `awaiting_signature`/`signed` states (Phase 3 sign-off + payment), the DD and entrepreneur-assessment report *types* (Phase 3 modules fill them), PV-into-milestones (Phase 3 goals tracker), coaching signals (Phase 3 coach referral), the learning-queue approval UI (Phase 3), and cross-client/anonymous intelligence (Phase 4).

## 12. Open risks & decisions to revisit (Phase 2)

| # | Risk / open item | Owner action | Trigger |
|---|---|---|---|
| P2-R1 | Anthropic API key still optional — analysis runs in degraded mode without it; Phase 2 is where real AI value appears | Provision key | Before validating any analysis module end-to-end |
| P2-R2 | Xero/MYOB/QuickBooks developer apps + OAuth credentials needed for live accounting; each has an app-review process | Register developer apps early | Before WO-37 live mode |
| P2-R3 | RBNZ / Stats NZ / MBIE data access — most are open data, but terms/rate-limits vary | Confirm access + caching policy | Before WO-36 live mode |
| P2-R4 | Valuation multiples (MBIE / NZ Business Brokers) may require a data agreement | Confirm source + licence | Before WO-39 live mode |
| P2-R5 | PowerPoint export library choice (PHP lib vs Node sidecar) affects deploy footprint | Decide in WO-58 | WO-58 start |
| P2-R6 | Outcome-based fee suggestions are advice-adjacent; ensure the AI Integrity Principle + liability disclaimer cover them and a lawyer reviews the fee-justification wording | Owner + lawyer review | Before WO-55 ships to a real client |
| P2-R7 | NZ statutory penalty ranges (risk-cost PV) must be current and sourced | Confirm source list | Before WO-42 ships |
| P2-R8 | Continuous monitoring cadence + cost (accounting API rate limits, Claude token spend) | Set sane defaults + budgets | Before WO-38 enables scheduling |
| P2-R9 | Carryover Phase 1 inputs still pending (brand kit, lawyer-reviewed T&C) now block client-facing reports | Owner supply | Before any report leaves the platform |

## 13. Phase 2 feature → WO mapping (traceability)

| Spec §26 Phase 2 item | WO(s) |
|---|---|
| AI analysis engine core + feedback capture + source attribution | WO-31, WO-32 |
| Bias detection layer (full) | WO-33 |
| Financial analysis + continuous monitoring | WO-44, WO-37, WO-38 |
| PV module (3 types, 4 methods) | WO-40, WO-41, WO-42 |
| PV integration into reports/proposals/dashboard | WO-43 |
| PV waterfall chart | WO-43 |
| Website audit, competitor, SWOT/TOWS/MAPS | WO-45, WO-46, WO-47 |
| NZ compliance checker + legislative currency | WO-50 |
| Regulatory change impact assessment | WO-51 |
| Insurance risk flag detection | WO-52 |
| HR/people, operational, systems review | WO-48, WO-49 |
| AI red flag alerts + client knowledge assessment | WO-34, WO-35 |
| Scenario planning | WO-53 |
| Succession planning | WO-54 |
| Fee calculator | WO-55 |
| Five-report system | WO-57 (engine + Client/Advisor), WO-58 (Stakeholder); DD + Entrepreneur types scaffolded, filled Phase 3 |
| Stakeholder report | WO-58 |
| Business health trajectory report | WO-59 |
| Fee proposal generation + release control + expiry | WO-56 |
| Industry intelligence briefings | WO-60 |
| Automated pre-meeting brief | WO-60 |
| NZ economic indicators feed | WO-36 |
| Accounting API integration | WO-37 |
| Valuation multiple data feed | WO-39 |
| Funnel analytics | WO-61 |
| Practice health report (PV portfolio) | WO-62 |
| Client wellbeing check-in (monthly pulse) | WO-64 |
| (Advisor dashboard Phase 2 panels — implied by §22) | WO-63 |

---

*End of Phase 2 plan. Phase 3 plan (DD, commerce sign-off + payment, broker/coach portals, full entrepreneur module, learning-queue UI) to be authored after Phase 2 acceptance. `PLAN.md` (Phase 1) is intentionally left unchanged.*
