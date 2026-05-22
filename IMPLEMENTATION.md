# Future Shift Advisory - Implementation Status & Handoff

Living status document. Read alongside [`PLAN.md`](./PLAN.md) (Phase 1), [`PLAN-PHASE2.md`](./PLAN-PHASE2.md) (Phase 2), and [`CLAUDE.md`](./CLAUDE.md).

**Last updated:** 2026-05-22
**Phase:** 1 - Foundation **COMPLETE & VERIFIED** (30/30). Phase 2 - Intelligence: WO-35 complete (next: WO-36, pending owner approval).
**Plan:** Phase 1 = 30 work orders (`PLAN.md` section 8). Phase 2 = WO-31...WO-64 (`PLAN-PHASE2.md` section 8).

## Snapshot

| | |
|---|---|
| Work orders complete | **35 total** - Phase 1 complete (30/30) + Phase 2 WO-31...WO-35 complete |
| Work orders in progress | none |
| Next work order | **WO-36** - NZ economic indicators feed (pending owner approval) |
| Current branch | `featureApp` |
| Branching rule | Do not create WO branches. Commit each completed WO directly on `featureApp`. |
| Verification status | WO-35 verified locally. `composer test` passed (Pint + PHPUnit **211 tests / 1378 assertions**) against PostgreSQL `futureshift_test`; `php artisan test tests\Feature\Analysis` passed **15 tests / 133 assertions**; WO-35 targeted tests passed **2 tests / 22 assertions**; advisor/client adjacent tests passed **17 tests / 171 assertions**; `npm run lint:check`, `npm run types:check`, and `npm run format:check` all passed. |

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
| WO-13 | `684e77a` | NZ integration scaffolds | NZBN, Companies Office, and IRD clients with fixture stubs, resilience fallback, feature flags, and empty named future integration scaffolds. |
| WO-14 | `be1fa65` | Add New Client | Advisor client index/create/show flow, engagement type enum, client/team/conflict tables, NZBN auto-population, conflict gate, RLS scope update. |
| WO-15 | `a9d7be5` | Add New Entrepreneur | Basic entrepreneur profiles, advisor invite flow, invite acceptance handoff, Phase 1 placeholder portal, and advisor capacity gates. |
| WO-16 | `6437c27` | Client portal shell + onboarding wizard | Portal layout, dashboard, persisted seven-step onboarding state, server step enforcement, and engagement-type questionnaire placeholders. |
| WO-17 | `f2e0248` | Questionnaire engine | Versioned questionnaire schema, Standard Advisory seed, admin builder, conditional logic, portal renderer, and response persistence. |
| WO-18 | `241e7a8` | Document upload + verification pipeline | Upload pipeline, `DocumentVerification`, `DocumentVerifier`, `DocumentVerificationGate` (blocks outstanding advisory_flag + accuracy_discrepancy), advisor flag panel. |
| WO-19 | `11bee7c` | Data quality gate | `DataQualityScorer`/`DataQualityScore`/`DataQualitySignal`, pre-analysis gate, recompute job, profile-header score. |
| WO-20 | `7ee4902` | Wellbeing check-ins | `wellbeing_checkins` + `coaching_signals` scaffold (RLS), monthly pulse primitive, advisor-only visibility, low-score signal. |
| WO-21 | `f020e23` | Conflict declaration primitive | Reusable conflict-of-interest declaration used at client create and (future) referrals. |
| WO-22 | `d25ceca` | Structured offboarding | `offboarding_records`, artifact generation, re-engagement reminder, capacity decrement. |
| WO-23 | `fff4a5c` | Client lifecycle management | Status transitions via a single manager, audit + notification side effects, portal access gating. |
| WO-24 | `3110a32` | Notification centre | Bell + popover + `/notifications`, unread badge, urgent badging, mark-read. |
| WO-25 | `4b35703` | Threaded messaging | `message_threads`/participants/`messages`, advisor + portal threads, attachments via the document pipeline. |
| WO-26 | `fc32cb6` | Email from app | Advisor compose, channel-preference-aware send, communication-log persistence. |
| WO-27 | `5be0336` | Advisor knowledge base | `knowledge_entries`, manual entry, tsvector search, per-advisor scoping. |
| WO-28 | `18fe325` | Advisor dashboard shell | Advisor layout + dashboard with clients-health, doc-verification flags, pending T&C reacceptance, prospect inbox, integration health slots. |
| WO-29 | `a467679` | Prospect intake + triage | Signed website intake webhook (HMAC), prospect inbox, triage outcomes, invited → WO-08 invite. |
| WO-30 | `8db3c53` | API health dashboard | `integration_health_alerts`, Green/Amber/Red rollups surfaced, stuck-red (>30min) super-admin alert with idempotency. |
| WO-31 | `d13ae0c` | Analysis spine | Shared `AnalysisRunner`, analysis runs/findings/feedback tables, RLS, integrity gates, and Phase 2 architecture docs. |
| WO-32 | `3b635a4` | AI feedback capture loop | Advisor finding feedback route/UI, `FeedbackRecorder`, `learning_layer_runs`, scheduled feedback learning command, governed `learning_updates` candidates. |
| WO-33 | `b2fc7c2` | Bias detection layer | Per-analysis bias signal capture, systematic skew monitor, urgent governed alerts, and bias-monitor architecture docs. |
| WO-34 | `f71b230` | AI red-flag alerts | Critical finding promotion, urgent red-flag notifications, dashboard panel, and audited acknowledge/resolve flow. |
| WO-35 | this commit | Client knowledge assessment | Advisor-scored knowledge profile, prompt calibration injection, client detail UI, and raw leadership-gap coaching observation boundary. |

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

