# Architecture decisions

This folder holds Architecture Decision Records (ADRs) and topic-specific architecture notes for the Future Shift Advisory platform.

## When to add a file here

- A non-trivial architectural choice was made and a future contributor would benefit from knowing the *why* (not just the *what*).
- A decision diverges from the spec — record the deviation, the rationale, the trigger for revisiting.
- A cross-cutting concern needs its own write-up (security, audit, AI integrity, integration patterns).

## Architecture file index

| File | Created by WO | Purpose |
|---|---|---|
| `postgres-rls.md` | WO-02 | How row-level security is wired; how to add a new client-scoped table |
| `key-envelope.md` | WO-02 | AES-256 envelope design and PQC-ready seam (spec §4 deviation) |
| `audit-trail.md` | WO-03 | Immutable audit table contract, redaction policy, chain verification job |
| `ai-integrity.md` | WO-04 | AiClient contract, prompt registry, attribution validator, bias detector |
| `integration-pattern.md` | WO-05 | ResilientHttp + RetryPolicy + CircuitBreaker + cached fallback shape |
| `secure-file-storage.md` | WO-06 | SecureFileWriter, encrypted disk wrapper, scanner contract, quarantine flow |
| `rbac-matrix.md` | WO-07 | Spatie role/permission matrix, DD guest token-only rule, middleware/policy gates |
| `auth-invite-mfa.md` | WO-08 | Invite-only account creation, Fortify MFA enforcement, session MFA gate |
| `session-security.md` | WO-09 | Per-user-type session timeout, step-up MFA risk scoring, session audit hooks |
| `terms-versioning.md` | WO-10 | Terms versions, clause editing, material publish re-acceptance seam |
| `terms-acceptance.md` | WO-11 | Authenticated T&C gate, signed PDF evidence, decline suspension |
| `notifications.md` | WO-12 | Channel preferences, notification decision ledger, digest jobs |
| `nz-integrations.md` | WO-13 | NZBN, Companies Office, IRD clients and empty named integration scaffolds |
| `client-management.md` | WO-14 | Client creation, engagement type, NZBN population, conflict declaration gate |
| `entrepreneur-profiles.md` | WO-15 | Basic entrepreneur profiles, invite handoff, Phase 1 capacity gates |
| `client-portal.md` | WO-16 | Client portal shell, onboarding wizard state, questionnaire placeholders |
| `questionnaire-engine.md` | WO-17 | Versioned questionnaire sets, conditional logic, admin builder, portal renderer |
| `document-verification.md` | WO-18 | Secure upload verification records, AI outcomes, advisor flags, analysis gate |
| `analysis-spine.md` | WO-31 | Shared Phase 2 analysis pipeline, run/finding storage, gates, RLS |
| `analysis-feedback-learning.md` | WO-32 | Advisor feedback capture and governed feedback-learning cadence |
| `bias-monitoring.md` | WO-33 | Per-output bias signals, systematic skew monitor, governed alerts |
| `red-flags.md` | WO-34 | Critical analysis finding promotion, urgent alerts, acknowledge/resolve flow |
| `knowledge-assessment.md` | WO-35 | Client knowledge scores, analysis prompt calibration, raw coaching-signal boundary |
| `economic-indicators.md` | WO-36 | RBNZ/Stats NZ/MBIE economic feeds, persisted rates, OCR-change candidates |
| `accounting-integration.md` | WO-37 | Xero/MYOB/QuickBooks OAuth, encrypted tokens, append-only financial snapshots |
| `financial-monitoring.md` | WO-38 | Scheduled accounting pulls, deterioration alerts, snapshot citations, notification routing |
| `valuation-multiples.md` | WO-39 | NZ-benchmarked EBITDA/SDE multiple feed, supersession, governed refresh candidates |
| `pv-foundation.md` | WO-40 | Shared PV engine, discount-rate methods, calculation ledger, attribution contract |
| `business-valuation.md` | WO-41 | SDE, EBITDA, and DCF business valuation with reconciliation and adjustments |
| `pv-impact-types.md` | WO-42 | Improvement-opportunity and risk-cost PV ranking with finding linkage |
| `pv-integration.md` | WO-43 | Dashboard/report-ready PV waterfall assembly and advisor dashboard surface |
| `financial-analysis.md` | WO-44 | Financial analysis module over snapshots, questionnaire fallback, economic overlay, and PV linkage |
| `website-audit.md` | WO-45 | Website audit analysis for SEO, content, UX, CTAs, mobile performance, and NZ search context |
| `competitor-analysis.md` | WO-46 | Competitor product, pricing, visibility, and gap analysis with six-competitor bound |
| `strategic-matrices.md` | WO-47 | SWOT/TOWS/MAPS matrix assembly with cited evidence and PV-referenced priorities |
| `hr-analysis.md` | WO-48 | HR/people analysis with wage benchmarking, verified HR-document support, and Holidays Act liability |
| `operational-systems-analysis.md` | WO-49 | Operational bottleneck and systems integration analysis modules |
| `compliance-legislative-currency.md` | WO-50 | NZ compliance checker with statute citations and governed legislative-currency monitor |
| `regulatory-impact.md` | WO-51 | Client-specific regulatory impact findings with risk-cost PV linkage |
| `insurance-risk-flags.md` | WO-52 | Insurance coverage-gap flags with verified certificate support |
| `scenario-planning.md` | WO-53 | Five-scenario planning with PV deltas, economic overlay, and client visibility |
| `succession-planning.md` | WO-54 | Exit readiness, target exit PV, and raw owner-readiness coaching observations |
| `fee-calculator.md` | WO-55 | Hours-based, outcome-based, and entrepreneur fee calculations with PV ROI |
| `proposals.md` | WO-56 | Branded fee proposals with consent elections, release/recall, expiry, and renewal |
| `reports.md` | WO-57 | Shared report composer with Client/Advisor reports, integrity notes, and branded PDFs |
| `stakeholder-report.md` | WO-58 | Stakeholder report redaction profile and PDF/PowerPoint export artifacts |
| `trajectory-report.md` | WO-59 | Business health trajectory report with trends, PV milestones, and advisor review gate |
| `briefings.md` | WO-60 | Monthly industry briefings, local meetings, and 24-hour pre-meeting briefs |
| `funnel-analytics.md` | WO-61 | Funnel event tracking, drop-off dashboard data, and governed UX-improvement candidates |
| `practice-health-report.md` | WO-62 | Active-client PV portfolio, revenue under management, and monthly practice-health snapshots |
| `dashboard-phase2.md` | WO-63 | Phase 2 dashboard panels, proposal status, and questionnaire optimisation candidates |
| `wellbeing-analytics.md` | WO-64 | Monthly wellbeing pulse analytics and raw low-coping observation boundary |
| `payments.md` | WO-67...WO-69 | Payment schedules, gateway failover, processing, receipts, and failure boundaries |
| `security-decisions.md` | this WO | Phase 1 security deviations from spec §4 (PQC deferral, etc.) |

A skeleton `security-decisions.md` lives in this folder as part of WO-01 so the PQC deferral is recorded from day one.
