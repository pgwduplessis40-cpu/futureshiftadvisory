# Future Shift Advisory — Phase 1 Implementation Plan (for Codex)

**Source spec:** `Future_Shift_Advisory_App_Specification_v2.4.docx` (definitive version, May 2026)
**Plan scope:** Phase 1 — Foundation (Months 1–3) detailed; Phases 2–4 summarised as forward roadmap.
**Repo:** `C:\Users\pgwdu\Herd\futureshiftadvisory` (Laravel 13 + Inertia + React + TypeScript starter kit, served by Laravel Herd on Windows)
**Owner:** Pieter Du Plessis — Future Shift Advisory, Hamilton NZ
**Plan version:** 1.0 — drafted from spec V2.4

---

## 0. How Codex should use this plan

1. Read sections 1–7 once before starting. They lock decisions you are not free to revisit without the owner.
2. Implement **Work Orders (WOs)** in section 8 in numeric order. Dependencies are listed; do not break order without explicit owner approval.
3. Each WO has explicit **acceptance criteria** and **tests**. A WO is not "done" until every acceptance criterion is demonstrably true and tests pass.
4. After each WO: commit on a branch `wo/<id>-<slug>`, open a PR, link the WO ID in the title. Do not merge multiple WOs in one PR.
5. Do not invent features beyond what the spec or this plan explicitly require. Spec V2.4 is the source of truth; this plan translates it to Laravel/Inertia. If you find a gap, raise it in the PR description rather than guessing.
6. If a WO references an external system (Anthropic, NZBN, Stripe, ClamAV, etc.) and credentials are not configured, build the **interface + stub + tests** per section 7.4. Do not block on credentials.
7. Treat the spec's **AI Integrity Principle (§3)** and **Document AI Verification (§9)** as non-negotiable architecture, not features — they thread through every AI-touching WO.

---

## 1. Non-negotiable platform principles (locked)

These come directly from the spec. They are constraints on every decision below, not features in their own right.

### 1.1 AI Integrity Principle (spec §3)

Every AI output — analysis, guidance, scoring, recommendation, document review, resource suggestion — must be:

- **Honest** — problems and low scores stated clearly. Kindness in delivery, not in content.
- **Evidence-based** — every finding cites its source. AI evidences, never asserts.
- **Accurate** — NZ-specific, industry-specific, current.
- **Free from bias** — bias detection layer monitors all outputs.
- **Truthful** — tells users what the evidence says, not what they want to hear.

**Structural safeguards** (must be built, not optional):
- Source attribution on every AI guidance statement.
- No score inflation; systematic upward drift triggers a learning update flag.
- Bias detection layer monitors all AI outputs.
- Mandatory uncertainty disclosure when data is insufficient.
- No warning suppression — viability alerts, risk flags, compliance gaps, document discrepancies are never hidden.

### 1.2 Document AI Verification (spec §9) — platform-wide

Every document uploaded anywhere — questionnaire attachments, financial statements, contracts, insurance certs, business plans, DD data room, entrepreneur plan attachments — is reviewed by the AI for relevance and accuracy. Three outcomes:

- **Verified — Consistent** → "Claim supported by uploaded document."
- **Advisory Flag — Partial Support** → flag raised; analysis proceeds with flag visible in every output and report.
- **Accuracy Discrepancy — Contradiction** → discrepancy stated in plain English; advisor notified; affected analysis paused until resolved.

**Accuracy discrepancies are never suppressed.** Every verification event is logged permanently.

### 1.3 Universal security baseline (spec §4)

For Phase 1 (PQC deferred to Phase 4 per owner decision):

