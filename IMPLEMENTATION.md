# Future Shift Advisory - Implementation Status & Handoff

Living status document. Read alongside [`PLAN.md`](./PLAN.md) (Phase 1), [`PLAN-PHASE2.md`](./PLAN-PHASE2.md) (Phase 2), [`PLAN-PHASE3.md`](./PLAN-PHASE3.md) (Phase 3), and [`CLAUDE.md`](./CLAUDE.md).

**Last updated:** 2026-05-23
**Phase:** 1 — Foundation **COMPLETE & VERIFIED** (30/30). Phase 2 — Intelligence **COMPLETE & VERIFIED** (34/34). Phase 3 — Engagement/Commerce/DD/Entrepreneur/Broker/Coach: **IN PROGRESS** (WO-65...WO-81 complete; next: WO-82).
**Plan:** Phase 1 = 30 WOs (`PLAN.md` §8). Phase 2 = WO-31…WO-64 (`PLAN-PHASE2.md` §8). Phase 3 = WO-65…WO-101 (`PLAN-PHASE3.md` §8).

## Snapshot

| | |
|---|---|
| Work orders complete | **81 total** - Phase 1 (30/30) + Phase 2 (34/34, WO-31...WO-64) + Phase 3 (17/37, WO-65...WO-81) |
| Work orders in progress | none |
| Next work order | **WO-82** - Entrepreneur readiness assessment (Phase 3; see `PLAN-PHASE3.md`) |
| Current branch | `featureApp` |
| Branching rule | Do not create WO branches. Commit each completed WO directly on `featureApp`. |
| Verification status | **Phase 2 reviewed & confirmed complete (2026-05-23).** WO-65...WO-81 targeted verification passed against PostgreSQL `futureshift_test`; Pint dirty check, ESLint, `tsc --noEmit`, and Prettier are green. |

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
| WO-35 | `f25fd93` | Client knowledge assessment | Advisor-scored knowledge profile, prompt calibration injection, client detail UI, and raw leadership-gap coaching observation boundary. |
| WO-36 | `62bbb24` | NZ economic indicators feed | RBNZ/Stats NZ/MBIE fixture/live clients, persisted economic indicators and exchange rates, scheduled refresh, OCR-change learning candidate, dashboard tile. |
| WO-37 | `450eec1` | Accounting API integration | Xero/MYOB/QuickBooks OAuth connection flow, encrypted token envelopes, append-only financial snapshots, manual pull/revoke UI, fixture and live fallback coverage. |
| WO-38 | `316ca38` | Continuous financial health monitoring | Scheduled daily/weekly accounting pulls, snapshot deterioration detection, `financial_alerts`, exact metric citations, and ChannelResolver notification routing. |
| WO-39 | `836ac85` | Valuation multiple data feed | NZ-benchmarked EBITDA/SDE multiples by industry, active-row supersession, quarterly refresh, and governed learning candidates. |
| WO-40 | `531c805` | PV engine + discount-rate methods | Shared PV calculation ledger, discounting math, OCR-linked/industry/advisor/client discount methods, and attribution contract. |
| WO-41 | `ab4a317` | Business valuation | SDE multiple, EBITDA multiple, DCF with terminal value, reconciled range, adjustments, and accounting/questionnaire input fallback. |
| WO-42 | `53b0171` | Improvement and risk PV | Improvement-opportunity PV, risk-cost PV, statutory penalty range application, ranking, and finding linkage. |
| WO-43 | `bac642a` | PV integration + waterfall chart | Dashboard/report-ready PV waterfall assembly, advisor dashboard baseline/target PV surface, reusable React chart, and server-side report chart partial. |
| WO-44 | `fd3b5eb` | Financial analysis module | Spine-native financial analysis over accounting snapshots, questionnaire fallback disclaimer, NZ economic overlay, and improvement-PV linkage. |
| WO-45 | `2776fa5` | Website audit module | Spine-native website audit for SEO, content, UX, CTAs, mobile performance, NZ search context, and document-gate enforcement. |
| WO-46 | `817fe18` | Competitor analysis module | Spine-native competitor product, pricing, visibility, and gap analysis with six-competitor input bound. |
| WO-47 | `ca6288b` | SWOT/TOWS/MAPS module | Strategic matrix assembler, spine-native SWOT/TOWS/MAPS analysis, reusable React matrix, and PV-referenced priorities. |
| WO-48 | `0281ee3` | HR and people analysis | Spine-native HR analysis with wage benchmarking, verified HR-document support, and Holidays Act liability quantification. |
| WO-49 | `0b0ee88` | Operational analysis + systems review | Spine-native operational bottleneck and systems integration analysis modules with cited findings. |
| WO-50 | `37825fb` | NZ compliance checker + legislative currency | Spine-native compliance checker with statute citations, verified document support, and governed legislative-currency candidates. |
| WO-51 | `f7464e8` | Regulatory change impact assessment | Legislative-change candidates produce client impact findings with compliance actions and risk-cost PV linkage. |
| WO-52 | `b26cf77` | Insurance risk flags | Spine-native insurance risk module flags coverage gaps with verified certificate support for future broker referral. |
| WO-53 | `e45c25a` | Scenario planning | Five named scenarios with economic overlay, per-scenario PV impact, RLS, and advisor/client read-only dashboard visibility. |
| WO-54 | `e92d47f` | Succession planning | Exit-readiness scoring, assessed options, owner-dependency plan, target exit PV, RLS, and raw owner-readiness coaching observation. |
| WO-55 | `dd92f8e` | Fee calculator | Hours-based, outcome-based, and entrepreneur fee suggestions with PV-referenced ROI and RLS. |
| WO-56 | `2b1764a` | Proposal generation | Branded fee proposals with consent elections, release/recall, expiry, renewal, and reserved signature-state guards. |
| WO-57 | `e7d0eab` | Report engine | Shared report composer with Client/Advisor reports, integrity notation, redaction rules, and branded PDFs. |
| WO-58 | `d3a65c9` | Stakeholder report export | Stakeholder redaction profile with liability disclaimer and PDF/PowerPoint artifacts. |
| WO-59 | `34bef91` | Business health trajectory report | Start-to-current financial trends, PV milestones, generated narrative, and advisor review gate. |
| WO-60 | `152d38a` | Industry briefings + pre-meeting brief | Monthly NZ-sourced briefings, local meetings, 24-hour pre-meeting briefs, review/send gates, and scheduler commands. |
| WO-61 | `2b0bd7c` | Funnel analytics | Funnel event ledger, onboarding/questionnaire/proposal capture, advisor dashboard drop-off panel, and governed UX-improvement candidates. |
| WO-62 | `acc1cf3` | Practice health report | Active-client PV portfolio, revenue under management, advisor/super-admin scoping, monthly cached snapshots, and dashboard summary. |
| WO-63 | `72fcd95` | Advisor dashboard Phase 2 panels | Proposal status/expiry panel, questionnaire optimisation candidates, and completed Phase 2 dashboard composition. |
| WO-64 | `979c6d0` | Wellbeing monthly pulse analytics | Advisor wellbeing analytics, duplicate-safe raw low-coping observation, and Phase 2 coaching-boundary documentation. |
| WO-65 | `5c0b1ee` | Goals & milestones tracker | Client-scoped goals, milestones, actions, proof-of-completion verification, PV-realised dashboard payloads/UI, and RLS coverage. |
| WO-66 | `195bd78` | Digital proposal sign-off flow | Seven-step portal sign-off, tokenised payment-authority capture, signed evidence, sign-off-only proposal status transitions, and RLS coverage. |
| WO-67 | `c86fef1` | Payment schedules | Signed-proposal payment schedules, one-off/monthly cadence, authority revocation cascade, audit events, and RLS coverage. |
| WO-68 | `67eeda8` | Stripe + Windcave live integration | Live/fallback gateway clients, fixture charge contract, primary-to-secondary failover, double-failure notification, PAN rejection, and signed webhooks. |
| WO-69 | `db7d18b` | Monthly payment processing + receipts | Due-schedule processing, payment attempt ledger, retry/failover handling, receipt PDFs, failed-payment notifications, and signed-status invariance. |
| WO-70 | `5102104` | Panel portal foundation | Shared broker/coach panel onboarding, signed agreement gate, referral lifecycle, per-referral messages, reverse referrals, portal layout, and RLS isolation. |
| WO-71 | `f446ab5` | Insurance Broker portal | FSP fixture/live/fallback validation, approval-time FSP gate, periodic lapse suspension, advisor alerts, and broker referral stages. |
| WO-72 | `164ce7f` | Coach portal | Five coach specialisations, admin-managed vetting, wellbeing scope-boundary clauses, coach referral stages, key-staff authorisations, and entrepreneur coach referrals. |
| WO-73 | `bdd0548` | Coaching referral signal detection | Raw coaching signals mapped to advisor suggestions, dashboard panel, no auto-referrals, and governed calibration candidates only. |
| WO-74 | `48d5256` | Referral conflict + consent wiring | Fresh conflict and active-consent send gate, referral consent grants/revokes, and consent-revocation withdrawal cascade. |
| WO-75 | `88e330c` | DD onboarding + acquisition target | DD engagement onboarding, acquisition-target isolation, DD-specific questionnaire seed, conflict gate, advisor target panel, and liability disclaimer. |
| WO-76 | `b28f32f` | DD virtual data room + guest upload | Workstream-scoped DD artifact ledger, token-only guest links, upload-only public endpoint, secure scan-before-store persistence, instant revocation, and audit coverage. |
| WO-77 | `36f5c5a` | DD eight workstreams | Eight DD workstreams run on the analysis spine with scoped evidence gates, double-weighted verified documents, NZ register/statute checks, and per-workstream pause handling. |
| WO-78 | `e64a553` | DD valuation + FX normalisation | DD wrapper over Phase 2 business valuation/PV, target-financial isolation, RBNZ FX normalisation to NZD, +/-10% sensitivity, and buyer negotiating position. |
| WO-79 | `cd1267f` | DD business plan builder | Shared five-phase plan engine, DD findings auto-population, acquisition-proceeding completeness gate, founding advisory payload, owner XOR, and RLS coverage. |
| WO-80 | `19711f1` | DD report generation | DD report composer path, PV-ranked risk register, price-adjustment schedule, 100-day integration plan, recommendation logic, disclaimer, PDF/PPTX, and RLS coverage. |
| WO-81 | this commit | Post-acquisition advisory pipeline | Acquisition-proceeding conversion, advisory client creation, DD document migration labels, gap questionnaire prefill, DD-baseline proposal, and RLS coverage. |

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