### WO-14 - Add New Client

- `clients`, `client_team`, and `conflict_declarations` tables are in place with RLS policies.
- `EnforceClientScope` / `RequestContext` now include `fsa.user_id` so `client_team` can safely resolve current-user client memberships.
- `EngagementType` defines the four Phase 1 engagement types used by advisor workflows.
- `PopulateFromNzbn` normalizes NZBN, Companies Office, and IRD fixture/live results for create-time auto-population and source badges.
- Advisor routes under `/advisor/clients` provide index, create, NZBN lookup, store, and show.
- Client creation requires a conflict declaration before save, records the lead advisor membership, stores registry source badges, initializes `data_quality=insufficient`, and audits `client.created` plus `conflict.declared`.
- `Client::engagementTypeIsLocked()` returns true once questionnaire responses exist, giving WO-17 the lock point without creating questionnaire tables early.
- Architecture doc: `docs/architecture/client-management.md`.

### WO-15 - Add New Entrepreneur

- `entrepreneur_profiles` stores the Phase 1 entrepreneur profile, assigned advisor, linked invite token, accepted user, concept summary, and stage.
- `EntrepreneurStage` defines the full future stage vocabulary while only `invited` and `onboarding` are reachable in Phase 1.
- Advisor routes under `/advisor/entrepreneurs` provide index, create, store, and show.
- Creating an entrepreneur issues a WO-08 invite with `target_user_type=entrepreneur`, stores the profile as `invited`, and audits `entrepreneur.created`.
- Invite acceptance links the profile to the new user, advances the stage to `onboarding`, and audits `entrepreneur.onboarding_started`.
- Entrepreneur users hitting `/dashboard` are redirected to the Phase 1 placeholder at `/portal/entrepreneur` after MFA and terms gates.
- `AdvisorEntrepreneurCapacity` enforces the default 30 active entrepreneur hard limit and exposes the 24 active entrepreneur warning threshold.
- Architecture doc: `docs/architecture/entrepreneur-profiles.md`.

### WO-16 - Client Portal Shell + Onboarding Wizard

