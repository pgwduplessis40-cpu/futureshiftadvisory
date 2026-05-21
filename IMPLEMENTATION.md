# Future Shift Advisory - Implementation Status & Handoff

Living status document for the Phase 1 build. Read alongside [`PLAN.md`](./PLAN.md) and [`CLAUDE.md`](./CLAUDE.md).

**Last updated:** 2026-05-21
**Phase:** 1 - Foundation
**Plan:** 30 work orders. See [`PLAN.md` section 8](./PLAN.md).

## Snapshot

| | |
|---|---|
| Work orders complete | **13 / 30** (WO-01, WO-02, WO-03, WO-04, WO-05, WO-06, WO-07, WO-08, WO-09, WO-10, WO-11, WO-12, WO-13) |
| Work orders in progress | none |
| Next work order | **WO-14** - Add New Client |
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
| WO-05 | `286c2ce` | Integration resilience layer | `ResilientHttp`, retry policy, circuit breaker, per-call logging, health rollups. |
| WO-06 | `eaa9ebd` | Secure file storage + virus scanning interface | `SecureFileWriter`, encrypted local disk, scanner contract/stubs, quarantine flow. |
| WO-07 | `543b7b7` | User roles, permissions, RBAC | Spatie permission tables, nine-role matrix, middleware, policies, matrix tests. |
| WO-08 | `3336e56` | Invite-only registration + MFA enforcement | Public registration removed, invite tokens, MFA gate, Fortify 2FA integration. |
| WO-09 | `b133147` | Session management + step-up MFA | Per-user-type timeouts, risk scoring, step-up MFA redirect, audit logging. |
| WO-10 | `454c11f` | Terms model + version control + admin clause editor | Version/clause schema, 14-clause seeder, admin edit/preview/publish flow, material re-acceptance seam. |
| WO-11 | `badb95f` | T&C acceptance gate + signed-PDF generation | Authenticated gate, scroll-end acceptance, signed PDF evidence, decline suspension, urgent advisor/super-admin notification. |
| WO-12 | `969a0ff` | Centralised notifications + channel preferences | Preference model, channel resolver, database decision ledger, digest jobs, communication settings UI. |
| WO-13 | this commit | NZ integration scaffolds | NZBN, Companies Office, and IRD clients with fixture stubs, resilience fallback, feature flags, and empty named future integration scaffolds. |

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
- `AdvisorAiNotice` records degraded-mode notices and authenticated Inertia pages render an AI-deferred banner; future notification-specific work can route it through the WO-12 resolver.
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

### WO-06 - Secure File Storage + Virus Scanning Interface

- `documents` metadata ledger for sensitive upload persistence and future document verification.
- `secure_local` filesystem disk registered with an encrypted Flysystem wrapper that uses `KeyEnvelope` on write/read.
- `FileScanner` contract with `NoopScanner` for dev/test degraded mode and `ClamAvScanner` skeleton for live ClamAV INSTREAM scanning.
- `SecureFileWriter` enforces scan-before-store, encrypted persistence, metadata creation, and immutable audit events.
- Infected uploads are rejected before persistence with an audit event.
- Scanner errors persist under `quarantine/`, set `scanner_result=error`, stay out of `Document::visibleToClients()`, and raise a cache/audit advisor notice that future notification-specific work can route through the WO-12 resolver.
- Architecture doc: `docs/architecture/secure-file-storage.md`.

### WO-07 - User Roles, Permissions, RBAC