### WO-36 - NZ Economic Indicators Feed

- `economic_indicators` stores OCR, CPI, GDP, unemployment, minimum wage, and living wage values by source and period.
- `exchange_rates` stores NZD exchange rates by currency pair, date, and source.
- RBNZ, Stats NZ, and MBIE clients now have fixture, live, and fallback implementations using the WO-05 resilience layer.
- `economic-indicators:refresh` persists the feed, records layer id `12` runs, audits refreshes, and is scheduled daily at 03:30.
- Live mode without credentials degrades through `ResilientHttp`, records failure/fallback rows, and returns cached or fixture-backed data with source badges.
- OCR changes create governed `learning_updates` candidates for PV discount-rate review with `automatic_application=false`; no PV/WACC logic is applied in WO-36.
- The advisor dashboard includes an economic indicators panel showing latest values, exchange rates, source badges, degraded state, and OCR-change alerts.
- Tests cover fixture refresh, live fallback, idempotent refresh, OCR-change candidate creation, no auto-implementation, and dashboard surfacing.
- Architecture docs: `docs/architecture/economic-indicators.md` and `docs/architecture/schema.md`.

### WO-37 - Accounting API Integration

- `accounting_connections` stores client/provider OAuth connection state with encrypted token envelopes and token metadata from `KeyEnvelope`.
- `financial_snapshots` stores append-only P&L, balance sheet, cash flow, and metrics payloads stamped with provider source, source badge, degraded state, and correlation id.
- Xero, MYOB, and QuickBooks contracts now support token exchange, financial snapshot pull, and revoke; fake/live/fallback implementations follow the WO-05 `ResilientHttp` pattern.
- `AccountingConnector` signs OAuth state, validates callbacks, encrypts tokens, revokes prior active same-provider connections, and audits connect/revoke events.
- `FinancialSnapshotPuller` decrypts tokens, writes immutable snapshots, updates connection `last_snapshot_at`, and audits snapshot pulls.
- Advisor client detail now includes provider connect buttons, connection state, manual Pull/Revoke actions, and latest snapshot metrics.
- Live mode without credentials records failure/fallback rows through the integration ledger and returns fixture-backed degraded data.
- Tests cover mocked OAuth callback, encrypted token storage, fixture snapshot pull, append-only snapshot enforcement, live fallback logging, revoke, and client show props.
- Architecture docs: `docs/architecture/accounting-integration.md` and `docs/architecture/schema.md`.

### WO-38 - Continuous Financial Health Monitoring

- `financial_alerts` stores client-scoped early-warning alerts tied to the previous/current accounting snapshots that triggered them.
- `RunFinancialMonitoring` pulls connected accounting providers on demand or through the scheduler when `FEATURE_CONTINUOUS_MONITORING` is enabled.
- `HealthMonitor` applies system RLS context, pulls new snapshots through `FinancialSnapshotPuller`, compares consecutive snapshots, and audits completed runs and pull failures.
- Deterioration rules cover revenue, net profit, operating cash flow, gross margin, and current ratio thresholds from `integrations.accounting.monitoring`.
- Alerts carry exact `financial_snapshot:{id}:{path}` source references in `citation`, plus human-readable details with previous/current figures and period ends.
- `FinancialAlertNotification` routes super-admin and assigned advisor alerts through `ChannelResolver`; normal urgency respects channel/frequency preferences.
- Tests cover deterioration detection with citations, stable data with no false alert, notification channel routing, and feature-flag command gating.
- Architecture docs: `docs/architecture/financial-monitoring.md` and `docs/architecture/schema.md`.

### WO-39 - Valuation Multiple Data Feed

- `valuation_multiples` stores global NZ reference-data rows for industry-level EBITDA and SDE low/mid/high ranges.
- The MBIE integration client now exposes `valuationMultiples()` with fixture, live, and fallback paths using the existing resilience layer.
- Fixture data includes MBIE and NZ Business Brokers rows; production live-source licensing remains an owner input.
- `ValuationMultipleRefresher` imports active rows, uses a `record_hash` for idempotency, and marks prior active rows for the same industry, metric, and source with `superseded_at`.
- `valuation-multiples:refresh` runs manually or on the quarterly scheduler and records layer id `13` in `learning_layer_runs`.
- New active rows create governed `learning_updates` candidates with `automatic_application=false`; WO-39 never performs valuation calculations or mutates PV assumptions.
- `ValuationMultipleProvider` exposes `lookup()` and `rangeFor()` for WO-41 business valuation work, including `valuation_multiple:{id}` source references.
- Tests cover lookup, supersession, candidate idempotency, and no auto-implementation.
- Architecture docs: `docs/architecture/valuation-multiples.md` and `docs/architecture/schema.md`.

### WO-40 - PV Engine + Discount-Rate Methods

- `pv_calculations` stores client-scoped present-value calculations with input payloads, result payloads, discount-rate rationale, and source attributions.
- `PvType` and `DiscountMethod` enums define the Phase 2 PV contract.
- `DiscountRateResolver` supports OCR-linked, industry WACC, advisor-configured, and client-inputted rates.
- OCR-linked discounting resolves the latest persisted OCR economic indicator and cites the exact `economic_indicator:{id}` source reference, so new OCR rows alter future rates automatically.
- `PvEngine` discounts cash flows, calculates terminal value through Gordon growth, persists calculations, and audits `pv_calculation.created`.
- WO-40 does not implement business valuation, improvement opportunities, or risk costs; those use this foundation in WO-41 and WO-42.
- Tests cover all four discount methods, OCR auto-update behavior, PV math, persisted calculations, and attribution presence.
- Architecture docs: `docs/architecture/pv-foundation.md` and `docs/architecture/schema.md`.

### WO-41 - Business Valuation

- `business_valuations` stores client-scoped SDE multiple, EBITDA multiple, DCF, reconciled low/mid/high range, adjustments, and source attributions.
- `BusinessValuation` prefers latest connected accounting snapshots and falls back to advisor/questionnaire financial inputs with a data-quality disclaimer.
- SDE and EBITDA method values come from active WO-39 `valuation_multiples` rows.
- DCF uses the WO-40 `PvEngine`, records terminal value, and links the valuation back to its `pv_calculation_id`.
- Reconciliation averages the three valuation methods and applies explicit advisor adjustments with label, amount, and rationale.
- Tests cover three-method calculation, reconciliation, adjustment application, accounting source preference, and questionnaire fallback disclaimer.
- Architecture docs: `docs/architecture/business-valuation.md` and `docs/architecture/schema.md`.

### WO-42 - Improvement Opportunity + Risk Cost PV