- `clients.onboarding_wizard_state` stores current step, completed steps, per-step payloads, submission time, and update time.
- `routes/portal.php` defines the authenticated client portal dashboard and onboarding routes.
- `ClientPortalResolver` restricts portal access to `client_primary` and `client_team` users with an assigned client scope.
- `/dashboard` now redirects scoped client users to `/portal` while entrepreneur users continue to `/portal/entrepreneur`.
- `PortalLayout` provides the top navigation, notification stub, skip link, and mobile menu for portal pages.
- `portal/Dashboard` surfaces onboarding progress, data quality, notifications, messages, milestones, and referral placeholders.
- `OnboardingWizard` owns the seven-step sequence and engagement-type questionnaire placeholder mapping.
- `OnboardingController` enforces step order server-side and persists each step before moving forward.
- Architecture doc: `docs/architecture/client-portal.md`.

### WO-17 - Questionnaire Engine

- `questionnaires`, `questionnaire_sections`, `questionnaire_questions`, `questionnaire_responses`, and `questionnaire_answers` store versioned questionnaire definitions and client-scoped responses.
- `QuestionnaireSet` and `QuestionnaireQuestionType` define the supported sets and Phase 1 question types.
- `StandardAdvisoryQuestionnaireSeeder` seeds the required 10-section Standard Advisory set and covers text, long-text, number, currency, date, single-select, multi-select, file-attach, and Likert questions.
- `QuestionnaireRuleEngine` evaluates simple `when` plus `equals` / `in` conditional rules server-side; the React evaluator mirrors it for builder and portal preview.
- `QuestionnaireResponseRecorder` validates visible required questions by type, ignores hidden answers, records `attached_document_ids`, persists responses, and audits `questionnaire.submitted`.
- Super-admin routes under `/admin/questionnaires` provide list, draft, edit, preview, and publish flows with drag-and-drop ordering via `@dnd-kit/core`.
- Portal onboarding Step 5 now renders and submits the latest published Standard Advisory questionnaire while keeping Phase 3 questionnaire sets gated.
- Architecture doc: `docs/architecture/questionnaire-engine.md`.

### WO-18 - Document Upload + Verification Pipeline

- `document_verifications` stores per-document claim verification outcomes with client-scoped RLS, advisor resolution metadata, prompt hashes, and AI payloads.
- `/portal/documents` accepts client uploads and persists only through `SecureFileWriter`, preserving the WO-06 scan-before-encrypted-write path.
- `VerifyDocumentJob` builds verification claims from upload context or saved WO-17 questionnaire answers, applies system DB context for async execution, and delegates to `DocumentVerifier`.
- `DocumentVerifier` uses the `document.verify` prompt registry entry and `AiClient::verifyDocument` to populate verified, advisory, discrepancy, or error outcomes.
- Fake AI now returns deterministic document verification outcomes so clean and discrepancy paths can be tested without live credentials.
- Accuracy discrepancies create urgent advisor notifications and appear in the dashboard `DocumentVerificationFlagPanel`.
- The portal dashboard now shows document tiles with verification badges and client-facing flag explanations.
- `DocumentVerificationGate` blocks future Phase 2 analysis output while unresolved advisory or discrepancy rows exist.
- Architecture doc: `docs/architecture/document-verification.md`.

### WO-31 - Analysis Spine

- `analysis_runs`, `analysis_findings`, and `analysis_feedback` tables are in place with Postgres RLS; feedback is scoped through its parent finding.
- `AnalysisModule`, `AnalysisFindingData`, `AnalyticalFramework`, and `AnalysisRunner` define the shared Phase 2 module contract and four-lens framework.
- Analysis runs score data quality first, check the document gate before AI, build prompts through `PromptRegistry`, call `AiClient::analyse`, and re-validate attribution before findings are stored.
- Insufficient data returns `blocked_data_quality`; unresolved `advisory_flag` or `accuracy_discrepancy` rows return `blocked_documents`; missing response attribution fails the run with `analysis.integrity_violation`.
- Findings missing their own attribution are dropped and audited, while valid findings carry source attributions, uncertainty, document support, bias signals, and medium/low data-quality disclaimers.
- `AnalysisRun`, `AnalysisFinding`, and `AnalysisFeedback` models plus analysis enums are ready for WO-32 feedback capture and later analysis modules.
- Tests cover completed runs, attribution drop/fail paths, data-quality blocking, both document-block outcomes, and RLS isolation for runs, findings, and feedback.
- Architecture docs: `docs/architecture/analysis-spine.md` and `docs/architecture/schema.md`.

