---
name: FSA App Guardrails
description: Apply Future Shift Advisory app guardrails and AI-assistant skill routing when changing Laravel/Inertia code, analysis modules, reports, proposals, strategic plans, calendar rules, document handling, AI prompts, voice/assistant flows, learning layers, or Claude skills.
when_to_use: Use for FSA feature work, reviews, tests, or docs changes where client workflows, AI integrity, security, Standard Advisory scope, reporting, proposals, strategic plans, uploaded documents, data, finance, productivity, operations, FP&A, decisions, fact checks, skill creation, or forecasting/time-series AI may be affected.
paths:
  - app/**
  - resources/js/**
  - routes/**
  - database/**
  - tests/**
  - docs/**
---

## FSA App Guardrails

Apply these rules before editing and again before finishing.

## Scope And Flow

- Read the touched code path first; follow existing Laravel, Inertia, shadcn/ui, audit, RLS, and test patterns.
- Keep changes scoped to the current work item. Do not implement unrelated roadmap features.
- Standard Advisory analysis findings must flow into the "what is wrong" report, the proposal "what needs to be fixed" section, and the signed-proposal strategic plan where relevant.
- Client-facing outputs must say what is wrong, why it matters commercially, and what needs to be fixed. Avoid vague advisory language that does not create business value.
- Do not leak DD, Post-Acquisition, Entrepreneur, NPO, broker, coach, or future-phase assumptions into Standard Advisory workflows unless the request explicitly asks for that lane.

## AI Integrity

- All AI calls go through `App\Services\Ai\Contracts\AiClient`; never construct direct Anthropic API requests outside `AnthropicClaudeClient`.
- Every factual AI claim needs source attribution. Missing attribution is a hard failure.
- Bias signals and accuracy discrepancies are surfaced through the governed learning/document-verification paths; never silently suppress them.
- Tests use fake or recording AI clients unless a live-integration test is explicitly gated.

## AI Assistant Skill Routing

Whenever adding or changing AI or AI-assistant behaviour, identify which capability areas apply and encode the rule in prompts, services, reports, proposals, UI copy, and tests.

| Capability | Apply When | Required Behaviour |
|---|---|---|
| Data | Analysis modules, document verification, data-quality gates, integrations, dashboards, learning signals, and source references | Preserve provenance, quality state, uncertainty, tenant/RLS boundaries, and evidence links. Do not infer missing data as fact. |
| Finance | Financial analysis, PV, fees, accounting, funding, proposals, budgets, invoices, valuation, grant accountability, and business-case outputs | Use NZ context, show assumptions, quantify value/risk where supportable, and keep financial claims attributed and reviewable. |
| Productivity | Voice notes, voice assistant shortcuts, template suggestions, knowledge capture, notifications, meeting notes, action extraction, and advisor workflow aids | Create useful drafts or actions without bypassing approval, audit, document verification, client scope, or public-holiday scheduling rules. |
| Operations | OperationalAnalysis, SystemsReview, SOP/process evidence, automation candidates, handoffs, duplicate entry, reporting lag, and workflow constraints | Convert process gaps into measurable business impact and specific fixes; avoid generic automation recommendations. |
| Financial Planning and Analysis | Cash flow, budgets, runway, scenario planning, variance, forecasts, strategic budgets, and plan milestones | Separate historicals, assumptions, forecast outputs, and confidence. Surface sensitivity/range and advisor-review requirements. |
| decision-toolkit | Recommendations, prioritisation, proposal focus areas, strategic-plan priorities, red flags, and trade-off decisions | Present options, evidence, consequences, risks, and the recommended decision path; do not hide material downsides. |
| fact-checker | Website audit, document claims, reports, AI narratives, regulatory content, proposal claims, and client-facing guidance | Verify against supplied sources or current official sources; flag unsupported, stale, contradictory, or missing evidence. |
| skill-creator | Updates to `.claude/skills/**/SKILL.md`, project memory, reusable prompt operating rules, or AI-assistant capability documentation | Keep skills concise, triggerable, frontmatter-valid, and tested; move repeatable workflow guidance into skills rather than bloating `CLAUDE.md`. |
| forecasting-time-series-data | Time-series trends, cash-flow forecasts, wellbeing trends, sales/revenue history, budget runway, valuation history, and economic indicators | Use appropriate time windows, label seasonality/outliers, avoid overfitting sparse data, and state forecast uncertainty plainly. |

Current AI surfaces to check include `AnalysisRunner`, `PromptRegistry`, `DocumentVerifier`, entrepreneur assessment/guidance/idea validation, voice note processing, voice assistant shortcuts, knowledge capture, template suggestions, NPO AI assessments, report narratives, learning layers, and budget/forecast helpers.

## Security And Documents

- Accounts remain invite-only and MFA remains mandatory for all user types.
- Every meaningful state change is audit logged.
- Every uploaded file must be virus-scanned before persistence and document-verified before downstream analysis uses it.
- Document verification outcomes stay limited to `verified`, `advisory_flag`, and `accuracy_discrepancy`; discrepancies pause downstream analysis until resolved.
- Do not write PII to raw logs. Route external calls through `ResilientHttp` with retry/circuit-breaker handling.

## Calendar And Execution

- Meetings, actions, and milestones cannot be scheduled on public holidays for the client region.
- National NZ holidays apply to all NZ clients. Regional anniversary days apply only to matching client regions; South Island regional holidays do not block North Island clients unless the client region matches, and vice versa.
- System-generated due dates should move forward to the next non-holiday date instead of failing deployment.

## Verification

- Add focused tests for every business rule you change.
- Prefer unit tests for pure services and feature tests for controller/UI flows.
- Run the narrowest useful checks first, then `npm run types:check` for TypeScript changes and Pint for PHP changes.
- If database-backed tests fail because local Postgres credentials are missing, report the exact blocker and keep non-DB checks green.