- `improvement_opportunities` stores PV Type 2 rows with annual benefit, duration, `pv_of_impact`, rank, and optional `analysis_finding_id`.
- `risk_costs` stores PV Type 3 rows with financial impact, probability, duration, statutory penalty range, applied impact, annual expected cost, `pv_of_cost`, rank, and optional `analysis_finding_id`.
- `ImprovementPv` writes one PV calculation per opportunity and ranks opportunities by descending PV impact.
- `RiskCostPv` applies statutory penalty range midpoints when they exceed the supplied financial impact, then ranks risks by descending PV cost.
- Both services preserve source attributions and link rows back to originating analysis findings when provided.
- Tests cover ranking, statutory range application, and finding linkage.
- Architecture docs: `docs/architecture/pv-impact-types.md` and `docs/architecture/schema.md`.

### WO-43 - PV Integration + Waterfall Chart

- `PvWaterfallBuilder` assembles visible-client PV baselines from latest `business_valuations.reconciled_mid`, summed improvement PV, and summed risk-cost mitigation value.
- The builder emits both dashboard summary totals and per-client waterfall steps: current PV, improvements, risk mitigation, and target PV.
- The advisor dashboard Inertia payload now includes `pvWaterfall`, and the dashboard renders current/target badges plus the first available client waterfall chart.
- `resources/js/components/pv/WaterfallChart.tsx` provides the reusable client-side chart surface for dashboard use.
- `PvWaterfallReportChart` renders the same waterfall steps through a Blade partial for future Browsershot/PDF report embedding.
- WO-43 adds no schema; it reads the PV tables created by WO-40 through WO-42.
- Tests cover waterfall data reconciliation, dashboard payload assembly, and server-side report chart rendering.
- Architecture docs: `docs/architecture/pv-integration.md` and `docs/architecture/schema.md`.

### WO-44 - Financial Analysis Module

- `FinancialAnalysis` implements the shared `AnalysisModule` contract with prompt id `analysis.financial`.
- Snapshot-backed runs read the latest `financial_snapshots` row, calculate profitability, cash-flow, driver, ratio, and liquidity context, and cite exact metric paths.
- The module overlays latest OCR, CPI, GDP, and unemployment indicators when present and cites the underlying `economic_indicators` rows.
- When no accounting snapshot exists, the module falls back to questionnaire answers and stamps each finding with a no-accounting-snapshot disclaimer.
- `FinancialAnalysisRunner` wraps the generic spine runner and links the prescriptive financial finding into WO-42 improvement PV, writing the resulting opportunity id to `analysis_findings.pv_link_id`.
- WO-44 adds no schema; it reuses analysis, accounting, economic, and PV tables from earlier WOs.
- Tests cover spine execution with fixture accounting/economic data, source attribution, document-support stamping, questionnaire fallback disclaimer, and finding-to-PV linkage.
- Architecture docs: `docs/architecture/financial-analysis.md` and `docs/architecture/schema.md`.

### WO-45 - Website Audit Module

- `WebsiteAudit` implements the shared `AnalysisModule` contract with prompt id `analysis.website_audit`.
- The module reads website, SEO, mobile, search, CTA, landing-page, and enquiry evidence from questionnaire answers.
- Findings cover SEO/content/UX/CTA/mobile diagnostics, NZ search visibility risk, and a prescriptive website action plan across the four analysis lenses.
- Every finding carries questionnaire-answer citations and `document_support=none`; unresolved document verification flags still block the run before output.
- WO-45 adds no schema and does not crawl live websites or perform continuous search monitoring.
- Tests cover successful module execution with cited findings and document-verification gate enforcement.
- Architecture docs: `docs/architecture/website-audit.md` and `docs/architecture/schema.md`.

### WO-46 - Competitor Analysis Module

- `CompetitorAnalysis` implements the shared `AnalysisModule` contract with prompt id `analysis.competitor`.
- The module reads competitor product, pricing, visibility, and market-gap evidence from questionnaire answers.
- The input mapper enforces the spec limit of up to six competitors before prompt construction.
- Findings cover competitor set description, product/pricing/visibility diagnostics, visibility trajectory risk, and a gap action plan.
- Every finding carries questionnaire-answer citations and `document_support=none`.
- WO-46 adds no schema and does not perform live competitor monitoring or scraping.
- Tests cover successful module execution with cited gap findings and the six-competitor bound.
- Architecture docs: `docs/architecture/competitor-analysis.md` and `docs/architecture/schema.md`.

### WO-47 - SWOT/TOWS/MAPS Module

- `StrategicMatrixAssembler` builds deterministic SWOT, TOWS, MAPS, PV summary, and attribution payloads from questionnaire answers, recent governed findings, and top PV rows.
- `StrategicMatrices` implements the shared `AnalysisModule` contract with prompt id `analysis.strategic_matrices` and module enum `swot`.
- Findings cover SWOT, TOWS, MAPS, and a PV-referenced strategic priority across the four analysis lenses.
- Prescriptive findings set `analysis_findings.pv_link_id` to the top improvement or risk PV row when available.
- `resources/js/components/analysis/StrategicMatrix.tsx` adds a reusable matrix renderer for future advisor/report surfaces.
- WO-47 adds no schema and does not create new PV calculations.
- Tests cover matrix assembly, cited findings, and PV-linked strategic priority persistence.
- Architecture docs: `docs/architecture/strategic-matrices.md` and `docs/architecture/schema.md`.

### WO-48 - HR And People Analysis

- `HrAnalysis` implements the shared `AnalysisModule` contract with prompt id `analysis.hr`.
- The module reads HR, people, staff, wage, CV, JD, and Holidays Act evidence from questionnaire answers.
- Latest `minimum_wage` and `living_wage` economic indicators are used for wage benchmarking with cited source rows.
- Verified `hr_record` documents stamp HR findings with `document_support=verified` and document citations; unresolved HR document flags still block through the shared gate.
- `HolidaysActLiabilityCalculator` quantifies underpaid hours, gross exposure, remediation buffer, and total estimated liability.
- Findings cover staff structure, wage compliance, Holidays Act liability, and a people remediation plan across the four analysis lenses.
- WO-48 adds no schema and does not replace legal/advisor review of employment-law exposure.
- Tests cover wage compliance, verified HR-document cross-reference, and Holidays Act liability calculation.
- Architecture docs: `docs/architecture/hr-analysis.md` and `docs/architecture/schema.md`.

### WO-49 - Operational Analysis + Systems Review

- `OperationalAnalysis` implements the shared `AnalysisModule` contract with prompt id `analysis.operational`.
- `SystemsReview` implements the shared `AnalysisModule` contract with prompt id `analysis.systems`.
- Operational findings cover SOPs, process flow, bottlenecks, capacity trajectory, and automation opportunities.
- Systems findings cover technology gaps, integrations, manual workarounds, data-quality risk, and upgrade sequencing.
- Both modules use questionnaire-answer citations and emit all four analysis lenses through the shared spine.
- WO-49 adds no schema and does not create vendor recommendations, procurement flows, or automated monitoring.
- Tests cover successful operational and systems module runs with cited diagnostic findings.
- Architecture docs: `docs/architecture/operational-systems-analysis.md` and `docs/architecture/schema.md`.

### WO-50 - NZ Compliance Checker + Legislative Currency

- `ComplianceChecker` implements the shared `AnalysisModule` contract with prompt id `analysis.compliance`.
- The checker covers Employment Relations Act, Health and Safety at Work Act, Holidays Act, Privacy Act, and Companies Act signals.
- Compliance findings carry questionnaire/document citations plus statute references such as `statute:nz:era`.
- Verified compliance, HR, contract, and insurance documents stamp findings with `document_support=verified`; unresolved document flags still block through the shared gate.
- NZ Parliament, WorkSafe, and IRD feed contracts now expose legislative-change data through fixture clients.
- `LegislativeCurrencyMonitor` records layer id `14` runs and creates governed `learning_updates` candidates with `automatic_application=false`.
- `legislative-currency:monitor` runs the monitor and is idempotent by change key.
- WO-50 adds no schema and does not implement DD regulatory workstreams or auto-apply law changes.
- Tests cover severity rating, statute citation, verified document cross-reference, candidate creation, idempotency, and no auto-implementation.
- Architecture docs: `docs/architecture/compliance-legislative-currency.md` and `docs/architecture/schema.md`.

### WO-51 - Regulatory Change Impact Assessment