### WO-32 - AI Feedback Capture Loop

- Advisor feedback can now be recorded against every analysis finding with `confirm`, `correct`, `rate`, or `add_context` decisions.
- `FeedbackRecorder` is the single write path for `analysis_feedback` rows and audits each feedback event as `analysis_feedback.recorded`.
- The advisor client detail page includes recent analysis findings with attribution, document-support, uncertainty, data-quality, latest-feedback, and feedback controls.
- `learning_layer_runs` records the scheduled feedback-learning cadence, and `RunFeedbackLearningLayer` runs daily at 03:00.
- `FeedbackLearningLayer` scans correction feedback by module, creates one governed `learning_updates` candidate in `detected` status at the correction threshold, and does not create implementation rows or apply changes.
- Tests cover feedback persistence/auditing/UI props, threshold candidate creation, idempotent reruns, and no auto-implementation.
- Architecture docs: `docs/architecture/analysis-feedback-learning.md` and `docs/architecture/schema.md`.

### WO-33 - Bias Detection Layer

- `AnalysisRunner` now calls `BiasDetector` with analysis run, client, and module metadata, so completed findings carry bias signals even when a direct test AI client bypasses the production integrity wrapper.
- The per-analysis inspection writes `ai.bias_assessed` audit rows without creating duplicate heuristic learning candidates.
- `BiasMonitor` scans recent analysis findings by module and compares high/critical severity rates across `entity_type`, `engagement_type`, and `gst_registered` cohorts.
- `RunBiasMonitor` is scheduled daily at 03:15 and records layer id `3` executions in `learning_layer_runs`.
- Systematic skew creates one idempotent `learning_updates` candidate in `detected` status with `automatic_application=false`; WO-33 never edits findings or writes implementation rows.
- New urgent `BiasMonitorSignalNotification` alerts super-admins and advisors assigned to affected clients.
- Tests cover per-output signal persistence/auditing, systematic skew detection, alerting, idempotent reruns, and no auto-implementation.
- Architecture docs: `docs/architecture/bias-monitoring.md` and `docs/architecture/schema.md`.

### WO-34 - AI Red-Flag Alerts

- `red_flags` stores critical analysis alerts with client-scoped RLS, optional `analysis_finding_id`, monitor-source idempotency fields, category, severity, headline/detail, surfaced, acknowledged, and resolved timestamps.
- `RedFlagPromoter` runs from the shared `AnalysisRunner` after each finding is persisted and promotes only `critical` findings.
- Finding-derived red flags are deduped by `analysis_finding_id`; repeating promotion for the same finding does not create duplicate flags or notifications.
- `RedFlagUrgentNotification` alerts super-admins and assigned advisors through `ChannelResolver` with `urgency=urgent`, bypassing user channel/frequency preferences.
- The advisor dashboard includes an AI red flags panel scoped to the viewer's clients, with Open, Acknowledge, and Resolve actions.
- Acknowledge/resolve actions are routed through `RedFlagController` and audited as `red_flag.acknowledged` and `red_flag.resolved`.
- Tests cover critical-finding promotion, urgent preference bypass, dedupe, dashboard scoping, and audited acknowledge/resolve.
- Architecture docs: `docs/architecture/red-flags.md` and `docs/architecture/schema.md`.

### WO-35 - Client Knowledge Assessment