- TLS 1.3 in transit (Herd dev cert locally; ACM/Let's Encrypt in production).
- AES-256 at rest via Laravel `Crypt` facade, keys via env in dev, cloud KMS in production.
- MFA mandatory for all 9 user types. No exceptions.
- Invite-only registration. No self-registration anywhere.
- Every file scanned for malware before storage (scanner interface — see WO-06).
- Immutable audit trail of every action (WO-03).
- Zero-trust: every inter-service call authenticated; API-layer role enforcement; row-level DB security per client.
- T&C accept/decline gate before any platform access (WO-10/11).
- Session timeout configurable per user type; step-up MFA on risk signals.

PQC envelope (CRYSTALS-Kyber/Dilithium via liboqs) is **deferred to Phase 4**. Architect the key/envelope layer so PQC can slot in without rewriting (see WO-02 §2.6).

### 1.4 32-layer governed learning (spec §23)

Every AI behaviour that learns goes through a governed update process — never silent self-modification. Phase 1 builds the **scaffolding** (audit table, learning update queue table, admin-approval workflow stubs). Layers themselves come online in Phases 2–4.

### 1.5 Liability disclaimer (spec §16 intro)

Every DD output and every report-grade AI output carries the standard liability disclaimer. Phase 1 establishes the disclaimer registry and rendering primitive even though DD reports themselves are Phase 3.

---

## 2. Phase 1 goal & out-of-scope

### 2.1 Phase 1 goal (Months 1–3)

A working foundation that:

- Lets the owner invite real clients and entrepreneurs with full MFA, T&C acceptance, and conflict-of-interest declaration.
- Captures questionnaire responses and uploaded documents safely (encrypted, scanned, audit-logged).
- Runs every uploaded document through the AI relevance/accuracy verification pipeline (real Claude calls when key is present; degraded mode otherwise).
- Surfaces document verification flags, red flags, and pending T&C re-acceptance on a basic advisor dashboard.
- Establishes the API resilience layer and integration scaffolding for NZBN/Companies Office/IRD so Phase 2 analysis modules plug in without architectural change.
- Embeds the AI Integrity Principle and bias-detection logging into every AI prompt path.
- Is audit-complete: every user action, document event, T&C event, and AI output is logged immutably.

### 2.2 Explicitly out of Phase 1

These are Phase 2–4 per spec §26. Do not implement, but do not architect them out either — leave the seams.

- All analysis modules (financial, HR, operational, website audit, competitor, SWOT) — Phase 2.
- PV module — Phase 2.
- Fee calculator, proposal generation, payment processing — Phase 2/3.
- DD module, virtual data room, post-acquisition pipeline — Phase 3.
- Broker portal and Coach portal — Phase 3.
- Entrepreneur module beyond basic profile and engagement-type tagging — Phase 3.
- Learning update queue UI (table scaffolded in Phase 1; UI in Phase 3).
- All 32 learning layers active — Phases 2–4.
- Cross-client intelligence, anonymous benchmarking, peer network — Phase 4.
- Voice assistant, mobile app foundation, full continuous learning engine — Phase 4.
- PQC cryptography (Kyber/Dilithium) and HSM — Phase 4.

---

## 3. Stack & architecture (locked)

| Layer | Choice | Spec ref / rationale |
|---|---|---|
| Framework | Laravel 13 (existing) | Owner decision — keep scaffold; spec §27 Node/Python replaced |
| Frontend | Inertia.js + React 19 + TypeScript (existing) | Same; spec §27 React kept, Next.js replaced by Inertia |
| UI kit | shadcn/ui (style "new-york", neutral), Tailwind v4, Radix primitives, lucide-react, sonner for toasts | Already wired in `components.json` |
| Typed routes | Laravel Wayfinder (existing) | Avoids hand-maintained route constants in TS |
| Auth | Laravel Fortify (existing) + custom MFA/invite/SSO layer | Owner decision; spec §27 Auth0/Cognito replaced |
| Database | PostgreSQL 16 (Herd-provided locally) with row-level security | Spec §27 |
| Queues | Laravel queues, `database` driver in Phase 1; Redis post-Phase 1 | Operational simplicity |
| File storage | Local disk (`storage/app/secure/`) Phase 1; S3 (Sydney) in production | Spec §27 — region constrained NZ/AU |
| Encryption | TLS 1.3, AES-256 via `Crypt` facade, key envelope abstraction | Per owner decision; PQC deferred to Phase 4 |
| AI | Anthropic Claude API via `claude-3-5-sonnet` / latest, wrapped in `AiClient` interface | Spec §27 |
| Virus scan | `FileScanner` interface; `NoopScanner` (dev), `ClamAvScanner` skeleton (prod) | Spec §4 |
| PDF | `PdfRenderer` interface; Spatie Browsershot (Puppeteer) in Phase 1 | Spec §27 |
| Presentations | Deferred to Phase 2 (`PptxGenerator` interface only) | Spec §27 |
| Email | Laravel Mail; `log` driver locally, AWS SES in production | Spec §27 |
| Calendar | Deferred to Phase 2 (interface only) | Spec §27 |
| SIEM | Datadog in production; structured JSON logs Phase 1 | Spec §27 |
| Test | PHPUnit (existing) + Pest installable later; Vitest for frontend if needed | `composer.json` already on PHPUnit |
| Lint/format | Laravel Pint, ESLint, Prettier (existing) | — |

### 3.1 Six-layer architecture (spec §2.2) — how it maps

| Spec layer | Phase 1 implementation |
|---|---|
| 1 — Client Portal | `Http/Controllers/Portal/*` + `resources/js/pages/portal/*` |
| 2 — Advisor Command Centre | `Http/Controllers/Advisor/*` + `resources/js/pages/advisor/*` (shell only) |
| 3 — Broker/Coach Portals | Deferred to Phase 3 |
| 4 — DD Virtual Data Room | Deferred to Phase 3 |
| 5 — Entrepreneur Portal | Skeleton route + invite path; full build Phase 3 |
| 6 — Intelligence Engine | `app/Services/Ai/*` + `app/Services/Integration/*` scaffolds; analytical modules Phase 2+ |

---

## 4. Repo layout (after Phase 1)

```
app/
  Actions/                       # single-purpose actions (existing)
  Concerns/                      # traits (existing)
  Http/
    Controllers/
      Advisor/                   # advisor dashboard, knowledge base, prospect inbox
      Portal/                    # client portal, onboarding wizard
      Auth/                      # Fortify overrides, MFA challenge, T&C gate
      Admin/                     # super-admin: T&C versions, user invites, settings
      Webhook/                   # inbound integrations (later)
    Middleware/
      RequireMfa.php
      RequireAcceptedTerms.php
      EnsureRole.php             # role-based gate (wraps Gate)
      LogAuditEvent.php          # request-level audit trail
      EnforceClientScope.php     # tenant/client row-level scope
    Requests/                    # form requests (validation)
  Models/
    User.php
    Role.php
    Permission.php
    Client.php
    EngagementType.php
    EntrepreneurProfile.php
    InviteToken.php
    TermsVersion.php
    TermsClause.php
    TermsAcceptance.php
    Questionnaire.php
    QuestionnaireSection.php
    QuestionnaireQuestion.php
    QuestionnaireResponse.php
    Document.php
    DocumentVerification.php
    AuditEvent.php
    Notification.php             # extends Laravel notifications
    CommunicationPreference.php
    WellbeingCheckin.php
    ConflictDeclaration.php
    KnowledgeEntry.php
    ProspectLead.php
    IntegrationCall.php
    IntegrationHealthSample.php
    LearningUpdate.php           # table only; queue UI is Phase 3
  Policies/                      # one per model
  Providers/
  Services/
    Ai/
      Contracts/AiClient.php
      Claude/AnthropicClaudeClient.php
      Fake/FakeAiClient.php
      Prompts/                   # prompt registry (versioned)
      Integrity/SourceAttribution.php
      Integrity/BiasDetector.php
      Integrity/UncertaintyPolicy.php
      Verification/DocumentVerifier.php
    Integration/
      Contracts/IntegrationClient.php
      Resilience/RetryPolicy.php
      Resilience/CircuitBreaker.php
      Resilience/HealthRecorder.php
      Nzbn/NzbnClient.php
      CompaniesOffice/CompaniesOfficeClient.php
      Ird/IrdClient.php
      Stripe/StripeClient.php             # scaffold only — Phase 3
      Windcave/WindcaveClient.php         # scaffold only — Phase 3
      VirusScanner/Contracts/FileScanner.php
      VirusScanner/NoopScanner.php
      VirusScanner/ClamAvScanner.php
      Pdf/Contracts/PdfRenderer.php
      Pdf/BrowsershotRenderer.php
    Audit/AuditWriter.php
    Security/MfaChallenger.php
    Security/InviteIssuer.php
    Security/StepUpEvaluator.php
    Notifications/ChannelResolver.php
    Storage/SecureFileWriter.php
    Storage/KeyEnvelope.php               # AES-256 wrapper; PQC-ready seam
database/
  migrations/                             # schema below
  seeders/
    RoleSeeder.php
    PermissionSeeder.php
    SuperAdminSeeder.php
    TermsVersionSeeder.php                # imports the 14-clause T&C text
    QuestionnaireSeeder.php               # Standard Advisory set
resources/
  js/
    pages/
      portal/...
      advisor/...
      admin/...
      auth/...
    components/
      brand/                              # Meridian Warm tokens, logo, BrandShell
      ui/                                 # shadcn (existing)
      verification/                       # document verification UI primitives
      audit/
    layouts/                              # portal layout, advisor layout, admin layout
    lib/integrity/                        # attribution, uncertainty badges
  css/
    app.css                               # Meridian Warm tokens as CSS vars
routes/
  web.php
  auth.php                                # split Fortify-related routes
  admin.php
  portal.php
  advisor.php
tests/
  Feature/
  Unit/
docs/
  PLAN.md                                 # this file (lives at repo root, mirrored here)
  CLAUDE.md                               # critical rules block — see WO-01
  spec/Future_Shift_Advisory_App_Specification_v2.4.docx
  brand/                                  # Meridian Warm kit, logo files
  rating-criteria/Business_Plan_Rating_Matrix.pdf
  legal/terms-v1.md                       # 14-clause T&C source
```

---

## 5. Environment setup (one-time, Codex local on Windows)

> All commands assume PowerShell from `C:\Users\pgwdu\Herd\futureshiftadvisory`. Bash variants in parentheses.

### 5.1 Tooling check

```pwsh
php -v                       # expect 8.3+
composer -V
node -v                      # expect 22+
npm -v
herd status                  # Herd should be running
```

### 5.2 PostgreSQL via Herd

Herd ships with PostgreSQL. Confirm and create the DB:

```pwsh
herd open services           # ensure Postgres service is enabled
# Connect (defaults: host 127.0.0.1, port 5432, user "herd", password empty)
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_dev;"
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_test;"
```

If the Herd `psql` path differs, fall back to any installed `psql` on `PATH`. Document the actual command in `docs/dev-setup.md` once verified.

### 5.3 `.env`

Copy `.env.example` to `.env` (already done by Laravel installer). Then add/override:

```
APP_NAME="Future Shift Advisory"
APP_ENV=local
APP_URL=https://futureshiftadvisory.test       # Herd auto-serves this hostname

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=futureshift_dev
DB_USERNAME=herd
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=secure_local
MAIL_MAILER=log

# AI — scaffolded; if unset, AiClient resolves to FakeAiClient
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6

# Integrations — scaffolded with feature flags; all default off in Phase 1
FEATURE_NZBN_LIVE=false
FEATURE_COMPANIES_OFFICE_LIVE=false
FEATURE_IRD_LIVE=false
FEATURE_VIRUS_SCAN_LIVE=false

# Security knobs
MFA_REQUIRED=true
SESSION_TIMEOUT_DEFAULT_MINUTES=30
INVITE_TOKEN_TTL_HOURS=72
```

`.env.testing` mirrors above with `DB_DATABASE=futureshift_test` and `ANTHROPIC_API_KEY=` (forces FakeAiClient).

### 5.4 First boot

```pwsh
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
composer dev                 # runs server + queue + vite concurrently
```

Open `https://futureshiftadvisory.test` and confirm the starter page renders.

### 5.5 Test loop

```pwsh
composer test                # pint + phpunit
npm run lint:check
npm run types:check
```

---

## 6. Database schema (Phase 1)

Postgres-specific decisions:

- All primary keys are `uuid` (Laravel `HasUuids`), generated by `gen_random_uuid()` (`pgcrypto` extension — enable in first migration).
- All "audit-relevant" tables have `created_at`, `updated_at`, and a `created_by_user_id` where applicable. Soft deletes only on lookups, never on event/audit data.
- All client-scoped tables carry a `client_id` (nullable for cross-tenant rows like `users` and `audit_events`). RLS policies enforce isolation (see WO-02).
- All JSON columns use `jsonb`.

### 6.1 Tables (grouped)

**Identity & access** (WO-07/08/09)
- `users` — extend Fortify default with: `user_type` enum (super_admin, advisor, junior_advisor, entrepreneur_mentor, client_primary, client_team, entrepreneur, broker, coach), `mfa_enabled_at`, `mfa_method` (totp|webauthn), `last_password_set_at`, `session_timeout_minutes`, `suspended_at`, `suspended_reason`
- `roles`, `permissions`, `role_user`, `permission_role` (spatie/laravel-permission)
- `invite_tokens` — `email`, `target_role`, `target_user_type`, `token_hash`, `expires_at`, `accepted_at`, `issued_by_user_id`
- `mfa_factors` — TOTP secrets, recovery codes (encrypted)
- `webauthn_credentials` — Phase 2 stub table; created now, populated later
- `sessions` (Laravel default), extended with `risk_score`, `step_up_at`

**Terms & consent** (WO-10/11)
- `terms_versions` — `version`, `published_at`, `material` (bool), `pdf_path`, `published_by_user_id`, `notice_period_days`
- `terms_clauses` — `terms_version_id`, `clause_number`, `title`, `body`, `material` (bool)
- `terms_acceptances` — `user_id`, `terms_version_id`, `accepted_at`, `declined_at`, `signed_pdf_path`, `ip`, `user_agent`
- `consents` — generic consent ledger: `user_id`, `client_id`, `consent_type` (insurance_referral, coach_referral, marketing), `granted`, `granted_at`, `revoked_at`, `source` (proposal|portal)

**Client & entrepreneur** (WO-14/15)
- `clients` — `engagement_type` enum (standard_advisory, due_diligence, post_acquisition_advisory, entrepreneur_module), `nzbn`, `legal_name`, `trading_name`, `entity_type`, `address` (jsonb), `gst_registered`, `directors` (jsonb), `filing_status`, `data_quality` enum (high, medium, low, insufficient), `created_by_user_id`, `primary_contact_user_id`
- `client_team` — `client_id`, `user_id`, `granted_modules` (jsonb), `role`
- `entrepreneur_profiles` — `user_id`, `assigned_advisor_id`, `stage` enum (invited, onboarding, readiness, idea_validation, building_phase1..5, submitted, assessment, revising, launched, advisory_ready), `concept_summary`
- `conflict_declarations` — `client_id`, `advisor_id`, `declaration` (jsonb: `referral_type`, `existing_relationship`, `details`), `declared_at`
- `offboarding_records` — `client_id`, `triggered_at`, `final_report_path`, `handover_path`, `exit_interview_path`, `reengagement_due`

**Questionnaire** (WO-17)
- `questionnaires` — `set` enum (standard_advisory, dd_specific, post_acquisition_gap, entrepreneur_readiness, entrepreneur_idea_validation), `version`, `published_at`
- `questionnaire_sections` — `questionnaire_id`, `order`, `title`, `help_text`
- `questionnaire_questions` — `section_id`, `order`, `type` enum, `prompt`, `help_text`, `options` (jsonb), `conditional_logic` (jsonb), `required`
- `questionnaire_responses` — `client_id`, `questionnaire_id`, `submitted_at`, `submitted_by_user_id`
- `questionnaire_answers` — `response_id`, `question_id`, `value` (jsonb), `attached_document_ids` (jsonb)

**Documents & verification** (WO-18)
- `documents` — `client_id`, `entrepreneur_profile_id` (nullable), `category` enum (financial_statement, contract, insurance_certificate, hr_record, compliance_doc, plan_attachment, dd_artifact, other), `original_filename`, `stored_path`, `byte_size`, `mime_type`, `sha256`, `uploaded_by_user_id`, `scanner_result` enum (pending, clean, infected, error), `scanner_payload` (jsonb), `expires_at` (nullable — Phase 3 use)
- `document_verifications` — `document_id`, `subject_type` (questionnaire_answer | manual | plan_section), `subject_id`, `outcome` enum (verified, advisory_flag, accuracy_discrepancy, error), `claim_text`, `discrepancy_text`, `confidence`, `evidence` (jsonb — Claude response excerpts), `verified_at`, `verified_by` (ai|advisor), `resolved_at`, `resolution_note`

**Communications** (WO-12/23/24/25/26)
- `communication_preferences` — `user_id`, `channels` (jsonb: email_enabled, in_app_enabled), `frequency` enum (immediate, daily, weekly), `quiet_hours` (jsonb)
- `notifications` (Laravel default), extended with `urgency` enum (info, action, urgent), `channel_decision` (jsonb log)
- `message_threads` — `client_id` (nullable), `subject`, `last_activity_at`
- `message_thread_participants` — `thread_id`, `user_id`, `last_read_at`
- `messages` — `thread_id`, `sender_user_id`, `body`, `attachments` (jsonb of document_id), `sent_at`

**Wellbeing & knowledge** (WO-20/27)
- `wellbeing_checkins` — `user_id`, `client_id`, `business_confidence`, `personal_coping`, `notes`, `submitted_at`
- `knowledge_entries` — `author_user_id`, `client_id` (nullable), `category`, `title`, `body`, `tags` (jsonb), `created_at`

**Audit & integration health** (WO-03/05/30)
- `audit_events` — `id` (uuid), `occurred_at`, `actor_user_id` (nullable), `actor_role`, `client_id` (nullable), `action`, `subject_type`, `subject_id`, `before` (jsonb), `after` (jsonb), `ip`, `user_agent`, `request_id`. **No update or delete permitted** — enforced by DB trigger.
- `integration_calls` — `service`, `endpoint`, `request_id`, `status` enum (success, retry, failure, cached, fallback), `latency_ms`, `attempt`, `error_payload` (jsonb), `correlation_id`, `occurred_at`
- `integration_health_samples` — `service`, `window_start`, `window_end`, `success_rate`, `p95_latency_ms`, `health` enum (green, amber, red)

**Learning governance (scaffolding only, no UI in Phase 1)** (WO-04)
- `learning_updates` — `layer_id` (int), `source` (jsonb), `summary`, `proposed_change` (jsonb), `impact_scope` (jsonb), `clients_affected`, `magnitude`, `confidence`, `evidence` (jsonb), `effective_date`, `status` enum (detected, staged, approved, rejected, deferred, implemented, rolled_back), `decided_by_user_id`, `decided_at`, `rollback_id` (nullable)
- `learning_update_implementations` — `learning_update_id`, `implemented_at`, `review_due`, `review_outcome`, `rolled_back_at`

**Prospects** (WO-29)
- `prospect_leads` — `source` (website_business_journey | website_advisory_request | broker_reverse | coach_reverse), `payload` (jsonb), `received_at`, `triaged_by_user_id`, `triaged_at`, `outcome` enum (invited, parked, declined)

### 6.2 Row-level security policy template

For each client-scoped table, install a Postgres policy along these lines (see WO-02 for the actual generator):

```sql
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
CREATE POLICY documents_client_scope ON documents
  USING (
    current_setting('fsa.role', true) = 'super_admin'
    OR client_id::text = ANY (string_to_array(current_setting('fsa.client_ids', true), ','))
  );
```

The Laravel middleware (`EnforceClientScope`) sets `fsa.role` and `fsa.client_ids` per request from the authenticated user's resolved access. No raw SQL bypasses this — all data access goes through Eloquent.

---

## 7. Cross-cutting foundations

### 7.1 AI Integrity foundation (used by every WO that touches AI)

The `AiClient` interface accepts a `PromptEnvelope` and returns an `AiResponse` that **always** carries:

- `text` — the model output
- `attributions[]` — list of `{claim, source_reference}` pairs; missing attribution is a hard failure that surfaces an `AiIntegrityViolation` (logged, not silently fixed)
- `uncertainty` — enum (`high`, `medium`, `low`, `none`) — derived from `confidence` + data quality
- `bias_signals[]` — populated by the `BiasDetector` post-process
- `model`, `prompt_version`, `prompt_hash`, `tokens_in`, `tokens_out`

The `PromptEnvelope` always includes:

- The Integrity Preamble (constant text, versioned) — see WO-04 for exact wording, derived from spec §3.1.
- The structured data quality summary for the inputs.
- An explicit instruction that the model must respond with `{text, attributions[], uncertainty}` in a defined JSON schema; freeform prose is rejected.

`FakeAiClient` returns deterministic structured stubs for tests — never random text. Every WO that uses AI **must** have a test path that uses the fake.

### 7.2 Document verification pipeline (WO-18)

```
Upload → Virus scan → Encrypt at rest → Persist Document row (scanner_result: clean)
  → Dispatch VerifyDocumentJob
       → Build claims list from the upload context
              (e.g. questionnaire answer values it was attached to)
       → For each claim: call AiClient::verifyDocument(document, claim)
       → Persist document_verifications row per claim
       → If accuracy_discrepancy: notify advisor (urgent), pause affected analysis
       → If advisory_flag: notify advisor (action)
       → Emit audit_event
```

In Phase 1, "affected analysis pause" means: any downstream analysis WO (Phase 2+) consults `document_verifications` and refuses to render outputs for the affected subject until `resolved_at` is set. We build that gate now even though no analysis modules consume it yet — Phase 2 wires in.

### 7.3 Audit trail (WO-03)

- `AuditWriter::record(action, subject, before, after)` is the only sanctioned write path.
- Every controller action that mutates state goes through an `audit()` method on a `WritesAudit` trait, or through the `LogAuditEvent` middleware for read-tracking on sensitive endpoints (document downloads, T&C views).
- Postgres trigger blocks `UPDATE` and `DELETE` on `audit_events`. Migration installs the trigger.
- A nightly job verifies the audit chain (`request_id` continuity per session); discontinuities raise an admin alert.

### 7.4 Integration scaffolding pattern (every external service)

Every external integration follows the same shape, even when not live:

1. **Interface** (`IntegrationClient` derivative) declaring the methods the rest of the app calls.
2. **Live implementation** (e.g. `NzbnClient`) — real HTTP, behind a `FEATURE_<X>_LIVE` flag.
3. **Stub implementation** — returns canned data; used when flag is off or in test.
4. **Resilience wrap** — every live call goes through `RetryPolicy` (3 attempts, exponential backoff) and `CircuitBreaker`; every call records an `integration_calls` row regardless of outcome.
5. **Health rollup** — `IntegrationHealthSample` rows updated every 5 min by a scheduled job, surfaced on advisor dashboard (WO-30).
6. **Cached fallback** — `Cache::remember` reads displayed with a "Cached at HH:MM" badge in the UI when the live call fails and the cache is used.

This pattern is identical for: NZBN, Companies Office, IRD, FSP, PPSR, LINZ, IPONZ, Stats NZ, RBNZ, MBIE, NZ Parliament, WorkSafe, Stripe, Windcave, Xero/MYOB/QuickBooks, SendGrid/SES, ClamAV, Whisper.

Phase 1 only needs scaffolds for: **NZBN, Companies Office, IRD**, plus the patterns above. All others get an empty interface and stub class so Phase 2/3 work just fills them in.

### 7.5 Brand foundation

- `docs/brand/` holds the Meridian Warm kit, FSA logo files, typography references.
- `resources/css/app.css` declares CSS variables (`--fs-meridian-warm`, `--fs-meridian-deep`, `--fs-meridian-sand`, etc.) matching the brand kit; shadcn theme tokens overridden to reference them.
- `resources/js/components/brand/BrandShell.tsx` wraps every page; injects logo, brand fonts, footer mark.
- All PDF templates (T&C signed acceptance, future reports) start from a `BrandedPdf` layout that pulls the same tokens.

---

## 8. Work Orders (Phase 1, ordered)

> Format per WO: **ID** · **Title** · spec refs · goal · depends-on · files · acceptance criteria · tests · out-of-scope.

---

### WO-01 — Project foundations & critical rules

**Spec refs:** §26 Phase 1 row 1; §3; §4
**Goal:** Establish `CLAUDE.md` at repo root with the critical rules block; archive the spec; mirror this plan and the rating-criteria PDF into `docs/`.
**Depends on:** none.
**Files:**
- `CLAUDE.md` (repo root) — start with a "CRITICAL — DO NOT BREAK" block: AI Integrity Principle five lines, Document Verification three lines, no public registration, MFA mandatory, no PII in logs, no auto-merge of learning updates, every AI call goes through `AiClient`. Then project orientation: this is Phase 1; refer to `PLAN.md` for WOs; never invent features outside the plan.
- `docs/spec/Future_Shift_Advisory_App_Specification_v2.4.docx` (copy from `C:\Users\pgwdu\OneDrive\Desktop\FS Consulting\`).
- `docs/brand/` populated with Meridian Warm assets and FSA logo files (owner to drop in; this WO creates the folder and `README.md` listing required files).
- `docs/rating-criteria/Business_Plan_Rating_Matrix.pdf` (owner to drop in).
- `docs/legal/terms-v1.md` — 14-clause T&C source text in markdown (owner to provide; placeholder file with structure of all 14 clauses if text not yet pasted).
- `docs/dev-setup.md` — section 5 of this plan, expanded with any quirks discovered.
- Update `README.md` with: project description, link to `PLAN.md`, link to `CLAUDE.md`, dev-setup pointer.
**Acceptance criteria:**
- `CLAUDE.md` exists with critical rules block as the first content under the title.
- `docs/` populated as above.
- `README.md` points to `PLAN.md` and `CLAUDE.md`.
- `composer dev` still boots without error.
**Tests:** none (docs-only WO).
**Out of scope:** any code changes.

---

### WO-02 — PostgreSQL migration + RLS scaffold

**Spec refs:** §27; §4 (row-level DB security per client)
**Goal:** Move the project off SQLite onto Postgres; enable `pgcrypto` and `uuid-ossp`; install the RLS policy harness; add `EnforceClientScope` middleware.
**Depends on:** WO-01.
**Files:**
- `config/database.sql` (per-env defaults), `.env` updated per section 5.3.
- New migration `0001_01_01_000000_enable_postgres_extensions.php` — `CREATE EXTENSION IF NOT EXISTS pgcrypto;` and `uuid-ossp`.
- New migration `0001_01_01_000010_install_rls_helpers.php` — Postgres function `fsa_set_request_context(role text, client_ids text)` that sets `fsa.role` and `fsa.client_ids` session vars.
- `app/Http/Middleware/EnforceClientScope.php` — calls the helper at the start of every authenticated request based on the user's accessible client IDs.
- `app/Services/Storage/KeyEnvelope.php` — wraps `Crypt::encryptString`/`decryptString` with a tagged envelope `{alg:"aes-256-gcm", v:1, body:...}`. Interface designed so a PQC version (alg:"kyber-1024-aes-256") can be added in Phase 4 without changing call sites.
- Update `bootstrap/app.php` to register `EnforceClientScope` on the `web` and `api` groups.
- Documentation in `docs/architecture/postgres-rls.md` — how policies are written, how to add a new client-scoped table.
**Acceptance criteria:**
- `php artisan migrate:fresh` runs cleanly against Postgres.
- All existing starter-kit tests pass against Postgres.
- A demo migration that creates a tiny scoped table and installs a policy proves the harness end-to-end (test asserts that querying without `EnforceClientScope` returns zero rows).
- `KeyEnvelope::encrypt`/`decrypt` round-trips with version tag.
**Tests:**
- `tests/Feature/Database/RlsHarnessTest.php` — creates two clients, two users, asserts user A cannot read user B's row.
- `tests/Unit/Storage/KeyEnvelopeTest.php`.
**Out of scope:** real per-table policies (those land in the WO that creates each table).

---

### WO-03 — Immutable audit trail

**Spec refs:** §4 (Immutable Audit Trail); §23 governance prerequisite
**Goal:** Build the `audit_events` table with append-only DB trigger, the `AuditWriter` service, and the `LogAuditEvent` middleware. Verify request-level chain integrity.
**Depends on:** WO-02.
**Files:**
- Migration creating `audit_events` per §6.1, plus trigger `audit_events_append_only` rejecting `UPDATE` and `DELETE`.
- `app/Services/Audit/AuditWriter.php`.
- `app/Concerns/WritesAudit.php` trait.
- `app/Http/Middleware/LogAuditEvent.php`.
- `app/Console/Commands/VerifyAuditChain.php` + scheduled `daily()` in `bootstrap/app.php`.
- `tests/Feature/Audit/AuditTrailImmutableTest.php`.
**Acceptance criteria:**
- Direct `UPDATE`/`DELETE` against `audit_events` raises a Postgres exception.
- `AuditWriter::record` writes `before`/`after` diffs as `jsonb` with no PII in raw form (PII references are stored as opaque IDs; values must use the redaction helper).
- Daily verification command runs without error on a clean DB.
**Tests:** as listed; plus a unit test that the redaction helper masks emails, phone numbers, IRD numbers, NZBN, bank accounts.
**Out of scope:** UI to browse audit (Phase 2).

---

### WO-04 — AI Integrity foundation (AiClient + prompts + bias)

**Spec refs:** §3 (whole); §11
**Goal:** Establish the AI client interface, the Integrity Preamble, the structured response schema, the source-attribution validator, the bias detection layer scaffold, and the `FakeAiClient`.
**Depends on:** WO-03.
**Files:**
- `app/Services/Ai/Contracts/AiClient.php` — methods: `analyse`, `verifyDocument`, `scoreCriterion` (Phase 3 placeholder), `summarise`, `redFlag`.
- `app/Services/Ai/Contracts/AiResponse.php` (DTO) — fields per §7.1.
- `app/Services/Ai/Contracts/PromptEnvelope.php` (DTO).
- `app/Services/Ai/Prompts/IntegrityPreamble.php` — versioned constant text drawn verbatim from spec §3.1 governing statement and five principles.
- `app/Services/Ai/Prompts/PromptRegistry.php` — `register(id, version, body)`; hash-tracked.
- `app/Services/Ai/Integrity/SourceAttribution.php` — validator; raises `MissingAttributionException` if any factual claim lacks a source reference.
- `app/Services/Ai/Integrity/UncertaintyPolicy.php` — derives uncertainty from data quality + model confidence.
- `app/Services/Ai/Integrity/BiasDetector.php` — Phase 1 implementation: logs every output with subject metadata (industry, demographic indicators if known) and tags responses where wording matches a Phase 1 heuristic list (e.g. praise terms without evidence). Phase 4 swaps in the statistical detector.
- `app/Services/Ai/Claude/AnthropicClaudeClient.php` — uses `Http::withToken(env('ANTHROPIC_API_KEY'))`; if key empty, throws `AiUnavailableException` (which the `AiClient` resolver catches and falls back to `FakeAiClient`).
- `app/Services/Ai/Fake/FakeAiClient.php` — deterministic outputs keyed off input hash; used in all tests.
- `app/Providers/AiServiceProvider.php` — binds `AiClient` to live or fake based on env.
- `tests/Unit/Ai/*` and `tests/Feature/Ai/IntegrityEnforcedTest.php`.
**Acceptance criteria:**
- Any response without attributions on a factual claim raises `MissingAttributionException` in tests.
- Bias detector logs an entry for every AI call.
- No code path in the app constructs an `Http::post` to Anthropic outside `AnthropicClaudeClient`.
- When `ANTHROPIC_API_KEY` is empty, all AI methods return a degraded `AiResponse` with `uncertainty=high` and `text="AI unavailable — analysis deferred"`, and emit a notification visible to advisors.
**Tests:** as listed.
**Out of scope:** the verification pipeline itself (WO-18 consumes this); any production prompts beyond the verification one and a smoke `summarise` prompt.

---

### WO-05 — Integration resilience layer

**Spec refs:** §4.2; §25
**Goal:** Build `RetryPolicy`, `CircuitBreaker`, `HealthRecorder`, and the per-call logging into `integration_calls`. Provide a `ResilientHttp` helper every integration uses.
**Depends on:** WO-03.
**Files:**
- Migrations for `integration_calls` and `integration_health_samples`.
- `app/Services/Integration/Resilience/RetryPolicy.php`, `CircuitBreaker.php`, `HealthRecorder.php`.
- `app/Services/Integration/Resilience/ResilientHttp.php` — wraps `Http::*` with retry, breaker, logging, and cached-fallback hooks.
- `app/Console/Commands/AggregateIntegrationHealth.php` — every 5 min, summarises into `integration_health_samples` with green/amber/red per §4.2.
- `tests/Feature/Integration/Resilience/*`.
**Acceptance criteria:**
- A flaky test server fails twice then succeeds → call resolves on attempt 3; `integration_calls` shows two `retry` rows and one `success` row.
- Breaker opens after configurable consecutive failures (default 5 in 60s); subsequent calls short-circuit to cached value or graceful-degradation response without hitting the network.
- Health rollup correctly categorises green/amber/red per thresholds in §4.2.
**Tests:** as listed.
**Out of scope:** dashboard UI (WO-30 surfaces it).

---

### WO-06 — Secure file storage + virus scanning interface

**Spec refs:** §4 (File Scanning, Encryption); §27 (S3 Sydney; ClamAV/VirusTotal)
**Goal:** Provide an encrypted-at-rest filesystem disk for sensitive uploads, with mandatory virus scanning before persistence.
**Depends on:** WO-02, WO-05.
**Files:**
- `config/filesystems.php` — register `secure_local` disk under `storage/app/secure/` with a custom `WriteWrappedAdapter` that uses `KeyEnvelope` to encrypt bytes on write, decrypt on read. Add `secure_s3` (commented config, Phase 2).
- `app/Services/Integration/VirusScanner/Contracts/FileScanner.php` — `scan(stream): ScanResult`.
- `app/Services/Integration/VirusScanner/NoopScanner.php` — returns `clean`.
- `app/Services/Integration/VirusScanner/ClamAvScanner.php` — talks to a local ClamAV daemon over TCP; gated by `FEATURE_VIRUS_SCAN_LIVE`. Includes connection error → `scanner_result=error` and degraded-mode notice.
- `app/Services/Storage/SecureFileWriter.php` — `write(uploadedFile, owner, category): Document` — orchestrates: scan → encrypt → store → create `Document` row.
- `tests/Unit/Storage/*`.
**Acceptance criteria:**
- Writing a file via `SecureFileWriter` results in encrypted bytes on disk (cannot be read without `KeyEnvelope`).
- When the scanner returns `infected`, the file is not persisted and the upload is rejected with an audit event.
- When the scanner errors, the file is persisted but quarantined (`scanner_result=error`, not visible to clients) and an advisor notification is raised.
**Tests:** as listed; include a fixture with an EICAR test string against the noop and a fake-clamav that returns infected.
**Out of scope:** S3 prod adapter (Phase 2); upload UI (WO-18).

---

### WO-07 — User roles, permissions, RBAC

**Spec refs:** §5
**Goal:** Install `spatie/laravel-permission`; seed the 9 user types and their permission matrices; gate every controller via the `EnsureRole` middleware and per-resource policies.
**Depends on:** WO-02, WO-03.
**Files:**
- Composer add `spatie/laravel-permission`.
- Migration publishing the Spatie tables; seeders `RoleSeeder`, `PermissionSeeder` defining the matrix.
- Permission constants in `app/Enums/Permission.php`.
- Policies for: `Client`, `Document`, `Questionnaire`, `Notification`, `KnowledgeEntry`, `ProspectLead`, `TermsVersion`, `AuditEvent`.
- `app/Http/Middleware/EnsureRole.php` and `EnsurePermission.php`.
- `tests/Feature/Authorization/*`.
**Acceptance criteria:**
- Each of the 9 user types in §5 has a documented capability table, codified as Spatie role + permissions, with a test asserting the matrix.
- Junior advisor cannot publish, release proposals, manage payments, send referrals, or approve learning updates — even via direct route hits.
- DD guest (Phase 3) is represented as a token type only — no `User` row.
**Tests:** matrix tests as above.
**Out of scope:** UI for managing roles (Phase 2).

---

### WO-08 — Invite-only registration + MFA enforcement

**Spec refs:** §4 (Invite-only; MFA mandatory)
**Goal:** Replace Fortify's default registration with the invite-only flow; mandate MFA enrolment before any non-auth route is reachable.
**Depends on:** WO-07.
**Files:**
- Migrations: `invite_tokens`, `mfa_factors`.
- `app/Services/Security/InviteIssuer.php` — issue token (hash stored, plain emailed), set TTL from `INVITE_TOKEN_TTL_HOURS`.
- Override Fortify register route → 404; build `routes/auth.php` with `/invite/{token}` accept flow that sets the password, requires MFA enrolment, then T&C acceptance (WO-11).
- `app/Http/Middleware/RequireMfa.php` — redirects to MFA challenge if `mfa_enabled_at` is null or current session lacks MFA.
- `app/Services/Security/MfaChallenger.php` — TOTP using `pragmarx/google2fa-laravel` or built-in `Fortify` 2FA; bind to `MfaChallenger` interface so WebAuthn (Phase 2) drops in.
- Admin UI: `resources/js/pages/admin/invitations/Index.tsx` and `Create.tsx` — issue invite by email + user_type + role.
- `tests/Feature/Auth/InviteFlowTest.php`, `MfaEnforcementTest.php`.
**Acceptance criteria:**
- Direct GET to `/register` returns 404.
- Accepting an invite forces password set → MFA enrolment → T&C acceptance, in that order, before redirecting to the user's portal home.
- Logging in without an MFA-enrolled session is impossible.
- Invite tokens expire and one-shot (no replay after `accepted_at`).
**Tests:** as listed; plus integration test that an unauthenticated user cannot create a user via any route.
**Out of scope:** WebAuthn implementation; SSO; risk-based step-up signals (WO-09).

---

### WO-09 — Session management + step-up MFA

**Spec refs:** §4 (Session Management; Step-up MFA on risk signals)
**Goal:** Enforce configurable session timeouts per user type; surface risk signals to require re-MFA mid-session.
**Depends on:** WO-08.
**Files:**
- `sessions` table extension migration: `risk_score`, `step_up_at`.
- `app/Services/Security/StepUpEvaluator.php` — Phase 1 signals: IP/country change, user-agent change, suspicious endpoint hit (admin/super-admin route from a new device).
- Middleware on every authenticated route: evaluate, and if score over threshold, redirect to MFA challenge with `?reason=step_up`.
- Config: per-user-type timeout minutes (default 30; super-admin 15; client 60). Reads from `users.session_timeout_minutes` with fallback to config.
- `tests/Feature/Security/StepUpTest.php`, `SessionTimeoutTest.php`.
**Acceptance criteria:**
- Inactive session beyond the user's configured timeout requires re-auth.
- Visiting a super-admin route from a never-seen-before device triggers step-up.
- Step-up failures are audit-logged.
**Tests:** as listed.
**Out of scope:** behavioural biometric signals (Phase 4).

---

### WO-10 — Terms model + version control + admin clause editor

**Spec refs:** §18
**Goal:** Persist the 14-clause T&C structure, version it with material/non-material distinction, expose an admin UI for editing whole-doc and per-clause updates, and import the v1 document.
**Depends on:** WO-07.
**Files:**
- Migrations: `terms_versions`, `terms_clauses`.
- `app/Models/TermsVersion.php`, `TermsClause.php`.
- Seeder `TermsVersionSeeder.php` — reads `docs/legal/terms-v1.md` and builds version 1 with 14 clauses.
- `app/Http/Controllers/Admin/TermsController.php` — list versions, draft new version, edit clauses, mark clause material/non-material, publish.
- `resources/js/pages/admin/terms/*` — Inertia pages: index, edit, preview, publish.
- Policy: only `super_admin` can publish.
- Publishing flow: choose `material` (triggers 30-day notice + re-acceptance for affected users) or `non-material` (audit-trail only).
- `tests/Feature/Admin/TermsVersioningTest.php`.
**Acceptance criteria:**
- Publishing a material version queues a 30-day notification to every user with an active acceptance of the prior version and marks their `terms_acceptances` row as `expires_at = published_at + 30 days`.
- Non-material publish does not touch `terms_acceptances` but writes an `audit_events` row.
- All prior versions remain readable forever.
**Tests:** as listed.
**Out of scope:** PDF rendering of T&C (WO-11).

---

### WO-11 — T&C acceptance gate + signed-PDF generation

**Spec refs:** §4 (T&C Gate); §7 (T&C Gate); §13 (proposal sign-off references same primitive); §18
**Goal:** Build the global accept/decline gate, the scroll-to-end enforcement, the signed-PDF generation, and the suspended-on-decline state.
**Depends on:** WO-10, WO-06.
**Files:**
- `app/Http/Middleware/RequireAcceptedTerms.php` — every authenticated route except `/terms/*` redirects to the gate when the active version is unaccepted or expired.
- Inertia page `resources/js/pages/terms/Gate.tsx` — long-form scrollable T&C; accept button disabled until scroll-end event.
- `app/Services/Pdf/BrowsershotRenderer.php` — uses Spatie Browsershot (puppeteer). Composer add `spatie/browsershot`. Node already present.
- `app/Services/Terms/SignedAcceptancePdf.php` — renders a branded PDF capturing accepted version, clauses, user identity, IP, UA, timestamp; signs with `KeyEnvelope` (hash + envelope tag stored in `terms_acceptances`).
- On decline: redirect to `resources/js/pages/terms/Declined.tsx`; suspend user (`suspended_at`); notify advisor + super-admin; do not delete account.
- `tests/Feature/Terms/AcceptanceGateTest.php`.
**Acceptance criteria:**
- Accept button disabled in DOM until a `scroll-end` event from the gate page.
- Acceptance writes `terms_acceptances` row with signed PDF stored on `secure_local`.
- Decline suspends user and creates urgent notifications to advisor and super-admin.
- Material T&C re-publish forces existing users back to the gate within 30 days.
- The PDF includes the exact clause text of the accepted version.
**Tests:** as listed.
**Out of scope:** the proposal sign-off flow (Phase 2/3 reuses this primitive).

---

### WO-12 — Centralised notifications + channel preferences

**Spec refs:** §6 (Communication Preferences); §26 Phase 1 (Centralised notifications, channel-preference aware)
**Goal:** Wire Laravel notifications through a `ChannelResolver` that honours per-user channels (email_only / in-platform-only / both) and frequency (immediate / daily / weekly), with urgent always bypassing preference.
**Depends on:** WO-07, WO-09.
**Files:**
- Migration: `communication_preferences`; extend `notifications` table with `urgency` and `channel_decision`.
- `app/Services/Notifications/ChannelResolver.php`.
- `app/Notifications/` base class wrapper that consults the resolver.
- Scheduled jobs: `DispatchDailyDigest`, `DispatchWeeklyDigest`.
- Settings UI in user profile (`resources/js/pages/settings/communication.tsx`) — built on top of Fortify settings routes.
- `tests/Feature/Notifications/ChannelResolverTest.php`.
**Acceptance criteria:**
- User with "in-platform only, weekly" never receives email for non-urgent notifications; urgent flagged ones bypass the rule.
- Channel decision logged on each notification for audit.
- Frequency aggregation works without losing notifications across windows.
**Tests:** as listed.
**Out of scope:** notification bell UI (WO-24).

---

### WO-13 — NZ integration scaffolds (NZBN, Companies Office, IRD)

**Spec refs:** §25; §6 (NZBN auto-population)
**Goal:** Implement three integration clients following the pattern in §7.4. Stubs deliver canned data for known NZBNs; live mode is gated by `FEATURE_*_LIVE`. No credentials configured yet — all stubbed.
**Depends on:** WO-05.
**Files:**
- `app/Services/Integration/Nzbn/{Contracts,NzbnClient,FakeNzbnClient}` and equivalent for `CompaniesOffice` and `Ird`.
- Each `Contracts/*Client.php` interface lists the methods needed by Phase 1+2 (e.g. `lookupByNzbn`, `directorsForCompany`, `gstStatus`).
- Stubs read from `database/fixtures/integration/*.json` so tests and dev use consistent canned data.
- Live implementations: full HTTP using `ResilientHttp`; throw `IntegrationDisabledException` when feature flag off, caught by the resolver which falls back to the stub.
- `app/Providers/IntegrationServiceProvider.php` — binds interfaces per feature flag.
- Empty-but-named scaffold files for: FSP, PPSR, LINZ, IPONZ, Stats NZ, RBNZ, MBIE, NZ Parliament, WorkSafe, Stripe, Windcave, Xero, MYOB, QuickBooks, SES/SendGrid, Whisper, Google Calendar, Microsoft Graph — interface only, stub only; no behaviour. Future WOs fill them in.
- `tests/Feature/Integration/NzbnLookupTest.php`, `CompaniesOfficeTest.php`, `IrdTest.php`.
**Acceptance criteria:**
- `NzbnClient::lookupByNzbn('9429000000000')` returns canned data in dev/test.
- Toggling `FEATURE_NZBN_LIVE=true` without a credential errors gracefully via the resilience layer and falls back to cached data plus a UI badge.
- All 20 scaffolded integrations have an interface + stub file present so a Phase 2/3 engineer doesn't need to invent the shape.
**Tests:** as listed.
**Out of scope:** any live API agreements; the actual analysis or proposal flows that consume the data.

---

### WO-14 — Add New Client (engagement-type aware)

**Spec refs:** §6; §2.1
**Goal:** Build the "Add New Client" flow where the advisor picks engagement type, enters NZBN, sees auto-populated registry data, and creates the client record with the conflict-of-interest gate.
**Depends on:** WO-07, WO-13, WO-22, WO-21.
**Files:**
- Migration: `clients`, `client_team`, `conflict_declarations` (latter created by WO-21 if independent — order them to land conflict before client).
- `app/Models/Client.php` + `EngagementType` enum.
- `app/Http/Controllers/Advisor/ClientController.php`.
- Inertia pages: `resources/js/pages/advisor/clients/Create.tsx`, `Show.tsx`, `Index.tsx`.
- Auto-population action: `app/Actions/Clients/PopulateFromNzbn.php` — calls NZBN + Companies Office + IRD clients in parallel.
- Conflict-of-interest declaration check at create time (advisor must declare or affirm none before saving).
- `tests/Feature/Advisor/AddClientTest.php`.
**Acceptance criteria:**
- Advisor picks engagement type (one of four); the chosen type is locked once questionnaire responses are saved.
- NZBN lookup auto-populates fields and stamps the source.
- Conflict declaration required before save.
- Data Quality calculated initially as "Insufficient" until questionnaire responses arrive (see WO-19).
**Tests:** as listed.
**Out of scope:** standard-advisory questionnaire content (WO-17); DD-specific questionnaire (Phase 3).

---

### WO-15 — Add New Entrepreneur (basic profile)

**Spec refs:** §17 (skeleton only — full Entrepreneur Module is Phase 3); §5 (user_type)
**Goal:** Capture entrepreneur profiles in Phase 1 so invites can flow; full module functionality lands Phase 3.
**Depends on:** WO-14.
**Files:**
- Migration: `entrepreneur_profiles`.
- `app/Http/Controllers/Advisor/EntrepreneurController.php` — `index`, `create`, `show`. `create` issues an invite (WO-08).
- Inertia pages: `resources/js/pages/advisor/entrepreneurs/{Index,Create,Show}.tsx`.
- Stage progression: only `invited` and `onboarding` reachable in Phase 1.
- `tests/Feature/Advisor/AddEntrepreneurTest.php`.
**Acceptance criteria:**
- Adding an entrepreneur issues an invite; on accept the entrepreneur lands on a placeholder portal page noting Phase 3 features.
- Phase 1 capacity check is in place (default 30 active entrepreneurs per advisor; warning at 24, hard block at 30 per spec §17.7).
**Tests:** as listed.
**Out of scope:** readiness assessment, idea validation, plan builder, scoring (Phase 3).

---

### WO-16 — Client portal shell + 7-step onboarding wizard

**Spec refs:** §7
**Goal:** A WCAG 2.1 AA, mobile-responsive, Meridian Warm-branded portal shell with the 7-step onboarding wizard. Engagement type determines questionnaire set in Step 5.
**Depends on:** WO-11, WO-12, WO-14.
**Files:**
- `resources/js/layouts/PortalLayout.tsx` — top nav, mobile menu, brand shell, notification bell stub.
- `resources/js/pages/portal/Dashboard.tsx` — completion progress, data quality indicator, milestones placeholder (Phase 2), messages link, notifications, referral status placeholder.
- Wizard pages (7 steps): Welcome, Identity Verification (MFA already done; this confirms profile), Business Snapshot (NZBN auto-pop view), Goals (high-level only in Phase 1), Questionnaire (engagement-type dependent — see WO-17), Documents (WO-18), Review & Submit.
- Wizard state persisted server-side (`onboarding_wizard_state` jsonb on `clients`).
- `tests/Feature/Portal/OnboardingWizardTest.php`.
**Acceptance criteria:**
- Wizard step order enforced server-side (cannot jump ahead).
- Engagement type drives the questionnaire shown in Step 5 (standard advisory in Phase 1; DD/post-acquisition placeholders gated as "Coming in Phase 3").
- Mobile layout passes Lighthouse accessibility ≥ 95 on the Welcome page (manual check; not a test).
**Tests:** as listed.
**Out of scope:** offline PWA mode (Phase 3); funnel analytics (Phase 2).

---

### WO-17 — Questionnaire engine

**Spec refs:** §8
**Goal:** A reusable questionnaire engine supporting all question types, conditional logic, drag-and-drop builder (admin), help text, version control, real-time preview. Seed the Standard Advisory question set per §8.
**Depends on:** WO-14, WO-16.
**Files:**
- Migrations: `questionnaires`, `questionnaire_sections`, `questionnaire_questions`, `questionnaire_responses`, `questionnaire_answers`.
- `app/Models/Questionnaire*.php`.
- Admin builder UI: `resources/js/pages/admin/questionnaires/*` — list, edit, preview. Drag-and-drop ordering using `@dnd-kit/core` (composer-add equivalent npm).
- Renderer used in the portal wizard Step 5; supports text, long-text, number, currency, date, single-select, multi-select, file-attach (uses WO-18 upload), Likert.
- Conditional logic: simple `{ when: questionId, equals/in: value, show: questionId }` rules; engine evaluates in TS and server-side.
- Seeder: Standard Advisory set per §8 (Business Overview, Products and Services, Market and Customers, Financial Position, People and HR, Operations, Sales and Marketing, Strategy and Goals, Compliance and Risk, Owner and Leadership).
- `tests/Feature/Questionnaire/*`.
**Acceptance criteria:**
- Standard Advisory set seeds cleanly with the 10 sections.
- Conditional logic hides/shows questions consistently in TS preview and on server submit (server is source of truth).
- File-attach questions integrate with WO-18 so attached documents are recorded against the answer in `attached_document_ids`.
**Tests:** as listed.
**Out of scope:** DD-specific, post-acquisition gap, entrepreneur questionnaires (Phase 3); AI optimisation suggestions (Phase 2 — feed the learning update queue then).

---

### WO-18 — Document upload + verification pipeline

**Spec refs:** §9 (whole); §27 (PDF & file storage)
**Goal:** Upload pipeline that scans, encrypts, persists, then dispatches an AI verification job per attached claim. Surface verified/advisory/discrepancy outcomes in the portal and on the advisor dashboard.
**Depends on:** WO-04, WO-06, WO-17.
**Files:**
- Migrations: `documents`, `document_verifications`.
- `app/Http/Controllers/DocumentController.php` — secure upload endpoint (uses `SecureFileWriter`).
- `app/Jobs/VerifyDocumentJob.php` — builds claims from the upload context (questionnaire answer values it was attached to), calls `AiClient::verifyDocument(document, claim)` per claim, persists outcomes.
- `app/Services/Ai/Verification/DocumentVerifier.php` — orchestrates per-claim verification with structured prompt (defined in `PromptRegistry`).
- UI primitives: `resources/js/components/verification/{Badge,FlagBanner,DiscrepancyDialog}.tsx`.
- Portal: every document tile shows current verification state and (for clients) a plain-English explanation of any flag.
- Advisor dashboard fragment (`DocumentVerificationFlagPanel`) lists all outstanding `advisory_flag` and `accuracy_discrepancy` rows across all clients.
- "Affected analysis paused" gate: a `DocumentVerificationGate` helper that any Phase 2 analysis must call before rendering output.
- `tests/Feature/Documents/{UploadTest,VerificationOutcomesTest,DiscrepancyBlocksAnalysisTest}.php`.
**Acceptance criteria:**
- Uploading a clean file results in a `document_verifications` row per attached claim, with outcome populated within one job cycle.
- A simulated discrepancy from `FakeAiClient` creates an urgent advisor notification and surfaces in the advisor flag panel.
- The `DocumentVerificationGate` blocks a Phase 2 placeholder analysis from rendering output (proven via a temporary test analysis class).
- No code path persists a file without first running the scanner.
**Tests:** as listed.
**Out of scope:** advisor resolution UI for discrepancies (Phase 2 builds a richer workflow; Phase 1 lets advisor mark `resolved_at` with a note).

---

### WO-19 — Data quality gate

**Spec refs:** §10 intro (data quality gate runs before every analysis); §6 (Data Quality Score)
**Goal:** A composable `DataQualityScorer` that examines a client's questionnaire completeness, document support, and answer freshness; surfaces a score on the profile header; refuses to run any (Phase 2+) analysis below threshold.
**Depends on:** WO-17, WO-18.
**Files:**
- `app/Services/DataQuality/DataQualityScorer.php` — pluggable signal providers; Phase 1 signals: questionnaire % complete, % of answers with supporting docs, % of docs in `verified` state, age of last update.
- `app/Services/DataQuality/Gate.php` — `assertSufficient(client, requirement)` throws when below.
- Profile header shows current score (`High`/`Medium`/`Low`/`Insufficient`) with a tooltip explanation.
- `tests/Feature/DataQuality/*`.
**Acceptance criteria:**
- Score recomputes on every relevant data event and updates the client header within one job cycle.
- The gate raises an exception when invoked below requirement; UI shows a clear "Improve data first" message.
- Score components are individually explainable in the UI (no black-box numbers).
**Tests:** as listed.
**Out of scope:** any analysis itself (Phase 2).

---

### WO-20 — Wellbeing check-in

**Spec refs:** §6 (Wellbeing Check-In); §15 (signal calibration is Phase 3)
**Goal:** Monthly optional 2-question pulse in the portal; responses visible to advisor only; low scores raise an internal signal (consumed in Phase 3 by the coaching referral signals).
**Depends on:** WO-16.
**Files:**
- Migration: `wellbeing_checkins`.
- Portal widget: `resources/js/pages/portal/wellbeing/Pulse.tsx`.
- Monthly trigger via scheduled command + notification.
- Advisor view in client profile: trend chart.
- Internal signal: when two consecutive months ≤ 2 on personal coping, write a `learning_updates`-adjacent record on a `coaching_signals` table (table created as scaffolding; UI later).
- `tests/Feature/Wellbeing/*`.
**Acceptance criteria:**
- Check-in is optional; clients see a soft prompt but can skip.
- Responses are not visible to anyone except the assigned advisor and super-admins.
- Signal generation logic correct per §15.4.
**Tests:** as listed.
**Out of scope:** coaching referral workflow (Phase 3).

---

### WO-21 — Conflict of interest declaration

**Spec refs:** §6; §14; §15
**Goal:** Mandatory declaration primitive used at: client creation, before any broker referral, before any coach referral, before any DD engagement.
**Depends on:** WO-07.
**Files:**
- Migration: `conflict_declarations`.
- `app/Services/Conflicts/ConflictDeclarer.php` — `require(advisor, client, referralType): Declaration` — raises if missing or stale.
- Inertia modal component used inline wherever required.
- `tests/Feature/Conflicts/*`.
**Acceptance criteria:**
- Cannot save a client (WO-14) without a declaration recorded against the creating advisor.
- Cannot send any referral (Phase 3 wiring) without a fresh declaration (re-declared per referral type).
**Tests:** as listed.
**Out of scope:** broker/coach referrals themselves (Phase 3).

---

### WO-22 — Structured offboarding

**Spec refs:** §6 (Structured Offboarding)
**Goal:** A documented offboarding flow that triggers when an engagement is marked complete; auto-generates the final progress report (placeholder PDF in Phase 1), engagement summary, handover document, exit interview record, privacy notice, re-engagement reminder.
**Depends on:** WO-11 (PDF), WO-12 (notifications).
**Files:**
- Migration: `offboarding_records`.
- `app/Services/Offboarding/OffboardingService.php`.
- Inertia page `resources/js/pages/advisor/clients/Offboard.tsx`.
- Re-engagement reminder scheduled at +90 days post-offboarding (configurable).
- `tests/Feature/Offboarding/*`.
**Acceptance criteria:**
- Triggering offboarding produces the artifacts (PDFs are simple branded placeholders that note Phase 2 will enrich them), notifies the client per their channel preference, and reduces the advisor capacity counter.
- Re-engagement reminder fires per schedule.
**Tests:** as listed.
**Out of scope:** rich report generation (Phase 2).

---

### WO-23 — Client lifecycle management (status, suspension, restore)

**Spec refs:** §5 (suspended on T&C decline); §6 (lifecycle); §22 (advisor dashboard)
**Goal:** Centralise client status transitions (`active`, `paused`, `suspended`, `offboarded`) and the audit/notification side effects of each.
**Depends on:** WO-14, WO-22.
**Files:**
- `clients.status` enum migration extension.
- `app/Services/Clients/LifecycleManager.php` — state transitions only via this service; emits audit + notifications.
- Inertia controls on the client profile.
- `tests/Feature/Clients/LifecycleTest.php`.
**Acceptance criteria:**
- All transitions go through `LifecycleManager`; direct model writes blocked by an observer.
- Suspending a client revokes their portal access immediately; restoring re-grants without losing data.
**Tests:** as listed.
**Out of scope:** none.

---

### WO-24 — Notification centre UI

**Spec refs:** §22 (advisor dashboard); §7 (client portal notifications)
**Goal:** A bell icon + popover + dedicated `/notifications` page wired to the channel-preference-aware delivery; bulk mark-read; urgent badging.
**Depends on:** WO-12, WO-16.
**Files:**
- Components: `NotificationBell.tsx`, `NotificationPopover.tsx`; page `pages/notifications/Index.tsx`.
- Echo/pusher optional; Phase 1 uses Inertia polling every 30s for unread count.
- `tests/Feature/Notifications/CentreTest.php`.
**Acceptance criteria:**
- Unread count badge updates on mark-read.
- Urgent items visually distinct and bypass any "do not disturb" UI hint (consistent with spec rule that urgent always reaches user).
**Tests:** as listed.
**Out of scope:** websocket-driven push (Phase 2).

---

### WO-25 — In-app messaging (threaded)

**Spec refs:** §21 (In-App Messaging)
**Goal:** Threaded messaging between advisor and client (and entrepreneur). Stored per client; channel preference respected for notifications about new messages.
**Depends on:** WO-12, WO-16, WO-18 (attachments).
**Files:**
- Migrations: `message_threads`, `message_thread_participants`, `messages`.
- Controllers + Inertia pages in both portal and advisor command centre.
- Attachments through the document pipeline (so verification still runs).
- `tests/Feature/Messaging/*`.
**Acceptance criteria:**
- Messages are scoped per client via RLS.
- Sending a message creates a notification respecting the recipient's channel preference.
- Attached documents go through scan + verification.
**Tests:** as listed.
**Out of scope:** broker/coach messaging (Phase 3).

---

### WO-26 — Email-from-app

**Spec refs:** §21
**Goal:** Allow advisors to send emails through the platform; auto-save to the client's communication log; channel-preference aware (do not double-send if a parallel in-app message exists).
**Depends on:** WO-12, WO-25.
**Files:**
- `app/Services/Communications/EmailFromApp.php` — wraps `Mail::send`, logs to `messages` thread as `channel: email`.
- Inertia page `pages/advisor/clients/Compose.tsx`.
- `tests/Feature/Communications/EmailFromAppTest.php`.
**Acceptance criteria:**
- Sent emails appear in the client's communication log with delivery state.
- Channel decision recorded; no duplicate-channel sends for the same logical message.
**Tests:** as listed.
**Out of scope:** SES production wiring (configured in WO-30/env; cutover at production).

---

### WO-27 — Advisor knowledge base (manual entry)

**Spec refs:** §11 (Advisor Knowledge Base); §26 Phase 1 row
**Goal:** Private methodology repository owned by each advisor; manual entry only in Phase 1; AI-assisted capture lands in Phase 3.
**Depends on:** WO-07.
**Files:**
- Migration: `knowledge_entries`.
- Inertia pages `pages/advisor/knowledge/*`.
- Search via Postgres `tsvector` (gin index) — Phase 1 keeps it simple.
- `tests/Feature/Knowledge/*`.
**Acceptance criteria:**
- Each advisor sees only their own entries (unless explicitly shared with another advisor; Phase 1 has no sharing — placeholder).
- Search ranks by relevance.
**Tests:** as listed.
**Out of scope:** AI-assisted capture; template library (Phase 3).

---

### WO-28 — Advisor dashboard shell with verification flag panel

**Spec refs:** §22; §9 (Audit/Learning panel reference)
**Goal:** A minimal advisor dashboard that surfaces what Phase 1 produces: assigned clients with health, document verification flags, T&C re-acceptance pending, prospect inbox (WO-29), integration health (WO-30).
**Depends on:** WO-14, WO-18, WO-29, WO-30.
**Files:**
- Layout: `resources/js/layouts/AdvisorLayout.tsx`.
- Page: `pages/advisor/Dashboard.tsx` composed of widgets:
  - `MyClientsHealth` (data quality, last activity)
  - `DocumentVerificationFlagPanel`
  - `PendingTermsReacceptance`
  - `ProspectInbox`
  - `IntegrationHealth`
- `tests/Feature/Advisor/DashboardTest.php`.
**Acceptance criteria:**
- Each widget pulls live data scoped to the advisor's clients.
- Dashboard renders in ≤ 500ms server time on seeded test data (loose budget; not enforced in CI).
**Tests:** as listed.
**Out of scope:** Phase 2/3 panels (proposals, payments, broker, coach, DD, learning queue) — leave placeholder slots.

---

### WO-29 — Website integration layer (prospect capture)

**Spec refs:** §21; §25; §26 Phase 1 (Website integration layer)
**Goal:** Public API endpoints for "Start Your Business Journey" and "Request Advisory Conversation" website forms; deliver them into a triaged prospect inbox on the advisor dashboard. Invite-only model preserved — no platform access granted automatically.
**Depends on:** WO-05, WO-12.
**Files:**
- Migration: `prospect_leads`.
- `app/Http/Controllers/Webhook/ProspectIntakeController.php` — accepts signed JSON from the FSA site (HMAC shared-secret).
- `app/Http/Controllers/Advisor/ProspectInboxController.php` + Inertia pages.
- `tests/Feature/Webhook/ProspectIntakeTest.php`.
**Acceptance criteria:**
- A POST to the intake endpoint with valid HMAC creates a `prospect_leads` row and notifies the advisor.
- Invalid HMAC rejected with 401 and audit event.
- Triage outcomes (`invited`/`parked`/`declined`) are recorded; invited prospects launch the WO-08 invite flow.
**Tests:** as listed.
**Out of scope:** the FSA website itself.

---

### WO-30 — API health dashboard

**Spec refs:** §4.2; §22 (API Health row)
**Goal:** Surface `integration_health_samples` as a live Green/Amber/Red dashboard for super-admins and advisors; alert when any service stays unavailable > 30 min.
**Depends on:** WO-05, WO-12.
**Files:**
- Inertia page `pages/admin/integration-health/Index.tsx`.
- Alerting job: scans for any integration in red state > 30 min; raises urgent notification to super-admin.
- `tests/Feature/Integration/HealthDashboardTest.php`.
**Acceptance criteria:**
- Dashboard reflects rollups in near-real-time (≤ 5 min lag).
- Stuck-red alert fires once per stuck window (no spam).
**Tests:** as listed.
**Out of scope:** automated remediation (Phase 4).

---

## 9. Acceptance & Definition of Done (per WO)

A WO is "done" only when **all** of the following are true:

1. All listed files exist; no orphan files added.
2. Every acceptance criterion is demonstrably true (PR description quotes the test names or screenshots).
3. New tables documented in `docs/architecture/schema.md` (auto-generated section ok).
4. PHPUnit, Pint, ESLint, Prettier, and `tsc --noEmit` all pass.
5. The WO has its own branch and PR linked to the WO ID.
6. No `TODO`/`FIXME` left in shipped code without a linked follow-up WO ID.
7. No `console.log`, `dd()`, or `dump()` left in shipped code.
8. No raw secrets committed; all external integrations gated by env flags.
9. The WO did not silently introduce a Phase 2+ feature.
10. The AI Integrity Principle and Document Verification gates are intact (if the WO touched any AI-adjacent path).

## 10. Test strategy

- **Feature tests** — every public-facing surface (controllers, jobs, scheduled commands). Use Postgres test DB (`futureshift_test`).
- **Unit tests** — pure services (scorers, validators, redactors, key envelope, retry policy, channel resolver).
- **Authorization matrix test** — single test class enumerates user_types × endpoints; asserts the §5 matrix.
- **RLS test** — proves cross-client reads are impossible without `EnforceClientScope` bypass (and that bypass is only reachable from a super-admin context).
- **Audit immutability test** — direct UPDATE/DELETE attempts raise.
- **AI integrity test** — any AI-touching test uses `FakeAiClient`; a global test fixture asserts the live client is never bound in test env.
- **Contract tests** — every external integration interface has a contract test that both the live and stub implementations satisfy.
- **Accessibility smoke** — manual Lighthouse pass on Welcome, Login, T&C Gate, Dashboard pages noted in PR.

## 11. Deferred work — forward roadmap (Phases 2–4)

This is a faithful condensed view of spec §26 for Phase 2–4; treat as the next plans to write, not work to start now.

### Phase 2 — Intelligence (Months 3–6)
- AI analysis engine core with feedback capture + source attribution.
- Bias detection layer — full implementation.
- Financial analysis + continuous health monitoring for connected accounting (Xero/MYOB/QuickBooks).
- PV module — all 3 calculation types + all 4 discount rate methods + waterfall chart.
- Website audit, competitor analysis, SWOT/TOWS/MAPS.
- NZ regulatory compliance checker + legislative currency monitoring.
- Regulatory change impact assessment.
- Insurance risk flag detection.
- HR/people, operational, systems review analysis modules.
- AI red flag alerts + client knowledge assessment.
- Scenario planning (5 scenarios, PV impact, NZ economic overlay).
- Succession planning (exit readiness, options, PV target, owner dependency).
- Fee calculator (hours-based, outcome-based PV-integrated, entrepreneur structure).
- Five-report system (Client, Advisor, DD, Stakeholder, Entrepreneur Assessment).
- Stakeholder report; business health trajectory report.
- Fee proposal generation, release control, proposal expiry.
- Industry intelligence briefings; automated pre-meeting brief.
- NZ economic indicators feed (all sources).
- Accounting API integrations (Xero, MYOB, QuickBooks).
- Valuation multiple data feed (quarterly).
- Funnel analytics.
- Practice health report with PV portfolio view.
- Wellbeing check-in (extension — Phase 1 builds the primitive).

### Phase 3 — Engagement, Commerce, DD, Entrepreneur, Broker, Coach (Months 6–9)
- Goals/milestones tracker (PV-linked, proof-of-completion with document AI review).
- Digital proposal sign-off flow (7-step) + insurance & coach consent capture.
- Credit card + direct debit authority schedules.
- Stripe + Windcave integration (API resilience failover).
- Monthly payment processing + receipts; proposal expiry/renewal flow.
- Broker portal (full) + coach portal (full, 5 specialisations) + coach panel agreement.
- Coaching referral signal detection (informs advisor only).
- Conflict of interest declaration on broker/coach referrals (Phase 1 primitive already in place).
- DD questionnaire + virtual data room + guest upload + 8 workstreams + valuation + foreign currency + business plan builder + DD report generation.
- Post-acquisition pipeline + intelligent gap questionnaire + auto-generated proposal.
- Entrepreneur module full build: readiness, idea validation, 5-phase plan builder, AI-guided building with predictive score, document upload with verification, admin-managed rating framework (founding criteria from `docs/rating-criteria/Business_Plan_Rating_Matrix.pdf`), AI first-pass + advisor assessment + mentor notes, iterative resubmission, 4-part assessment report + concept PV, NZ resource layer, anonymous benchmarking, viability alerts, advisory readiness indicator + conversion pipeline, living business plan, advisor capacity management, DD integration.
- T&C version manager UI (Phase 1 has the model + admin editor already).
- Learning update queue UI + audit trail + rollback (Phase 1 has the tables already).
- All 32 learning layers scheduled cadence setup.
- Testimonial capture, voice-to-text notes, bulk communications, document expiry tracking.
- Offline mode for client portal (PWA).

### Phase 4 — Intelligence expansion (Months 9–12)
- Full continuous learning engine (all 32 layers active).
- Bias detection automated calibration.
- DD finding pattern learning + valuation accuracy learning.
- Industry-specific plan quality benchmarks.
- Conversion outcome learning (long-term signal).
- Rating predictive validity testing.
- Cross-client competitive intelligence; shared intelligence layer; anonymous NZ SME benchmarking.
- Anonymous peer network (SME and entrepreneur).
- Voice assistant (Siri, Google Assistant).
- Advisor API layer (read-only + limited write).
- Additional NZ business tool integrations.
- Multi-advisor scaling + team management.
- Mobile app foundation (iOS, Android).
- Industry WACC data feed full automation.
- Annual third-party security and legal audit framework.
- **PQC envelope swap-in** — replace AES-only `KeyEnvelope` with Kyber/Dilithium per §4; provision HSM (CloudHSM or Azure Dedicated HSM).

---

## 12. Open risks & decisions to revisit

| # | Risk / open item | Owner action | Trigger to revisit |
|---|---|---|---|
| R1 | Anthropic API key not yet provisioned — all AI features run in degraded mode | Owner to obtain key | Before any client onboarding test |
| R2 | NZBN, Companies Office, IRD live access not yet arranged — running on stubs | Owner to register | Before first real client added |
| R3 | NZ-qualified lawyer must review T&C (spec §27, §28 closing note) before go-live | Owner to engage | Before Phase 1 release to any real user |
| R4 | NZ-qualified developer or auditor should review crypto/security choices (spec §27) | Owner to engage | Before Phase 1 release |
| R5 | Meridian Warm brand kit not yet decoded into design tokens — depends on owner-provided files in `docs/brand/` | Owner to drop files; WO-01 acceptance covers the placement | Before any client-facing UI is shown externally |
| R6 | PQC deferral is a deviation from spec §4 — must be documented in `docs/architecture/security-decisions.md` and re-affirmed before production | Owner sign-off | Before production cutover |
| R7 | ClamAV deployment plan for production — Phase 1 ships the interface only | Resolve when picking prod infra | Before first production upload |
| R8 | DB-level RLS is dependent on the request setting `fsa.role`/`fsa.client_ids` — a bug in the middleware bypasses RLS | Covered by test in WO-02; add a CI assertion that middleware is bound | Ongoing |
| R9 | Inertia + SSR is not enabled by default — fine for Phase 1; revisit when SEO matters | None Phase 1 | Phase 4 (mobile/web SEO) |
| R10 | The 9-user-type matrix in spec §5 has some ambiguity around "Junior Advisor / Analyst" approval scope — locked here as draft-only, but revisit with owner | Owner confirm matrix in WO-07 PR | WO-07 PR review |

---

## Appendix A — Spec feature → WO mapping

For traceability. Use this when reviewing Phase 1 completeness against the spec.

| Spec section | Feature | WO(s) |
|---|---|---|
| §3 AI Integrity Principle | All five principles + safeguards | WO-04 (foundation); applied by every AI-touching WO (4, 18) |
| §4 Universal security | MFA, invite-only, encryption, file scanning, sessions, zero-trust, audit, T&C gate | WO-02, 03, 06, 07, 08, 09, 10, 11 |
| §4.2 API resilience | Retry, graceful degradation, payment failover (scaffold), admin health dashboard | WO-05, 13, 30 |
| §5 Roles | All 9 user types | WO-07 (matrix); WO-08 (invite/MFA); WO-15 (entrepreneur Phase 1 slice) |
| §6 Client management | Engagement type, NZBN auto-pop, data quality, conflict, comms prefs, wellbeing, testimonial, offboarding | WO-12, 14, 19, 20, 21, 22, 23 (testimonial deferred to Phase 3) |
| §7 Client portal | Design, T&C gate, onboarding wizard, dashboard, consents, scenario (deferred), offline (deferred), funnel (deferred) | WO-11, 16, 24 |
| §8 Questionnaire system | Standard set + builder | WO-17 |
| §9 Document verification | Three outcomes, application across features, audit, learning | WO-18; learning hooks live in WO-04 scaffolding |
| §10 Analysis modules | All | Phase 2 (deferred) — gate scaffolding in WO-19 |
| §11 Intelligence engine | Framework, feedback, bias, knowledge base, template library, benchmarking, outcome-based pricing, cross-client | WO-04, 27 (knowledge base manual); rest Phase 2/4 |
| §12 PV module | All | Phase 2 |
| §13 Fee/proposal/payment | All | Phase 2/3 (Stripe/Windcave scaffold lives in WO-13) |
| §14 Broker portal | All | Phase 3 |
| §15 Coach panel | All | Phase 3 |
| §16 DD feature | All | Phase 3 |
| §17 Entrepreneur module | All except basic profile/invite | WO-15 for profile/invite; rest Phase 3 |
| §18 T&C | All | WO-10, 11 |
| §19 Reporting | All | Phase 2 (PDF primitive in WO-11) |
| §20 Implementation tracking | All | Phase 3 |
| §21 Communication | In-app messaging, email-from-app, bulk (deferred), website integration, voice (deferred), calendar (deferred) | WO-25, 26, 29 |
| §22 Advisor dashboard | All Phase 1 panels | WO-28; rest Phase 2/3 |
| §23 Learning architecture | Scaffolding (tables, governance shape) | WO-04 tables; UI Phase 3 |
| §24 Economic intelligence | Feeds | Phase 2; integration scaffolds in WO-13 |
| §25 Integrations | All | WO-13 (scaffolds for everything); live wiring Phase 2+ |
| §26 Roadmap | Phase 1 row | This whole plan |
| §27 Tech stack | Stack | Section 3 (with owner-confirmed deviations) |
| §28 Appendices | Reference | `docs/legal/*`, `docs/rating-criteria/*` |

---

## Appendix B — Things the owner must provide before / during Phase 1

| Item | When needed | WO |
|---|---|---|
| Meridian Warm brand kit (colours, typography, logo files) | Before any client-facing UI is exposed (i.e. before WO-11 PR is merged for review) | WO-01 |
| Final 14-clause T&C text (NZ lawyer-reviewed) | Before WO-11 acceptance | WO-10, 11 |
| `Business_Plan_Rating_Matrix.pdf` | Phase 3 (place in `docs/rating-criteria/` during WO-01) | WO-01 (placement); Phase 3 (use) |
| FSA logo (vector + favicon) | WO-01 | WO-01 |
| Anthropic API key | Before live AI testing of WO-18 | WO-04 / WO-18 |
| Decision on production cloud (AWS Sydney recommended) | Before Phase 2 deployment | Phase 2 |
| Stripe + Windcave merchant accounts | Phase 3 | Phase 3 |
| ClamAV daemon host plan | Before production uploads | Phase 2/3 |
| NZBN/Companies Office/IRD developer access | Before first real client | WO-13 |

---

*End of Phase 1 plan. Phase 2 plan to be authored after Phase 1 acceptance.*
