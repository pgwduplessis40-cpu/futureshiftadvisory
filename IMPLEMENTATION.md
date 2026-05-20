# Future Shift Advisory - Implementation Status & Handoff

Living status document for the Phase 1 build. Read alongside [`PLAN.md`](./PLAN.md) and [`CLAUDE.md`](./CLAUDE.md).

**Last updated:** 2026-05-21
**Phase:** 1 - Foundation
**Plan:** 30 work orders. See [`PLAN.md` section 8](./PLAN.md).

## Snapshot

| | |
|---|---|
| Work orders complete | **5 / 30** (WO-01, WO-02, WO-03, WO-04, WO-05) |
| Work orders in progress | none |
| Next work order | **WO-06** - Secure file storage + virus scanning interface |
| Current branch | `featureApp` |
| Branching rule | Do not create WO branches. Commit each completed WO directly on `featureApp`. |
| Verification status | Full PHP suite, ESLint, TypeScript, and Prettier are passing locally. |

## Commit Log

| WO | Commit | Title | Notes |
|---|---|---|---|
| WO-01 | included in foundation history | Project foundations & critical rules | Docs, `CLAUDE.md`, architecture folder, setup docs. |
| WO-02 | included in foundation history | PostgreSQL migration + RLS scaffold | Postgres helpers, RLS context middleware, `KeyEnvelope`. |
| WO-03 | included in foundation history | Immutable audit trail | `audit_events`, append-only triggers, redaction, audit chain command. |
| WO-04 | `6c266d5` | AI Integrity foundation | `AiClient`, DTOs, prompt registry, source attribution, bias detector, fake/live client, learning-update scaffolding. |
| WO-05 | this commit | Integration resilience layer | `ResilientHttp`, retry policy, circuit breaker, per-call logging, health rollups. |

## Completed WO Details

### WO-01 - Project Foundations

- Root `CLAUDE.md` with critical AI, document verification, security, and process rules.
- `README.md`, `docs/dev-setup.md`, `docs/architecture/*`, legal/brand/rating placeholders.

### WO-02 - PostgreSQL + RLS + KeyEnvelope

- Postgres extensions and RLS helper functions.
- `RequestContext` and `EnforceClientScope` middleware.
- `KeyEnvelope` encryption-at-rest seam for future PQC replacement.
- RLS harness test now verifies policies even when local Postgres uses a superuser by switching into a temporary non-bypass role.

### WO-03 - Immutable Audit Trail

- `audit_events` table and append-only Postgres triggers.
- `AuditWriter`, `WritesAudit`, read-tracking middleware, and `fsa:audit:verify`.
- Redactor masks email, NZ phone, NZ bank account, and IRD values while leaving NZBN visible.

### WO-04 - AI Integrity Foundation

- `AiClient` contract with `analyse`, `verifyDocument`, `scoreCriterion`, `summarise`, and `redFlag`.
- `AiResponse`, `PromptEnvelope`, and `Uncertainty` DTOs.
- `IntegrityPreamble` and `PromptRegistry`.
- `SourceAttribution` raises `MissingAttributionException` when factual output lacks attribution.
- `BiasDetector` logs every AI output and creates governed `learning_updates` candidates for heuristic bias signals.
- `AnthropicClaudeClient` is the only live Anthropic HTTP exit point.
- `FakeAiClient` returns deterministic degraded responses when Anthropic is unavailable.
- `AdvisorAiNotice` records degraded-mode notices and authenticated Inertia pages render an AI-deferred banner until WO-12 moves this into the notification centre.
- `learning_updates` and `learning_update_implementations` tables scaffold future governed learning without self-modifying behaviour.
- Architecture docs: `docs/architecture/ai-integrity.md` and `docs/architecture/schema.md`.

### WO-05 - Integration Resilience Layer