- `knowledge_assessments` stores advisor-recorded financial-literacy, strategic-awareness, and leadership scores with client-scoped RLS.
- `KnowledgeCalibration` derives language-depth, financial-detail, strategic-framing, and leadership-context calibration blocks from the latest assessment.
- `AnalysisRunner` injects `knowledge_calibration` into every Phase 2 analysis prompt, making the calibration visible in prompt inputs and hashes.
- The advisor client detail page includes a knowledge assessment panel for recording scores and reviewing the latest calibration labels.
- Leadership scores at or below the raw-observation threshold write a neutral `leadership_capability_gap` row to `coaching_signals` with `raw_observation_only=true` and `auto_referral=false`.
- WO-35 does not consume coaching signals, classify referrals, tune thresholds, notify coaches, or create coach referrals; that remains Phase 3.
- Tests cover prompt calibration injection, raw leadership-gap signal persistence, and the absence of Phase 2 notification/red-flag side effects.
- Architecture docs: `docs/architecture/knowledge-assessment.md` and `docs/architecture/schema.md`.

## Verification

Latest local checks:

```pwsh
composer test
npm run lint:check
npm run types:check
npm run format:check
```

Results after WO-35:

- `composer test` (Pint + PHPUnit against PostgreSQL `futureshift_test`): passed - 211 tests, 1378 assertions.
- `php artisan test tests\Feature\Analysis` (targeted analysis suite): passed - 15 tests, 133 assertions.
- `php artisan test tests\Feature\Analysis\KnowledgeAssessmentTest.php` (WO-35 targeted): passed - 2 tests, 22 assertions.
- `php artisan test tests\Feature\Clients tests\Feature\Advisor\AddClientTest.php tests\Feature\DataQuality\DataQualityGateTest.php tests\Feature\Wellbeing\WellbeingCheckinTest.php` (advisor/client adjacent coverage): passed - 17 tests, 171 assertions.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 35 distinct WO commits (WO-01...WO-35) on `featureApp`.

Note: the local test DB required using the actual local Postgres connection values via the process environment, because `.env.testing` ships Herd defaults (`herd` role / empty password) that do not authenticate against a standalone PostgreSQL install. The test database must be separate from the dev database (`RefreshDatabase` wipes it). Do not commit local DB credentials.

## Remaining Work

**Phase 1 (WO-01...WO-30) is complete and verified.** Phase 2 has started; WO-31 through WO-35 are complete. WO-36 is next, pending owner approval.

> Per-WO detail above covers WO-01...WO-18 and WO-31...WO-35; WO-19...WO-30 are summarised in the commit-log table with their commit hashes, and each shipped with its own architecture doc under `docs/architecture/` and tests. The git log and architecture docs are the authoritative per-WO record for WO-19...WO-30.

### Carryover owner inputs (deferred by design — not Phase 1 gaps; several now gate client-facing Phase 2 output)

| Item | Needed for | Status |
|---|---|---|
| Anthropic API key | Live AI (analysis degrades to deferred without it) | Optional in P1; needed early in P2 |
| NZBN / Companies Office / IRD + accounting (Xero/MYOB/QuickBooks) credentials | Live integration mode | Stubs/fixtures until arranged |
| Meridian Warm brand kit | Client-facing UI + report branding | Placeholder in `docs/brand/` |
| Lawyer-reviewed 14-clause T&C text | Anything client-facing | Placeholder in `docs/legal/terms-v1.md` |
| ClamAV production host | Production uploads | Interface ready; host/port pending |

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
- After each WO is committed, stop and ask the owner whether to proceed to the next numeric WO.
- WO-08 was completed before WO-07 by explicit owner request; the RBAC dependency is now closed before further role-sensitive surfaces.
- Do not invent Phase 2+ features.
- No raw secrets, unowned placeholder comments, or debug calls in shipped code.
- All AI calls go through `AiClient`.
- All future external calls go through the resilience layer introduced in WO-05.
- All sensitive upload persistence goes through `SecureFileWriter`; do not bypass scan-before-store.