- `RegulatoryImpactAssessor` converts a governed legislative-currency `learning_update` into a client-specific regulatory-impact run.
- The assessor writes a completed `analysis_runs` row with module `regulatory_impact` and a prescriptive `analysis_findings` row.
- Recommended compliance actions are recorded directly in the finding body because the milestones/action tracker remains Phase 3.
- Financial exposure is routed through WO-42 `RiskCostPv`, and the finding stores the created risk row id in `pv_link_id`.
- Findings cite the source `learning_update` and retained legislative source URL.
- WO-51 adds no schema and does not auto-create Phase 3 compliance action items.
- Tests cover impact generation, recommended actions, risk-cost row creation, and PV linkage.
- Architecture docs: `docs/architecture/regulatory-impact.md` and `docs/architecture/schema.md`.

### WO-52 - Insurance Risk Flags

- `InsuranceRiskFlags` implements the shared `AnalysisModule` contract with prompt id `analysis.insurance_risk`.
- The module reads insurance, coverage, certificate, policy, public-liability, professional-indemnity, and key-person evidence from questionnaire answers.
- Verified `insurance_certificate` documents stamp findings with `document_support=verified` and document citations.
- Coverage gaps flag missing or low public liability, missing professional indemnity, missing key person cover, and expired certificate evidence.
- Flags are recorded as governed analysis findings for future broker-referral workflows.
- WO-52 adds no schema and does not create broker referrals.
- Tests cover gap detection, certificate verification support, and document citations.
- Architecture docs: `docs/architecture/insurance-risk-flags.md` and `docs/architecture/schema.md`.

### WO-53 - Scenario Planning

- `scenarios` stores client-scoped best/expected/worst/custom scenario rows with assumptions, economic overlay, PV impact, position, visibility, and RLS.
- `ScenarioPlanner` creates a `scenario` analysis run, enforces the five-scenario bound, applies the shared data-quality and document-verification gates, snapshots OCR/CPI/GDP/unemployment, and routes every scenario through `PvEngine`.
- Scenario PV uses the shared PV ledger without creating `improvement_opportunities` rows, so hypothetical scenarios do not alter WO-43 waterfall totals.
- The advisor dashboard surfaces a scoped scenario-planning panel, and the client portal shows only `is_client_visible` scenarios in a read-only list with PV impact and overlay context.
- Tests cover the five-scenario bound, PV per scenario, economic overlay application, client-portal visibility, and scenario RLS isolation.
- Architecture docs: `docs/architecture/scenario-planning.md` and `docs/architecture/schema.md`.

### WO-54 - Succession Planning

- `succession_plans` stores client-scoped exit-readiness outputs with assessed options, owner-dependency actions, target exit PV, creator, and RLS.
- `SuccessionPlanner` creates a `succession` analysis run, applies the shared data-quality and document-verification gates, scores exit readiness from owner, management, process, financial, and timeline inputs, and routes target exit PV through `PvEngine`.
- Target exit PV uses the shared `pv_calculations` ledger without creating a current `business_valuations` row, so it does not alter WO-43 current valuation/waterfall surfaces.
- When owner readiness is the primary constraint, the planner writes a raw `coaching_signals.owner_readiness_primary_constraint` observation with `raw_observation_only=true` and `auto_referral=false`; Phase 2 does not consume it for referral logic.
- Tests cover readiness scoring, options persistence, target exit PV attribution, raw coaching-signal boundary, no Phase 2 notification/red-flag side effect, and succession-plan RLS isolation.
- Architecture docs: `docs/architecture/succession-planning.md` and `docs/architecture/schema.md`.

### WO-55 - Fee Calculator

- `fee_calculations` stores client-scoped fee suggestions with method, inputs, low/mid/high range, improvement PV total, risk-cost PV total, ROI ratio, structured justification, and RLS.
- `FeeCalculator` supports hours-based fees from per-service hours and rates, with optional retainer conversion.
- Outcome-based fees directly reference current improvement PV, risk-cost PV, annual revenue, and complexity in the persisted justification.
- `roi_ratio` is calculated as `improvement_pv_total / suggested_mid`, matching the Phase 2 requirement that ROI is improvement-PV based.
- The entrepreneur method is a distinct lower-entry path and deliberately stops before payment collection or proposal signature workflow.
- Tests cover all three methods, PV-referenced outcome justification, ROI ratio, entrepreneur lower-entry structure, and fee-calculation RLS isolation.
- Architecture docs: `docs/architecture/fee-calculator.md` and `docs/architecture/schema.md`.

### WO-56 - Proposal Generation

- `proposals` stores client-scoped fee proposal artifacts with scope, services, PV summary, ROI ratio, PDF metadata, lifecycle timestamps, versioning, and renewal lineage.
- `consents` stores insurance and coach referral elections per proposal; Phase 2 captures elections only and does not create referrals.
- `ProposalBuilder` generates branded PDFs, writes consent rows, releases proposals with a configurable default 30-day expiry window, recalls released proposals, expires due proposals, and renews expired proposals into new versions.
- WO-66 replaces the earlier forward-compatibility guard with a dedicated sign-off flow for `awaiting_signature` and `signed`.
- `proposals:expire` is scheduled daily with overlap protection.
- The advisor client detail page has a thin proposal panel for generating from recent fee calculations and manually releasing, recalling, or renewing proposals.
- Tests cover generation, PDF storage, route payload, release/recall, scheduled expiry, renewal, audit writes, reserved-state blocking, and proposal/consent RLS isolation.
- Architecture docs: `docs/architecture/proposals.md` and `docs/architecture/schema.md`.

### WO-57 - Report Engine

- `reports` stores client-scoped generated report headers with type, title, PDF metadata, generator, generated timestamp, and redaction/scaffold metadata.
- `report_sections` stores ordered section content with source attributions, document-support notation, and data-quality notes on every section.
- `ReportType` defines Client, Advisor, Stakeholder, Trajectory, Due Diligence, and Entrepreneur Assessment report types; WO-57 composes Client and Advisor only.
- `ReportComposer` builds branded PDFs from persisted findings, PV waterfall, latest valuation, and latest proposal data through `PdfRenderer`.
- Client reports include valuation and non-prescriptive findings only, excluding recommendations, implementation plan, fee detail, and proposal ROI.
- Advisor reports include all findings, PV waterfall chart HTML, implementation plan, and the latest proposal fee/ROI.
- The advisor client detail page can generate Client or Advisor reports and lists recent reports; the client portal shows client-report summaries only.
- Tests cover composition, client redaction, advisor completeness, required section notation, route/payload behavior, portal filtering, audit writes, and report/section RLS isolation.
- Architecture docs: `docs/architecture/reports.md` and `docs/architecture/schema.md`.

### WO-58 - Stakeholder Report Export

- `reports` now records `pptx_path` and `pptx_byte_size` for PowerPoint exports.
- `PptxGenerator` defines the PowerPoint rendering contract, with `OpenXmlPptxGenerator` producing a simple Open XML `.pptx` artifact from report sections.
- `ReportComposer` now composes `stakeholder` reports, applies `fsa_methodology` and `fsa_ip` redaction metadata, excludes advisor-only fee/methodology content, and stores both PDF and PPTX artifacts.
- Stakeholder reports include a dedicated liability disclaimer section in both export formats.
- The advisor report panel includes one-click Stakeholder generation; client portal report summaries remain client-report only.
- Tests cover stakeholder IP/methodology redaction, liability disclaimer presence, PDF/PPTX storage, route generation, and portal filtering.
- Architecture docs: `docs/architecture/stakeholder-report.md`, `docs/architecture/reports.md`, and `docs/architecture/schema.md`.

### WO-59 - Business Health Trajectory Report

- `reports` now records `review_status`, `reviewed_by_user_id`, and `reviewed_at` so trajectory reports have an explicit advisor-review gate before sharing.
- `ReportComposer` composes `trajectory` reports from start-to-current financial snapshots, PV valuation milestones, current findings, and a generated trajectory narrative with data-quality notation.
- The trajectory narrative is marked advisor-review-required in section metadata, and review completion writes an immutable `report.reviewed` audit event.
- The advisor client report panel can generate Trajectory reports and mark pending trajectory reports reviewed.
- Tests cover trend assembly, PV milestone inclusion, narrative review notation, advisor review completion, and audit persistence.
- Architecture docs: `docs/architecture/trajectory-report.md`, `docs/architecture/reports.md`, and `docs/architecture/schema.md`.

### WO-60 - Industry Intelligence Briefings + Pre-Meeting Brief