- `spatie/laravel-permission` installed and published with its permission tables/config.
- `app/Enums/Permission.php` defines the canonical permission constants and the executable nine-role matrix.
- `PermissionSeeder` and `RoleSeeder` seed all nine spec user types as Spatie roles with their capability lists.
- `User` now uses Spatie `HasRoles`; `User::fsaRole()` resolves assigned roles for RLS context with `primary_role` as a legacy fallback.
- `EnsureRole` and `EnsurePermission` route middleware are registered as `role:*` and `permission:*`.
- Admin invitation routes are role-gated, invite acceptance assigns the matching Spatie role when seeded, and unsupported target roles are rejected.
- Policies exist for `Client`, `Document`, `Questionnaire`, `Notification`, `KnowledgeEntry`, `ProspectLead`, `TermsVersion`, and `AuditEvent`.
- DD guest remains token-only via `Permission::DD_GUEST_TOKEN_TYPE`; it is not a user type or Spatie role.
- Architecture doc: `docs/architecture/rbac-matrix.md`.

### WO-08 - Invite-Only Registration + MFA Enforcement

- Public Fortify registration is disabled; `/register` GET/POST now returns 404.
- `invite_tokens` supports hashed, expiring, one-shot invitations issued by `InviteIssuer`.
- Users now carry Phase 1 identity/MFA metadata (`user_type`, `primary_role`, `mfa_enabled_at`, session timeout/suspension fields).
- Invite acceptance creates the invited account, marks the token accepted, logs the user in, and redirects to MFA setup before any portal route is reachable.
- `mfa_factors` stores the TOTP factor ledger using `KeyEnvelope` for secret/recovery-code envelopes.
- `RequireMfa` protects authenticated app/settings/admin routes and redirects unenrolled users to setup or enrolled users without a verified session to the MFA challenge.
- Fortify 2FA confirmation and login challenge events sync `mfa_enabled_at`, `mfa_factors`, and session verification.
- Invite and MFA completion now hand users to the WO-11 terms gate before portal access.
- Admin invitation UI now sits behind the WO-07 Spatie RBAC matrix.
- Architecture doc: `docs/architecture/auth-invite-mfa.md`.

### WO-09 - Session Management + Step-Up MFA

- `sessions` table now records `risk_score` and `step_up_at` for database-session observability.
- `EnforceSessionSecurity` runs on authenticated web requests and enforces per-user-type inactivity timeouts.
- Timeout resolution uses `users.session_timeout_minutes` first, then config defaults (`super_admin` 15 minutes, advisors 30, clients/entrepreneurs/brokers/coaches 60).
- Expired sessions write `security.session_expired`, log the user out, invalidate the session, and redirect to login.
- `StepUpEvaluator` scores Phase 1 risk signals: IP change, country header change, user-agent change, and super-admin route access from a changed device.
- Sessions over the risk threshold are redirected to `mfa.challenge?reason=step_up`; failed step-up attempts write `security.step_up_failed`.
- `MfaChallenger` now fails closed on malformed/legacy TOTP secrets instead of leaking provider exceptions.
- Architecture doc: `docs/architecture/session-security.md`.

### WO-10 - Terms Model + Version Control + Admin Clause Editor

- `terms_versions`, `terms_clauses`, and the WO-11-ready `terms_acceptances` ledger are in place.
- `TermsVersionSeeder` imports `docs/legal/terms-v1.md`, enforces exactly 14 clauses, and marks clauses 1, 5, 6, 10, and 12 material by default.
- `TermsController` gives super-admins index, draft, edit, preview, and publish flows.
- Published terms versions are immutable and all prior versions remain readable through preview.
- Material publishing expires active acceptances of the prior published version after the configured notice period and records the notification queue seam on `terms_acceptances`.
- Non-material publishing leaves acceptances untouched and writes the immutable audit event.
- Admin Inertia pages exist under `resources/js/pages/admin/terms/*`.
- Architecture doc: `docs/architecture/terms-versioning.md`.

### WO-11 - T&C Acceptance Gate + Signed-PDF Generation