- `integration_calls` ledger for every success, retry, failure, cached response, and fallback response.
- `integration_health_samples` five-minute rollup table for WO-30 dashboard surfaces.
- `RetryPolicy` with config-driven attempts, retry status codes, and exponential backoff.
- `CircuitBreaker` with default five failures in sixty seconds and a five-minute open window.
- `HealthRecorder` as the single write path for integration call health rows.
- `ResilientHttp` wrapper for future live integrations, including cached fallback and graceful degraded response support.
- `AggregateIntegrationHealth` command scheduled every five minutes.
- Architecture doc: `docs/architecture/integration-pattern.md`.

## Verification

Latest local checks:

```pwsh
composer test
npm run lint:check
npm run types:check
npm run format:check
```

Results on 2026-05-21:

- `composer test`: passed, 82 tests, 237 assertions.
- `npm run lint:check`: passed.
- `npm run types:check`: passed.
- `npm run format:check`: passed.

Note: the local test DB required using the actual local Postgres connection values from `.env` in the process environment. Do not commit local DB credentials.

## Remaining Phase 1

| WO | Title | Status | Depends on |
|---|---|---|---|
| WO-06 | Secure file storage + virus scanning interface | next | WO-02, WO-05 |
| WO-07 | User roles, permissions, RBAC | not started | WO-02, WO-03 |
| WO-08 | Invite-only registration + MFA enforcement | not started | WO-07 |
| WO-09 | Session management + step-up MFA | not started | WO-08 |
| WO-10 | Terms model + version control + admin clause editor | not started | WO-07 |
| WO-11 | T&C acceptance gate + signed-PDF generation | not started | WO-10, WO-06 |
| WO-12 | Centralised notifications + channel preferences | not started | WO-07, WO-09 |
| WO-13 | NZ integration scaffolds | not started | WO-05 |
| WO-14 | Add New Client | not started | WO-07, WO-13, WO-21, WO-22 |
| WO-15 | Add New Entrepreneur | not started | WO-14 |
| WO-16 | Client portal shell + onboarding wizard | not started | WO-11, WO-12, WO-14 |
| WO-17 | Questionnaire engine | not started | WO-14, WO-16 |
| WO-18 | Document upload + verification pipeline | not started | WO-04, WO-06, WO-17 |
| WO-19 | Data quality gate | not started | WO-17, WO-18 |
| WO-20 | Wellbeing check-in | not started | WO-16 |
| WO-21 | Conflict of interest declaration | not started | WO-07 |
| WO-22 | Structured offboarding | not started | WO-11, WO-12 |
| WO-23 | Client lifecycle management | not started | WO-14, WO-22 |
| WO-24 | Notification centre UI | not started | WO-12, WO-16 |
| WO-25 | In-app messaging | not started | WO-12, WO-16, WO-18 |
| WO-26 | Email-from-app | not started | WO-12, WO-25 |
| WO-27 | Advisor knowledge base | not started | WO-07 |
| WO-28 | Advisor dashboard shell | not started | WO-14, WO-18, WO-29, WO-30 |
| WO-29 | Website integration layer | not started | WO-05, WO-12 |
| WO-30 | API health dashboard | not started | WO-05, WO-12 |

## Open Inputs

| Item | Needed for | Status |
|---|---|---|
| Anthropic API key | Live AI testing in WO-04/WO-18 | Optional; fake/degraded path works. |
| NZBN / Companies Office / IRD access | WO-13 live mode | Stubs until arranged. |
| Meridian Warm brand kit | Client-facing UI | Placeholder files exist. |
| Lawyer-reviewed T&C text | WO-10/11 | Placeholder exists. |
| ClamAV deployment plan | Production uploads | Interface lands in WO-06. |

## Process Reminder

- Stay on `featureApp`.
- Implement WOs in numeric order.
- Commit each completed WO directly on `featureApp` with `WO-<id>: <slug summary>`.
- Do not invent Phase 2+ features.
- No raw secrets, unowned placeholder comments, or debug calls in shipped code.
- All AI calls go through `AiClient`.
- All future external calls go through the resilience layer introduced in WO-05.