- `meetings` stores minimal advisor-entered meeting records with client-scoped RLS; full Google/Outlook calendar sync remains Phase 4.
- `industry_briefings` stores monthly per-client draft briefings with NZ economic source citations, advisor review metadata, send state, and RLS.
- `pre_meeting_briefs` stores one briefing per meeting with the 24-hour scheduler window, red-flag IDs, generated/reviewed/sent metadata, and RLS.
- `IndustryBriefingGenerator` and `PreMeetingBriefGenerator` assemble deterministic, source-linked briefing bodies from existing Phase 2 evidence and route reviewed outputs through `ChannelResolver` notifications.
- The advisor client detail page includes a meeting form plus briefing review/send controls.
- Scheduler commands `briefings:generate-monthly` and `briefings:generate-pre-meeting` are registered with overlap protection.
- Tests cover briefing generation, review gates, notification routing, 24-hour trigger behavior, duplicate prevention, route payloads, and RLS isolation.
- Architecture docs: `docs/architecture/briefings.md` and `docs/architecture/schema.md`.

### WO-61 - Funnel Analytics

- `funnel_events` records flow, step, client/user context, entry time, completion time, and abandonment state with RLS.
- `FunnelTracker` captures step entry/completion, marks stale open entries abandoned, computes drop-off summaries, and feeds the advisor dashboard.
- Onboarding step views/saves, questionnaire submission, and proposal generation/release now write funnel events.
- The advisor dashboard includes a funnel analytics panel with event, abandonment, completion, and drop-off metrics.
- `analytics:funnel-learning` runs monthly and queues a governed `learning_updates` candidate in `detected` status for the highest drop-off step; no UX change is auto-applied.
- Tests cover event capture, abandonment, scoped dashboard summaries, governed candidate emission, no auto-implementation, and RLS isolation.
- Architecture docs: `docs/architecture/funnel-analytics.md` and `docs/architecture/schema.md`.

### WO-62 - Practice Health Report

- `practice_health_snapshots` caches monthly portfolio-health metrics with advisor-scoped RLS; super-admin/system can see all snapshots, advisors can see only their own rows.
- `PracticeHealthReport` aggregates active-client current PV, improvement PV, risk-mitigation PV, target PV, revenue under management, proposal/report counts, red flags, and funnel summary signals.
- `practice-health:snapshot --all-advisors` creates the super-admin practice snapshot plus advisor, junior-advisor, and entrepreneur-mentor portfolio snapshots; it is scheduled monthly.
- The advisor dashboard now receives a `practiceHealth` payload and displays active-client count, target PV, revenue under management, proposal/report counts, red flags, and top client portfolio rows.
- Tests cover active-client portfolio aggregation, advisor versus super-admin scoping, monthly snapshot caching, dashboard payload delivery, and RLS isolation.
- Architecture docs: `docs/architecture/practice-health-report.md` and `docs/architecture/schema.md`.

### WO-63 - Advisor Dashboard Phase 2 Panels

- The advisor dashboard now includes a proposal status panel with Phase 2 status counts and released-proposal expiry alerts for the next 14 days.
- Proposal panel data is assembled from live `proposals` rows and scoped through the same visible-client resolver used by the existing dashboard widgets.
- The dashboard now includes a questionnaire optimisation panel backed by governed `learning_updates` candidates.
- `QuestionnaireOptimisationLayer` runs as layer 16, scans recent submitted questionnaire responses for high blank/omitted answer rates, and queues `detected` candidates only; no questionnaire changes are auto-applied.
- `questionnaires:optimisation-learning` is scheduled quarterly with overlap protection.
- Tests cover scoped dashboard proposal/candidate payloads and governed questionnaire candidate emission with no auto-implementation.
- Architecture docs: `docs/architecture/dashboard-phase2.md` and `docs/architecture/schema.md`.

### WO-64 - Wellbeing Monthly Pulse + Analytics

- `WellbeingTrendAnalytics` aggregates advisor-visible six-month wellbeing trends without exposing client notes in the dashboard payload.
- The advisor dashboard now includes wellbeing check-in count, average business confidence, average personal coping, low-coping count, current-month completion rate, and raw signal count.
- `CoachingSignalDetector` now suppresses duplicate rows for a continuing low-personal-coping streak so the fixed Phase 2 observation creates one raw `coaching_signals` row only.
- The raw low-coping signal evidence marks `auto_referral = false` and `phase_2_boundary = raw_internal_observation_only`; Phase 2 still has no coach calibration, referral, or consumer.
- The existing monthly prompt command remains scheduled and covered, sending optional pulse prompts only to due client portal users.
- Tests cover monthly prompts, advisor-only visibility, scoped dashboard analytics, the one-signal streak rule, and no Phase 2 learning/referral consumer.
- Architecture docs: `docs/architecture/wellbeing-analytics.md` and `docs/architecture/schema.md`.

### WO-65 - Goals & Milestones Tracker

- Added client-scoped `goals`, `milestones`, `milestone_actions`, and `proof_of_completion` tables with standard RLS policies.
- `GoalTracker` creates PV-linked goals and milestones through `PvEngine`, tracks milestone actions, verifies proof uploads through `DocumentVerifier`, and excludes flagged/blocked milestones from PV-realised totals.
- The advisor client detail page now includes goal, milestone, action, and proof-upload controls plus PV-realised progress; the client portal dashboard shows read-only goal progress.
- `PvType` includes `goal_target` and `milestone_impact` calculation types.
- Tests cover PV linkage, proof verification and discrepancy blocking, dashboard payloads for advisor/client portal, and RLS isolation across all four new tables.
- Architecture docs: `docs/architecture/schema.md`.

### WO-66 - Digital Proposal Sign-Off Flow

- Added proposal sign-off columns, `proposal_signoff_steps`, and `payment_authorities` with client-scoped RLS.
- Replaced the Phase 2 reserved-status guard with a sign-off-only transition guard: direct writes to `awaiting_signature`/`signed` are blocked, while `SignoffFlow` can move a released proposal forward in order.
- `SignoffFlow` enforces the seven steps (`review`, `insurance_consent`, `coach_consent`, `payment_method`, `authority`, `signature`, `confirmation`), updates/revokes proposal consents, and writes audit events.
- `AuthorityCapture` calls the Stripe/Windcave gateway contracts, stores gateway tokens only inside `KeyEnvelope`, rejects raw card numbers before persistence, and keeps failed authority captures pre-`awaiting_signature`.
- The client portal now surfaces released proposals and a sign-off page; signed evidence PDFs are stored on `secure_local` with a `KeyEnvelope`-wrapped hash.
- Tests cover ordering, consent capture/revoke, gateway failure, no raw PAN persistence, portal payloads, replaced signature-state guard, and RLS isolation.
- Architecture docs: `docs/architecture/proposals.md` and `docs/architecture/schema.md`.

### WO-67 - Payment Schedules

- Added client-scoped `payment_schedules` with cadence, amount, currency, next-run, status, revoke metadata, and RLS.
- `PaymentSchedule` relates schedules to clients, proposals, and tokenised payment authorities.
- `ScheduleBuilder` creates schedules only from signed proposals with active matching authorities, supports `one_off` and `monthly_retainer`, and defaults omitted amounts from proposal fee summary.
- Authority revocation through `ScheduleBuilder::revokeAuthority()` marks the authority and its active/paused schedules revoked in one audited transaction.
- Tests cover schedule creation, monthly cadence defaults, signed-proposal/authority guards, revoke cascade, audit writes, and RLS isolation.
- Architecture docs: `docs/architecture/payments.md`, `docs/architecture/proposals.md`, and `docs/architecture/schema.md`.

### WO-68 - Stripe + Windcave Live Integration

- Extended the Stripe/Windcave gateway contracts with charge support and added fixture, live, and fallback implementations for both gateways.
- Live gateway clients use `ResilientHttp` when feature flags are enabled; feature flags default off so fixture clients remain the default test/local path.
- Added `Gateway` to decrypt token envelopes in memory, reject raw PAN-like charge metadata, charge the configured primary gateway, fail over to the secondary gateway, and return `failoverFrom`.
- Double-gateway failures write audit events and send urgent `payment.gateway.failure` notifications to super admins and client advisors.
- Added signed Stripe and Windcave webhook endpoints that verify timestamped HMACs and audit receipt/rejection; payment reconciliation remains WO-69.
- Tests cover fixture charge success, primary failover, double-failure notification, no-PAN rejection, live Stripe `ResilientHttp` use, and webhook signature verification.
- Architecture docs: `docs/architecture/payments.md` and `docs/architecture/proposals.md`.

### WO-69 - Monthly Payment Processing + Receipts

- Added client-scoped `payments` and `receipts` tables with RLS, payment-attempt uniqueness, gateway/failover fields, and receipt artifact metadata.
- Added `PaymentProcessor` and `payments:process-scheduled`, scheduled every five minutes with overlap protection.
- Due active schedules create pending payment attempts, call `Gateway`, persist success/failure/retry state, and advance one-off or monthly-retainer schedules.
- `ReceiptGenerator` renders successful-payment PDFs to `secure_local`, stores `KeyEnvelope`-wrapped hashes, and audits receipt generation.
- Failed payments send urgent `payment.failed` notifications to client advisors and primary contacts; failed charges never change proposal `signed` status.
- Tests cover the console processing path, receipt generation, failed first charge notifications, retry/failover success, signed-status invariance, and RLS isolation.
- Architecture docs: `docs/architecture/payments.md` and `docs/architecture/schema.md`.