- `RequireAcceptedTerms` redirects MFA-verified users to the terms gate when they lack a valid current or still-grace-period acceptance.
- Material republishes respect the WO-10 `expires_at` notice window; non-material republishes do not force users with an active prior acceptance through the gate.
- `resources/js/pages/terms/Gate.tsx` renders the full terms document and keeps Accept disabled until the gate emits `scroll-end`.
- Accepting terms writes a `terms_acceptances` row, renders a signed PDF via the `PdfRenderer` contract, stores it on `secure_local`, and records the PDF hash through `KeyEnvelope`.
- Declining terms writes a declined acceptance, sets `users.suspended_at` / `suspended_reason = terms_declined`, sends urgent advisor/super-admin notifications, and lets the user return later to accept.
- `spatie/browsershot` is installed for the production `BrowsershotRenderer`; tests bind a fake renderer.
- Architecture doc: `docs/architecture/terms-acceptance.md`.

### WO-12 - Centralised Notifications + Channel Preferences

- `communication_preferences` stores per-user channel (`email_only`, `in_platform_only`, `both`) and frequency (`immediate`, `daily`, `weekly`) settings.
- The Laravel-compatible `notifications` ledger now records `urgency` and `channel_decision` for every `ChannelAwareNotification`.
- `ChannelResolver` routes non-urgent mail immediately or into daily/weekly digest windows, while urgent notifications bypass preferences and always route to mail plus the database ledger.
- `fsa_database` is the custom database notification channel that writes the durable routing decision for audit and future WO-24 notification UI.
- `DispatchDailyDigest` and `DispatchWeeklyDigest` are scheduled jobs backed by `DigestDispatcher` and `NotificationDigestMail`.
- Users can update communication preferences under settings.
- `TermsDeclinedUrgentNotification` now uses the central notification resolver.
- Architecture doc: `docs/architecture/notifications.md`.

### WO-13 - NZ Integration Scaffolds

- `NzbnClient`, `CompaniesOfficeClient`, and `IrdClient` contracts resolve through `IntegrationServiceProvider`.
- Fixture-backed fake clients return deterministic canned data for NZBN `9429000000000`.
- Live clients use `ResilientHttp`; when `FEATURE_*_LIVE` is off they raise `IntegrationDisabledException`, and the resolver returns the stub.
- Live mode without credentials records failure/fallback rows through the WO-05 resilience layer and returns a `stub_live_fallback` badge for UI source stamping.
- Empty interface plus fake class scaffolds exist for FSP, PPSR, LINZ, IPONZ, Stats NZ, RBNZ, MBIE, NZ Parliament, WorkSafe, Stripe, Windcave, Xero, MYOB, QuickBooks, SES/SendGrid, Whisper, Google Calendar, and Microsoft Graph.
- Architecture doc: `docs/architecture/nz-integrations.md`.

## Verification

Latest local checks:

```pwsh
composer test
npm run lint:check
npm run types:check
npm run format:check
```

Results on 2026-05-21:

- `composer test`: passed, 130 tests, 549 assertions.
- `npm run lint:check`: passed.
- `npm run types:check`: passed.
- `npm run format:check`: passed.

Note: the local test DB required using the actual local Postgres connection values from `.env` in the process environment. Do not commit local DB credentials.

## Remaining Phase 1

| WO | Title | Status | Depends on |
|---|---|---|---|
| WO-13 | NZ integration scaffolds | complete | WO-05 |
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
| ClamAV deployment plan | Production uploads | Interface exists; production daemon host/port still pending. |

## Process Reminder

- Stay on `featureApp`.
- Implement WOs in numeric order.
- Commit each completed WO directly on `featureApp` with `WO-<id>: <slug summary>`.
- WO-08 was completed before WO-07 by explicit owner request; the RBAC dependency is now closed before further role-sensitive surfaces.
- Do not invent Phase 2+ features.
- No raw secrets, unowned placeholder comments, or debug calls in shipped code.
- All AI calls go through `AiClient`.
- All future external calls go through the resilience layer introduced in WO-05.
- All sensitive upload persistence goes through `SecureFileWriter`; do not bypass scan-before-store.