### WO-70 - Panel Portal Foundation

- Added shared `panel_members`, `panel_agreements`, `referrals`, `referral_messages`, and `reverse_referrals` tables with panel-aware RLS policies.
- Added panel models and relationships from `User` and `Client`.
- `PanelOnboarding` handles broker/coach applications, advisor/super-admin approval, no-fee mutual-referral agreement generation, signed-PDF evidence, and active-portal access gating.
- `ReferralLifecycle` creates referrals, enforces forward-only stage transitions, stores per-referral messages, and creates reverse-referral prospect or entrepreneur records without granting platform access.
- Added an external panel layout for broker/coach portal surfaces and documented the panel architecture.
- Tests cover invite/application/approval/signature access gating, referral stage transitions, messaging, reverse referrals, audit writes, and RLS isolation between panel users.
- Architecture docs: `docs/architecture/panels.md` and `docs/architecture/schema.md`.

### WO-71 - Insurance Broker Portal

- Added broker FSP fields to `panel_members` (`fsp_number`, `fsp_status`, `fsp_last_checked_at`).
- Filled the FSP integration contract with fixture, live, and fallback clients using the WO-05 resilience pattern; `FEATURE_FSP_LIVE` defaults off.
- `PanelOnboarding::approve()` now validates broker applications against the FSP register and blocks approval unless the registration is current.
- Broker panel agreements include FSP-current, lapse-suspension, client-consent, and regulated-advice responsibility clauses.
- Added `panels:broker-fsp-reverify`, scheduled daily, which suspends lapsed brokers and sends urgent advisor/super-admin alerts.
- Broker referrals now use insurance-specific stages (`referral_sent`, `broker_acknowledged`, `quote_requested`, `cover_placed`, `declined`, `no_response`) rather than the shared coach/general stages.
- Tests cover approval-time FSP validation, lapsed-FSP rejection, periodic suspension and alerting, and broker referral-stage enforcement.
- Architecture docs: `docs/architecture/panels.md`, `docs/architecture/nz-integrations.md`, and `docs/architecture/schema.md`.

### WO-72 - Coach Portal

- Added `CoachSpecialisation` with the five fixed specialisations: life, business/executive, mental-health/wellbeing, financial-wellness, and career.
- Extended `panel_members` with coach specialisations, profile, professional memberships, admin-managed vetting payload, and vetted-by/timestamp fields.
- Added client-scoped `coach_referral_authorisations` so key-staff coach referrals require explicit client authorisation.
- `CoachPanel` records vetting, creates owner/key-staff referrals with authorisation checks, and creates entrepreneur coach referrals linked to `entrepreneur_profiles`.
- Coach agreements include the wellbeing scope-boundary clause: coaching only, not clinical mental-health diagnosis/treatment, crisis support, or regulated health advice.
- Coach referrals use coach-specific stages (`referral_sent`, `coach_accepted`, `coaching_underway`, `concluded`, `declined`) and terminal stages close the referral.
- Tests cover specialisation/vetting persistence, agreement clauses, key-staff authorisation gate, entrepreneur referrals, and coach stage transitions.
- Architecture docs: `docs/architecture/panels.md` and `docs/architecture/schema.md`.

### WO-73 - Coaching Referral Signal Detection

- Added `coach_referral_suggestions` with advisor/client-team RLS to persist suggestions derived from raw `coaching_signals`.
- Added two forward signal types (`financial_stress`, `career_transition`) so the five fixed coach specialisations can all be mapped.
- `SignalDetector` maps low coping, leadership capability gaps, owner-readiness constraints, financial stress, and career transition signals to coach specialisation suggestions.
- The advisor dashboard now receives and renders a scoped `coachSignals` panel; every suggestion records `auto_referral = false`.
- Added `panels:coach-signal-calibration`, scheduled monthly, to queue governed `learning_updates` candidates for calibration review only.
- Tests cover all signal mappings, dashboard scoping, no automatic referrals, governed candidate creation, and no learning implementation rows.
- Architecture docs: `docs/architecture/panels.md`, `docs/architecture/dashboard-phase2.md`, and `docs/architecture/schema.md`.

### WO-74 - Referral Conflict + Consent Wiring

- Extended `referrals` with `conflict_declaration_id` and `consent_id`.
- Extended `consents` with nullable referral-only usage (`proposal_id` nullable), `revoked_by_user_id`, and `revoked_at`.
- Added `ReferralConsentManager` to grant referral-specific opt-in consent, link a fresh conflict and active consent to a referral, and revoke consent.
- `ReferralLifecycle::transition()` now blocks broker/coach client-referral send stages unless the linked conflict declaration is fresh and the linked consent is active.
- Consent revocation flips the consent to opt-out and withdraws linked non-terminal referrals.
- Existing proposal sign-off and proposal-builder consent coverage still passes against the shared table.
- Tests cover missing/stale conflict gates, missing/revoked consent gates, successful send after both gates, and revoke-to-withdraw behavior.
- Architecture docs: `docs/architecture/panels.md` and `docs/architecture/schema.md`.

### WO-75 - DD Onboarding + Acquisition Target

- Added client-scoped `dd_engagements` with target details isolated from the buyer client profile, status/recommendation fields, conflict declaration link, and disclaimer acknowledgement.
- Added `DdSpecificQuestionnaireSeeder` for the published `dd_specific` questionnaire; DD onboarding uses that set and defers standard advisory until the post-acquisition flow.
- Added `DdOnboarding` and `DdDisclaimer`; onboarding requires a DD engagement-type client and a fresh `due_diligence` conflict declaration.
- The advisor client detail payload/UI now includes a distinct acquisition-target panel with target data, DD questionnaire metadata, and the DD liability disclaimer.
- Tests cover DD onboarding, conflict gate failure, target/buyer data separation, questionnaire publication, disclaimer text, and advisor target-tab payload.
- Architecture docs: `docs/architecture/due-diligence.md` and `docs/architecture/schema.md`.

### WO-76 - DD Virtual Data Room + Guest Upload

- Added `dd_guest_links` and `dd_data_room_items` with client-scoped RLS and DD engagement linkage.
- Added `DdGuestLink`, `DdDataRoomItem`, and `DataRoom` for eight workstream folders, token hash issuance, revocation, and guest uploads.
- Guest links are upload-only, store no plain token, can be revoked immediately, and enforce expiry/max-upload limits before scanning.
- Guest uploads use the existing `SecureFileWriter`, persist documents as `dd_artifact`, and attach them to the scoped workstream/folder only after a clean scan.
- Added a public API `POST /api/dd/guest-uploads/{token}` endpoint that returns only the created upload metadata and exposes no list/view route.
- The advisor acquisition-target panel now includes data room workstream counts and active guest-link counts.
- Tests cover workstream/folder scoping, upload-only/no-view enforcement, scanner invocation, infected rejection, instant revoke, and DD audit events.
- Architecture docs: `docs/architecture/due-diligence.md` and `docs/architecture/schema.md`.

### WO-77 - DD Eight Workstreams

- Added `dd_workstreams` with client-scoped RLS, workstream status, analysis-run link, evidence item IDs, verification weight, NZ checks, pause reason, and run metadata.
- Added `DdWorkstreamRunner`, `DdWorkstreamModule`, `DdEvidenceAssembler`, and `DdNzCheckProvider`.
- Added `AnalysisModule::DdWorkstream` and the `analysis.dd_workstream` prompt registration.
- DD workstreams run through `AnalysisRunner` while using a scoped DD evidence gate so unresolved accuracy discrepancies pause only the affected workstream.
- Verified DD documents are double-weighted; clean unverified documents count as single-weight evidence; findings cite DD engagement, data room item, document, and verification sources.
- Filled PPSR, LINZ, and IPONZ contracts/fakes for DD legal checks; NZ checks also include IRD GST status, Holidays Act scaffold, and owner-dependency scoring.
- Tests cover all eight workstreams, double-weighted document support, NZ-specific checks, filled register contracts, and per-workstream pause-on-discrepancy.
- Architecture docs: `docs/architecture/due-diligence.md`, `docs/architecture/analysis-spine.md`, and `docs/architecture/schema.md`.

### WO-78 - DD Business Valuation + FX Normalisation

- Added `dd_valuations` with DD engagement links, underlying `business_valuation_id`, reused `pv_calculation_id`, FX metadata, normalised values, sensitivity, buyer position, and source attributions.
- Added `DdValuation` model plus `App\Services\Dd\Valuation` and `FxNormaliser`.
- Extended Phase 2 `BusinessValuation` with an explicit `force_questionnaire_financials` option so DD target financials cannot be contaminated by buyer accounting snapshots.
- DD valuation reuses SDE, EBITDA, and DCF/PV math from the Phase 2 valuation/PV engine and stores the reused PV calculation link.
- Non-NZD DD valuations use the latest RBNZ `exchange_rates` row, timestamp the rate, convert to NZD, and store +/-10% source-to-NZD rate sensitivity.
- Buyer negotiating position compares asking price against the NZD reconciled range.
- Tests cover PV-engine reuse, target-financial isolation, FX normalisation/sensitivity, native NZD path, missing-rate rollback, and the existing Phase 2 valuation/economic indicator paths.
- Architecture docs: `docs/architecture/due-diligence.md`, `docs/architecture/business-valuation.md`, and `docs/architecture/schema.md`.

### WO-79 - DD Business Plan Builder

- Added shared `business_plans`, `plan_phases`, and `plan_sections` tables/models with five reusable plan phases.
- The plan header supports DD-owned plans via `dd_engagement_id` and future entrepreneur-owned plans via `entrepreneur_profile_id`, with a PostgreSQL owner XOR constraint and RLS coverage.
- Added `App\Services\Plans\PlanBuilder` as the shared phase/section/completeness engine.
- Added `App\Services\Dd\PlanBuilder` as a thin DD adapter that maps acquisition target details, completed DD workstream findings, DD valuation, and strategy integration into the shared plan sections.
- The acquisition-proceeding transition rebuilds the plan, blocks incomplete plans, marks complete plans as `founding`, stores the founding advisory payload, and updates the DD engagement status.
- Tests cover workstream auto-population, the completeness gate, founding advisory payload handoff, owner XOR enforcement, and plan-table RLS isolation.
- Architecture docs: `docs/architecture/due-diligence.md` and `docs/architecture/schema.md`.

### WO-80 - DD Report Generation

- Added `dd_risk_register` and `dd_integration_plans` with buyer-client RLS.
- Added `DdRiskRegisterItem` and `DdIntegrationPlanItem` models and DD engagement relationships.
- Added `ReportComposer::composeDueDiligence()` for engagement-specific DD reports using the existing `reports`, `report_sections`, PDF, and PowerPoint pipeline.
- DD reports include executive summary, valuation, workstream findings, risk register, price-adjustment schedule, 100-day integration plan, buyer readiness, recommendation, and liability disclaimer sections.
- Risk register rows are rebuilt from completed DD workstream findings, ranked through the shared risk-cost PV engine, and classified as deal-killer, major, minor, or informational.
- Recommendation logic writes Proceed / Renegotiate / Abandon back to `dd_engagements.recommendation`.
- Tests cover report composition, disclaimer/PDF/PPTX output, PV ranking, price-adjustment metadata, recommendation paths, and RLS isolation.
- Architecture docs: `docs/architecture/due-diligence.md` and `docs/architecture/schema.md`.

### WO-81 - Post-Acquisition Advisory Pipeline

- Added `post_acquisition_migrations` with buyer/advisory client RLS and idempotent `dd_engagement_id` uniqueness.
- Added `PostAcquisitionMigration` model and `App\Services\Dd\PostAcquisition`.
- Added `PostAcquisitionGapQuestionnaireSeeder` and seeded the `post_acquisition_gap` questionnaire in `DatabaseSeeder`.
- Conversion requires `dd_engagements.status = acquisition_proceeding`, creates a new `post_acquisition_advisory` client, and links the founding DD business plan when present.
- DD data-room documents are copied into new advisory-client document rows with `Sourced from DD` filename and scanner-payload labels.
- The gap questionnaire response is prefilled from DD target details, ranked risks, and migrated document counts, leaving only gap questions unanswered.
- The auto-generated draft proposal carries an outcome-based fee calculation with the DD valuation midpoint as the PV baseline and the DD risk-register PV total.
- Tests cover profile migration, document labelling, gap prefill, proposal PV baseline, and RLS isolation.
- Architecture docs: `docs/architecture/due-diligence.md` and `docs/architecture/schema.md`.

## Verification

Latest local checks include the full WO-64 suite plus WO-65...WO-81 targeted checks:

```pwsh
composer test
php artisan test tests\Feature\Goals\GoalTrackerTest.php
php artisan test tests\Feature\Proposals\ProposalBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php
php artisan test tests\Feature\Reports\PracticeHealthReportTest.php tests\Feature\Advisor\DashboardPhaseTwoPanelsTest.php
php artisan test tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php
php artisan test tests\Feature\Payments\PaymentGatewayTest.php tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php
php artisan test tests\Feature\Payments\PaymentProcessingTest.php tests\Feature\Payments\PaymentGatewayTest.php tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php
php artisan test tests\Feature\Panels\PanelFoundationTest.php
php artisan test tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\PanelFoundationTest.php tests\Feature\Integration\NzbnLookupTest.php
php artisan test tests\Feature\Panels\CoachPortalTest.php tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\PanelFoundationTest.php
php artisan test tests\Feature\Panels\CoachSignalDetectorTest.php tests\Feature\Panels\CoachPortalTest.php tests\Feature\Advisor\DashboardPhaseTwoPanelsTest.php
php artisan test tests\Feature\Panels\ReferralComplianceTest.php tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\CoachPortalTest.php tests\Feature\Panels\PanelFoundationTest.php
php artisan test tests\Feature\Proposals\ProposalSignoffFlowTest.php tests\Feature\Proposals\ProposalBuilderTest.php tests\Feature\Conflicts\ConflictDeclarerTest.php
php artisan test tests\Feature\Dd\DdOnboardingTest.php tests\Feature\Conflicts\ConflictDeclarerTest.php
php artisan test tests\Feature\Dd\DdDataRoomTest.php tests\Feature\Dd\DdOnboardingTest.php tests\Unit\Storage\SecureFileWriterTest.php
php artisan test tests\Feature\Dd\DdWorkstreamRunnerTest.php tests\Feature\Dd\DdDataRoomTest.php tests\Feature\Analysis\AnalysisRunnerTest.php tests\Feature\Integration\NzbnLookupTest.php
php artisan test tests\Feature\Dd\DdValuationTest.php tests\Feature\Pv\BusinessValuationTest.php tests\Feature\Integration\EconomicIndicatorsTest.php
php artisan test tests\Feature\Dd\DdPlanBuilderTest.php tests\Feature\Dd\DdWorkstreamRunnerTest.php tests\Feature\Dd\DdValuationTest.php
php artisan test tests\Feature\Dd\DdReportTest.php tests\Feature\Reports\ReportComposerTest.php tests\Feature\Dd\DdValuationTest.php tests\Feature\Dd\DdWorkstreamRunnerTest.php
php artisan test tests\Feature\Dd\PostAcquisitionTest.php tests\Feature\Dd\DdReportTest.php tests\Feature\Dd\DdPlanBuilderTest.php tests\Feature\Proposals\ProposalBuilderTest.php
vendor\bin\pint --dirty
npm run lint:check
npm run types:check
npm run format:check
```

Results after WO-64:

- `composer test` (Pint + PHPUnit against PostgreSQL `futureshift_test`): passed - 293 tests, 2190 assertions.
- `php artisan test tests\Feature\Wellbeing\WellbeingCheckinTest.php` (WO-64 targeted wellbeing coverage): passed - 7 tests, 103 assertions.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 64 distinct WO commits (WO-01...WO-64) on `featureApp`.

Results after WO-65:

- `php artisan test tests\Feature\Goals\GoalTrackerTest.php` (PostgreSQL `futureshift_test`): passed - 3 tests, 57 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 65 distinct WO commits (WO-01...WO-65) on `featureApp`.

Results after WO-66:

- `php artisan test tests\Feature\Proposals\ProposalBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php` (PostgreSQL `futureshift_test`): passed - 10 tests, 104 assertions.
- `php artisan test tests\Feature\Reports\PracticeHealthReportTest.php tests\Feature\Advisor\DashboardPhaseTwoPanelsTest.php` (PostgreSQL `futureshift_test`): passed - 6 tests, 62 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 66 distinct WO commits (WO-01...WO-66) on `featureApp`.

Results after WO-67:

- `php artisan test tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php` (PostgreSQL `futureshift_test`): passed - 11 tests, 77 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 67 distinct WO commits (WO-01...WO-67) on `featureApp`.

Results after WO-68:

- `php artisan test tests\Feature\Payments\PaymentGatewayTest.php tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php` (PostgreSQL `futureshift_test`): passed - 17 tests, 100 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 68 distinct WO commits (WO-01...WO-68) on `featureApp`.

Results after WO-69:

- `php artisan test tests\Feature\Payments\PaymentProcessingTest.php tests\Feature\Payments\PaymentGatewayTest.php tests\Feature\Payments\PaymentScheduleBuilderTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php` (PostgreSQL `futureshift_test`): passed - 21 tests, 131 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 69 distinct WO commits (WO-01...WO-69) on `featureApp`.

Results after WO-70:

- `php artisan test tests\Feature\Panels\PanelFoundationTest.php` (PostgreSQL `futureshift_test`): passed - 3 tests, 19 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 70 distinct WO commits (WO-01...WO-70) on `featureApp`.

Results after WO-71:

- `php artisan test tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\PanelFoundationTest.php tests\Feature\Integration\NzbnLookupTest.php` (PostgreSQL `futureshift_test`): passed - 10 tests, 106 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 71 distinct WO commits (WO-01...WO-71) on `featureApp`.

Results after WO-72:

- `php artisan test tests\Feature\Panels\CoachPortalTest.php tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\PanelFoundationTest.php` (PostgreSQL `futureshift_test`): passed - 9 tests, 59 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 72 distinct WO commits (WO-01...WO-72) on `featureApp`.

Results after WO-73:

- `php artisan test tests\Feature\Panels\CoachSignalDetectorTest.php tests\Feature\Panels\CoachPortalTest.php tests\Feature\Advisor\DashboardPhaseTwoPanelsTest.php` (PostgreSQL `futureshift_test`): passed - 7 tests, 83 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 73 distinct WO commits (WO-01...WO-73) on `featureApp`.

Results after WO-74:

- `php artisan test tests\Feature\Panels\ReferralComplianceTest.php tests\Feature\Panels\BrokerPortalTest.php tests\Feature\Panels\CoachPortalTest.php tests\Feature\Panels\PanelFoundationTest.php tests\Feature\Proposals\ProposalSignoffFlowTest.php tests\Feature\Proposals\ProposalBuilderTest.php tests\Feature\Conflicts\ConflictDeclarerTest.php` (PostgreSQL `futureshift_test`): passed - 26 tests, 188 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 74 distinct WO commits (WO-01...WO-74) on `featureApp`.

Results after WO-75:

- `php artisan test tests\Feature\Dd\DdOnboardingTest.php tests\Feature\Conflicts\ConflictDeclarerTest.php` (PostgreSQL `futureshift_test`): passed - 7 tests, 39 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 75 distinct WO commits (WO-01...WO-75) on `featureApp`.

Results after WO-76:

- `php artisan test tests\Feature\Dd\DdDataRoomTest.php tests\Feature\Dd\DdOnboardingTest.php tests\Unit\Storage\SecureFileWriterTest.php` (PostgreSQL `futureshift_test`): passed - 11 tests, 79 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 76 distinct WO commits (WO-01...WO-76) on `featureApp`.

Results after WO-77:

- `php artisan test tests\Feature\Dd\DdWorkstreamRunnerTest.php tests\Feature\Dd\DdDataRoomTest.php tests\Feature\Analysis\AnalysisRunnerTest.php tests\Feature\Integration\NzbnLookupTest.php` (PostgreSQL `futureshift_test`): passed - 17 tests, 205 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 77 distinct WO commits (WO-01...WO-77) on `featureApp`.

Results after WO-78:

- `php artisan test tests\Feature\Dd\DdValuationTest.php tests\Feature\Pv\BusinessValuationTest.php tests\Feature\Integration\EconomicIndicatorsTest.php` (PostgreSQL `futureshift_test`): passed - 11 tests, 103 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 78 distinct WO commits (WO-01...WO-78) on `featureApp`.

Results after WO-79:

- `php artisan test tests\Feature\Dd\DdPlanBuilderTest.php tests\Feature\Dd\DdWorkstreamRunnerTest.php tests\Feature\Dd\DdValuationTest.php` (PostgreSQL `futureshift_test`): passed - 12 tests, 131 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 79 distinct WO commits (WO-01...WO-79) on `featureApp`.

Results after WO-80:

- `php artisan test tests\Feature\Dd\DdReportTest.php tests\Feature\Reports\ReportComposerTest.php tests\Feature\Dd\DdValuationTest.php tests\Feature\Dd\DdWorkstreamRunnerTest.php` (PostgreSQL `futureshift_test`): passed - 18 tests, 223 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 80 distinct WO commits (WO-01...WO-80) on `featureApp`.

Results after WO-81:

- `php artisan test tests\Feature\Dd\PostAcquisitionTest.php tests\Feature\Dd\DdReportTest.php tests\Feature\Dd\DdPlanBuilderTest.php tests\Feature\Proposals\ProposalBuilderTest.php` (PostgreSQL `futureshift_test`): passed - 17 tests, 146 assertions.
- `vendor\bin\pint --dirty`: passed.
- `npm run lint:check` (ESLint): passed.
- `npm run types:check` (`tsc --noEmit`): passed.
- `npm run format:check` (Prettier): passed.
- Git history after this commit: 81 distinct WO commits (WO-01...WO-81) on `featureApp`.

Note: the local test DB required using the actual local Postgres connection values via the process environment, because `.env.testing` ships Herd defaults (`herd` role / empty password) that do not authenticate against a standalone PostgreSQL install. The test database must be separate from the dev database (`RefreshDatabase` wipes it). Do not commit local DB credentials.

## Remaining Work

**Phase 1 (WO-01...WO-30) and Phase 2 (WO-31...WO-64) are complete and verified. Phase 3 is in progress with WO-65...WO-81 complete; next is WO-82.**

> Per-WO detail above covers WO-01...WO-18 and WO-31...WO-64; WO-19...WO-30 are summarised in the commit-log table with their commit hashes, and each shipped with its own architecture doc under `docs/architecture/` and tests. The git log and architecture docs are the authoritative per-WO record for WO-19...WO-30.

### Carryover owner inputs (deferred by design — not Phase 1 gaps; several now gate client-facing Phase 2 output)

| Item | Needed for | Status |
|---|---|---|
| Anthropic API key | Live AI (analysis degrades to deferred without it) | Optional in P1; needed early in P2 |
| NZBN / Companies Office / IRD + accounting (Xero/MYOB/QuickBooks) credentials | Live integration mode | Stubs/fixtures and resilience fallback until arranged |
| RBNZ / Stats NZ / MBIE + NZ Business Brokers credentials or access policy | WO-36 live economic indicator mode; WO-39 live valuation multiples mode | Fixture/fallback path works; live access can be enabled later |
| Meridian Warm brand kit | Client-facing UI + report branding | Placeholder in `docs/brand/` |
| Lawyer-reviewed 14-clause T&C text | Anything client-facing | Placeholder in `docs/legal/terms-v1.md` |
| ClamAV production host | Production uploads | Interface ready; host/port pending |

## Open Inputs

| Item | Needed for | Status |
|---|---|---|
| Anthropic API key | Live AI testing in WO-04/WO-18 | Optional; fake/degraded path works. |
| NZBN / Companies Office / IRD access | WO-13 live mode | Stubs until arranged. |
| Xero / MYOB / QuickBooks access | WO-37 live accounting mode | Fixtures and resilience fallback work until arranged. |
| RBNZ / Stats NZ / MBIE + NZ Business Brokers access | WO-36 live economic indicator mode; WO-39 live valuation multiples mode | Fixtures and resilience fallback work until arranged. |
| Meridian Warm brand kit | Client-facing UI | Placeholder files exist. |
| Lawyer-reviewed T&C text | WO-10/11 | Placeholder exists. |
| ClamAV deployment plan | Production uploads | Interface exists; production daemon host/port still pending. |

## Process Reminder

- Stay on `featureApp`.
- Implement WOs in numeric order.
- Commit each completed WO directly on `featureApp` with `WO-<id>: <slug summary>`.
- For WO-65 through WO-79, the owner instructed proceeding in sequence without approval pauses; still commit each WO before starting the next.
- WO-08 was completed before WO-07 by explicit owner request; the RBAC dependency is now closed before further role-sensitive surfaces.
- Do not invent Phase 2+ features.
- No raw secrets, unowned placeholder comments, or debug calls in shipped code.
- All AI calls go through `AiClient`.
- All future external calls go through the resilience layer introduced in WO-05.
- All sensitive upload persistence goes through `SecureFileWriter`; do not bypass scan-before-store.
