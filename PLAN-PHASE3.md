# Future Shift Advisory — Phase 3 Implementation Plan (Engagement, Commerce, DD, Entrepreneur, Broker & Coach)

**Source spec:** `docs/spec/Future_Shift_Advisory_App_Specification_v2.4.docx` (definitive, May 2026)
**Plan scope:** Phase 3 (Months 6–9) in implementation detail. Phase 4 remains forward roadmap.
**Predecessors:** Phase 1 — Foundation (WO-01…WO-30) and Phase 2 — Intelligence (WO-31…WO-64) — **both complete and verified** on branch `featureApp` (see §3.0). See `PLAN.md`, `PLAN-PHASE2.md`, `IMPLEMENTATION.md`.
**Work orders:** WO-65 … WO-101 (continues the single global sequence).
**Plan version:** 1.0

> This document does **not** replace `PLAN.md` or `PLAN-PHASE2.md`. They stay as-is. This is the Phase 3 companion. Phase 3 is the **largest** phase — five major feature areas plus learning governance and platform polish.

---

## 0. How to use this plan

1. Read §1–§7 once before starting. They carry forward the non-negotiable principles and lock the new Phase 3 architecture (the panel-portal abstraction, the DD data room, the entrepreneur rating framework, the proposal sign-off + payment pipeline, the learning-queue approval flow).
2. Implement **Work Orders** in §8. Phase 3 has five largely-independent tracks (Commerce, Broker/Coach, DD, Entrepreneur, Learning/Polish) — see the dependency graph in §6. Within a track, follow numeric order.
3. **Commit cadence (locked — owner-confirmed):**
   - **Single branch.** All Phase 3 work happens on `featureApp`. **Do not create per-WO branches** and **do not open per-WO PRs.** (This supersedes the branch-per-WO / PR-per-WO language in `PLAN.md` §0, which applied only to the original Phase 1 model.)
   - **Commits are per WO — never per track and never one-per-phase.** Exactly **one commit per completed WO**, subject line `WO-<id>: <slug>`, committed directly on `featureApp`. This keeps the granular per-WO history (matching the 64 Phase 1+2 commits). "Commit after each phase" was never about squashing — it referred to the *verify + push* rhythm below.
   - **`IMPLEMENTATION.md` is updated in the same commit as the WO it documents** — so the status doc never drifts from the code (this is the fix for the Phase 1→2 contradiction).
   - **Verify + push cadence.** Phase 3 has five tracks (Commerce, Broker/Coach, DD, Entrepreneur, Learning/Polish) — treat each **track as a sub-phase**. Run the full verification (`composer test` + `npm run lint:check` + `npm run types:check` + `npm run format:check`) and **push `featureApp`** at **each track boundary**, and again at the **full Phase 3 boundary** (where the §3.0-style verified baseline is recorded in `IMPLEMENTATION.md`). Pushing more often (e.g. per WO) is fine; less often than per-track is not. So: **commit = per WO; verify + push = per track (minimum) and per phase.**
4. Every WO ships with its tests. A WO is done only when acceptance criteria are demonstrably true and `composer test`, `npm run lint:check`, `npm run types:check`, `npm run format:check` all pass.
5. **Do not invent features beyond the spec.** Phase 4 features stay out (see §2.2). Gaps → raise in the commit body or add a §12 risk row.
6. **Every analysis-bearing surface (DD workstreams, entrepreneur scoring) routes through the Phase 2 analysis spine** (`AnalysisRunner`) so AI integrity, document verification, and data-quality gating are enforced uniformly — never re-implemented.
7. **Every money-handling surface goes through the resilience layer** (`ResilientHttp`) and is fully audited. Payment is the highest-risk Phase 3 area — see §7.4 and the §12 risks.
8. **Regenerate Wayfinder output (`php artisan wayfinder:generate --with-form`) and commit it whenever a WO adds/changes a controller route** — the `--with-form` flag is required (the bare command drops the `.form` variants the pages depend on). Stale Wayfinder output was a recurring defect in Phase 1/2; do not repeat it.

---

## 1. Non-negotiable principles (carried forward — still binding)

Everything in `CLAUDE.md`, `PLAN.md` §1, and `PLAN-PHASE2.md` §1 remains in force.

- **AI Integrity Principle (spec §3) — heightened for the Entrepreneur Module (spec §3.3, §17).** Entrepreneurs are often making life-defining decisions with no prior business experience. Inflating readiness or plan quality misleads a vulnerable person and damages FSA. The predictive score and assessment must be honest, evidence-based, never flattering. Honest assessment is always paired with genuine encouragement to improve — never one without the other.
- **Document AI Verification (spec §9).** Applies to DD data-room uploads (double-weighted — direct financial consequences for the buyer), entrepreneur plan-section attachments (document support affects criterion score; a claim contradicted by the entrepreneur's own document is an accuracy discrepancy that blocks scoring until resolved), proof-of-completion uploads, broker/coach referral rationale docs. The existing `DocumentVerificationGate` blocks on **any** outstanding `advisory_flag` or `accuracy_discrepancy`.
- **Liability disclaimer (spec §16 intro).** **Every DD output** carries the standard disclaimer: FSA's DD support is advisory only, not legal/tax/accounting/investment advice; a qualified NZ lawyer and accountant must be engaged before any acquisition; FSA accepts no liability for acquisition decisions made in reliance on platform DD outputs. Use the existing disclaimer registry primitive.
- **Security baseline (spec §4).** Payment gateways (Stripe/Windcave), FSP register, PPSR, LINZ, IPONZ, calendar, Whisper — all through `ResilientHttp`. DD guest upload is tokenised (no account), virus-scanned, audit-logged, instantly revocable. All AI through `AiClient`; all audit through `AuditWriter`; all encryption (payment tokens, signature evidence) through `KeyEnvelope`. PCI-DSS: never store raw PANs — gateway tokens only. PQC/HSM remain Phase 4.
- **No silent learning (spec §23).** Phase 3 builds the learning-queue **approval UI** and rollback. Updates still only change behaviour through owner approval. The entrepreneur rating framework evolves only through the governed queue — never hardcoded, never self-modifying.
- **Electronic signatures (spec Appendix B; Contract and Commercial Law Act 2017).** Proposal sign-off, panel agreements, and DD engagement acceptance use the existing signed-PDF + `KeyEnvelope` evidence primitive (from WO-11).

---

## 2. Phase 3 goal & scope

### 2.1 Goal (Months 6–9)

Make the platform transactional and multi-sided: clients sign proposals and pay; advisors track goals/milestones with PV and proof-of-completion; the practice runs full Due Diligence engagements end-to-end (8 workstreams, data room, valuation, report, post-acquisition conversion); entrepreneurs go from readiness assessment through an AI-guided 5-phase plan to a scored assessment and advisory conversion; vetted insurance brokers and life/personal coaches operate their own portals with referrals in both directions; and the 32-layer learning system gets its governed admin approval UI with rollback.

### 2.2 Out of scope (Phase 4 — do not build)

- Full continuous-learning engine with all 32 layers *actively* self-tuning; bias detection automated calibration; DD finding-pattern + valuation-accuracy learning; industry-specific plan-quality benchmarks; conversion-outcome learning; rating predictive-validity testing. (Phase 3 sets up cadence + governed candidates + approval UI; Phase 4 turns on the heavier learning behaviours.)
- Cross-client competitive intelligence; shared intelligence layer; anonymous NZ SME benchmarking *community*; anonymous peer network. (Phase 3 entrepreneur "anonymous plan benchmarking" is aggregate-only against prior plans — not the Phase 4 community/peer network.)
- Voice assistant (Siri/Google Assistant); mobile app; advisor API layer; multi-advisor scaling/team management; NZ business-tool integrations (Employment Hero, Cin7, Tradify); industry WACC full automation; annual third-party security/legal audit framework; PQC + HSM.

> **DD valuation** reuses the Phase 2 `PvEngine` / `BusinessValuation` / `ValuationMultipleProvider` — do not rebuild. **DD + Entrepreneur-Assessment reports** fill the already-scaffolded `ReportType::DUE_DILIGENCE` and `ReportType::ENTREPRENEUR_ASSESSMENT` cases via the existing `ReportComposer`. **Entrepreneur capacity** extends the Phase 1 `AdvisorEntrepreneurCapacity`. **Coaching referral** consumes the Phase 2 `coaching_signals` rows.

---

## 3.0 Phase 2 verified baseline

Phase 2 was reviewed and confirmed complete before Phase 3 began. Starting baseline; do not regress:

| Gate | Result at handoff |
|---|---|
| PHPUnit suite (against PostgreSQL `futureshift_test`) | **293 tests / 2190 assertions — all pass** |
| Pint · ESLint · `tsc --noEmit` · Prettier | all pass |
| WO commits on `featureApp` | **64 / 64** (WO-01 … WO-64) |
| Forbidden markers (`TODO`/`FIXME` in `app/`+`routes/`, `dd()`/`dump()`, `console.log`) | **0** |

Inventory at handoff: 52 migrations · 60 models · 81 feature tests · 9 unit-test files · 55 architecture docs · `app/Services/{Analysis,Pv,Reports,Fees,Proposals,Accounting,Analytics,Compliance,EconomicData,Pptx,Messaging,…}`.

> **Running the suite locally.** As in Phase 2: on a standalone PostgreSQL install you must point the test connection at real credentials via the process environment (`DB_HOST/DB_PORT/DB_DATABASE=futureshift_test/DB_USERNAME/DB_PASSWORD`) before `php artisan test`, because `.env.testing` ships Herd defaults. The test DB is separate from dev (`RefreshDatabase` wipes it). Never commit local DB credentials.

**Carryover owner inputs that now hard-block Phase 3 surfaces:** Anthropic API key (DD/entrepreneur AI), Stripe + Windcave merchant accounts (commerce — onboarding takes weeks, start now), FSP Register access (broker validation), PPSR/LINZ/IPONZ access (DD legal workstream), ~~`Business_Plan_Rating_Matrix.pdf`~~ **(supplied; P3-R3 + P3-R3a closed — framework follows the spec's 11 criteria; founding weights/descriptors are owner-set in WO-87b before go-live)**, lawyer-reviewed T&C + panel agreement + DD disclaimer text, Whisper credentials (voice-to-text). Tracked in §12.

---

## 3. What Phase 3 builds on (Phase 1 + 2 inventory)

Reuse these directly — do not re-create.

| Asset | Location | Phase 3 use |
|---|---|---|
| `AnalysisRunner` + `AnalyticalFramework` + `AnalysisModule` contract | `app/Services/Analysis` | DD 8-workstream analysis; entrepreneur idea/plan analysis run on the spine |
| `AnalysisRun` / `AnalysisFinding` / `RedFlagPromoter` | models + `app/Services/Analysis` | DD findings + risk register; entrepreneur viability alerts |
| `PvEngine` / `DiscountRateResolver` / `BusinessValuation` / `ValuationMultipleProvider` | `app/Services/Pv` | DD valuation (3 methods + PV); entrepreneur concept PV; goal/milestone PV |
| `ImprovementPv` / `RiskCostPv` | `app/Services/Pv` | DD price-adjustment + risk register PV |
| `ReportComposer` + `ReportType` enum (DD + entrepreneur cases scaffolded) | `app/Services/Reports`, `app/Enums/ReportType.php` | DD report; entrepreneur assessment report |
| `Proposal` + `ProposalStatus` (reserved `awaiting_signature`/`signed`) | model + enum | Sign-off flow makes those states reachable (WO-66) |
| `Consent` model | `app/Models/Consent.php` | Insurance/coach consent election in sign-off; revocable from portal |
| `CoachingSignal` model | `app/Models/CoachingSignal.php` | Coach referral signal detection (WO-73) consumes raw rows |
| `DocumentVerificationGate` + pipeline (`Document` category has `dd_artifact`, `plan_attachment`) | `app/Services/Documents`, `app/Services/Ai/Verification` | DD data-room + entrepreneur attachment verification; proof-of-completion |
| `SecureFileWriter` + `FileScanner` + `secure_local` disk | `app/Services/Storage`, `app/Services/Integration/VirusScanner` | DD guest upload (virus-scanned, encrypted) |
| `QuestionnaireSet` (dd_specific, post_acquisition_gap, entrepreneur_readiness, entrepreneur_idea_validation scaffolded) + engine | enum + `app/Services/Questionnaires` | DD questionnaire; post-acquisition gap; entrepreneur readiness/idea |
| Signed-PDF + `KeyEnvelope` evidence (WO-11) | `app/Services/Pdf`, `app/Services/Storage` | Proposal signature, panel agreements, DD acceptance |
| `InviteIssuer` + invite-only + MFA + T&C gate | `app/Services/Security`, middleware | Broker/coach invite + onboarding; DD guest is token-only (no account) |
| `AdvisorEntrepreneurCapacity` | `app/Services/Entrepreneurs` | Entrepreneur capacity (30 active, warn 24, hard 30) |
| `learning_updates` / `learning_update_implementations` / `LearningLayerRun` | models | Learning queue admin UI + rollback (WO-93/94/95) |
| `ResilientHttp` + integration scaffolds: Stripe, Windcave, Fsp, Ppsr, Linz, Iponz, GoogleCalendar, MicrosoftGraph, Whisper | `app/Services/Integration` | Payment, FSP validation, DD legal, calendar, voice-to-text — fill the contracts |
| `Meeting` + `PreMeetingBrief` | models | Meeting/phone-call logs (WO-98); calendar sync fills `external_ref` |
| `OpenXmlPptxGenerator` / `PptxGenerator` | `app/Services/Pptx` | DD/entrepreneur report PowerPoint export |
| `ChannelResolver` + notifications | `app/Services/Notifications` | Referral updates, payment alerts, learning notices, testimonial requests |
| `LifecycleManager` (clients) | `app/Services/Clients` | Post-acquisition profile creation; DD → advisory conversion |
| RLS harness (`EnforceClientScope`, `fsa_*`, `FORCE ROW LEVEL SECURITY`) | WO-02 | Every new client-scoped table → policy + RLS test |

---

## 4. New database schema (Phase 3)

Same conventions: `uuid` PKs via `gen_random_uuid()`, `jsonb`, audit via `AuditWriter`. **RLS:** client-scoped tables use the `client_id` policy pattern (per `docs/architecture/postgres-rls.md`); **entrepreneur-scoped tables use a different policy keyed on `entrepreneur_profiles` ownership — see the RLS note in §4.5** (and the `entrepreneur_profiles` RLS retrofit it calls for). XOR ownership columns (e.g. `referrals`, `business_plans`) get DB `CHECK` constraints — see those tables.

### 4.1 Goals & milestones (WO-65)
- `goals` — `client_id`, `title`, `description`, `pv_target_calculation_id` (→ `pv_calculations`), `pv_target`, `status` (active, achieved, abandoned), `created_by_user_id`
- `milestones` — `goal_id`, `client_id`, `title`, `recommendation_ref`, `pv_of_impact_calculation_id` (→ `pv_calculations`), `pv_of_impact`, `due_date`, `status` (pending, in_progress, completed, blocked), `completed_at`
- `milestone_actions` — `milestone_id`, `client_id`, `title`, `owner_user_id`, `due_date`, `priority`, `status`
- `proof_of_completion` — `milestone_id`, `client_id`, `document_id`, `document_verification_id`, `status` (pending, verified, flagged), `reviewed_at` — uses the existing verification pipeline (AI reviews the evidence for relevance to the milestone)

### 4.2 Commerce: sign-off & payment (WO-66/67/68/69)
- `proposals` **extended** (WO-66) — `signed_at`, `signature_evidence_path`, `signature_envelope_meta`, `signed_by_user_id`, `awaiting_signature_at`; the reserved `ProposalStatus::AWAITING_SIGNATURE` / `SIGNED` become reachable, and WO-66 **removes/replaces the Phase 2 `phaseTwoReserved()` guard** that currently throws on them.
- `proposal_signoff_steps` (WO-66) — `proposal_id`, `step`, `completed_at`, `payload` (jsonb). The **7 steps** (resolves spec §13's enumerated list against §26's "7-step" headline by treating the two consents as distinct, which they are — broker vs coach): `review`, `insurance_consent`, `coach_consent`, `payment_method`, `authority`, `signature`, `confirmation`. The state machine is exactly these seven, in order.
- `payment_authorities` (**WO-66** — authority capture lives with the sign-off flow, against the gateway *contract*) — `client_id`, `proposal_id`, `type` (card, direct_debit), `gateway` (stripe, windcave), `gateway_customer_ref`, `gateway_token_envelope` (KeyEnvelope; **no raw PAN**), `status` (active, failed, revoked), `authorised_by_user_id`, `authorised_at`
- `payment_schedules` (WO-67) — `client_id`, `proposal_id`, `payment_authority_id`, `cadence` (one_off, monthly_retainer), `amount`, `currency` (NZD), `next_run_at`, `status`
- `payments` — `client_id`, `payment_schedule_id`, `amount`, `currency`, `gateway`, `gateway_ref`, `status` (pending, succeeded, failed, retrying), `attempt`, `failover_from` (nullable — records Stripe→Windcave failover), `failed_reason`, `processed_at`
- `receipts` — `payment_id`, `client_id`, `number`, `pdf_path`, `issued_at`

### 4.3 Broker & Coach panels (WO-70/71/72/73/74)
- `panel_members` — `type` (broker, coach), `user_id` (nullable until invite accepted), `invite_token_id`, `status` (invited, pending_approval, approved, suspended), `application` (jsonb — configurable fields), `profile` (jsonb), `specialisations` (jsonb — coach: life, business_executive, mental_health_wellbeing, financial_wellness, career), `fsp_number` (broker), `fsp_status` (broker — current, lapsed), `fsp_last_checked_at`, `approved_by_user_id`
- `panel_agreements` — `panel_member_id`, `type`, `version`, `clauses_snapshot` (jsonb), `signed_at`, `signed_pdf_path`, `signed_pdf_envelope_meta`, `re_sign_due_at` (material change → 14 days)
- `referrals` — `type` (broker, coach), `client_id` (nullable), `entrepreneur_profile_id` (nullable), `panel_member_id`, `specialisation` (coach), `stage` (enum per spec: broker = referral_sent…cover_placed/declined/no_response; coach = referral_sent…coaching_underway/concluded/declined), `rationale`, `rationale_document_id` (verified), `conflict_declaration_id`, `consent_id` (→ `consents`), `created_by_user_id`. **Subject is exactly one of client/entrepreneur — enforce a DB `CHECK ((client_id IS NOT NULL) <> (entrepreneur_profile_id IS NOT NULL))`** (XOR; neither-nor-both is rejected). A test asserts both the both-set and neither-set inserts fail.
- `referral_messages` — `referral_id`, `sender_user_id`, `body`, `sent_at` — per-referral thread, stored against both profiles
- `reverse_referrals` — `panel_member_id`, `type`, `payload` (jsonb), `created_client_id` (nullable), `created_entrepreneur_profile_id` (nullable), `status` (received, converted, declined). The two `created_*` columns are both nullable until conversion, then **at most one** is set — `CHECK (NOT (created_client_id IS NOT NULL AND created_entrepreneur_profile_id IS NOT NULL))`.

### 4.4 Due Diligence (WO-75/76/77/78/79/80/81)
- `dd_engagements` — `client_id` (buyer), `target_name`, `target_details` (jsonb), `status` (in_progress, acquisition_proceeding, abandoned), `recommendation` (proceed, renegotiate, abandon — nullable until report), `conflict_declaration_id`, `created_by_user_id`
- `dd_workstreams` — `dd_engagement_id`, `number` (1–8), `name`, `status` (not_started, in_progress, complete), `analysis_run_id` (nullable → spine), `completed_at`
- `dd_data_room_items` — `dd_engagement_id`, `dd_workstream_id`, `document_id`, `uploaded_by_user_id` (nullable), `uploaded_via_guest_link_id` (nullable)
- `dd_guest_links` — `dd_engagement_id`, `dd_workstream_id`, `token_hash`, `expires_at`, `revoked_at`, `created_by_user_id` — **upload-only, no account, no viewing**; uploads virus-scanned + audit-logged
- `dd_valuations` — `dd_engagement_id`, `pv_calculation_id`, `business_valuation_id` (→ Phase 2 `business_valuations`), `fx_normalisation` (jsonb — RBNZ rate, timestamp, ±10% sensitivity), `buyer_negotiating_position` (jsonb)
- `dd_risk_register` — `dd_engagement_id`, `analysis_finding_id` (nullable), `severity` (deal_killer, major, minor, informational), `item`, `pv_of_cost_calculation_id`, `price_adjustment` (numeric)
- `dd_integration_plans` — `dd_engagement_id`, `hundred_day_plan` (jsonb), `buyer_readiness` (jsonb)
- `post_acquisition_migrations` — `dd_engagement_id`, `new_client_id`, `gap_questionnaire_response_id`, `auto_proposal_id`, `migrated_at` — DD docs migrated with "Sourced from DD" label

### 4.5 Entrepreneur module (WO-82…92)

> **RLS for entrepreneur-scoped tables (read this before creating any table below).** Entrepreneur tables are **not** `client_id`-scoped — they hang off `entrepreneur_profiles`. So the standard client RLS pattern does **not** apply. Instead:
> - **First, retrofit `entrepreneur_profiles` with an RLS policy** (it was created in Phase 1 WO-15 *without* one — a known gap; fixing it is part of WO-82). Visibility: `fsa_current_role() = 'super_admin'` **OR** `assigned_advisor_id = fsa_current_user_id()` **OR** `user_id = fsa_current_user_id()` (the entrepreneur themselves). This needs an `fsa_current_user_id()` helper alongside the existing `fsa_current_role()`/`fsa_current_client_ids()` (the request context already sets `fsa.user_id` per Phase 1 WO-14 — expose it as a SQL helper).
> - **Every child table** (`readiness_assessments`, `idea_validations`, `business_plans`, `plan_*`, `plan_assessments`, `plan_revisions`, `advisory_readiness_signals`) gets an RLS policy that joins to its `entrepreneur_profiles` row and applies the same advisor/entrepreneur/super-admin visibility. Each ships an RLS test proving an unassigned advisor and a different entrepreneur cannot read the rows.
> - Admin-managed reference tables (`rating_frameworks`, `rating_criteria`, `nz_resources`) are **not** entrepreneur-scoped — they are global/admin and need no per-row RLS (authorisation via policy/permission instead).

- `readiness_assessments` — `entrepreneur_profile_id`, `responses` (jsonb), `outcome` (ready, develop_first, not_yet), `personal_barriers` (jsonb), `assessed_at`
- `idea_validations` — `entrepreneur_profile_id`, `problem`, `target_customer`, `solution`, `value_proposition`, `demand_signal`, `revenue_model`, `viability_alerts` (jsonb), `advisor_gate_passed_at` (nullable — advisor gate before plan builder opens)
- `business_plans` — `entrepreneur_profile_id` (nullable), `dd_engagement_id` (nullable — DD-built plan), `status` (building, submitted, assessing, revising, finalised, launched), `current_phase` (1–5), `created_at`. **Owner is exactly one of entrepreneur/DD — enforce `CHECK ((entrepreneur_profile_id IS NOT NULL) <> (dd_engagement_id IS NOT NULL))`** (XOR; neither-nor-both rejected). RLS: an entrepreneur-owned plan scopes via `entrepreneur_profiles` (above); a DD-owned plan scopes via the buyer `client_id` on its `dd_engagement` (DD tables are client-scoped). A test asserts both-set and neither-set inserts fail, and that each owner type is correctly isolated.
- `plan_phases` / `plan_sections` — `business_plan_id`, `phase` (1–5: foundation, market, strategy, legal_operations, financial), `section`, `body`, `attached_document_ids` (jsonb), `predictive_score` (jsonb — live running estimate, no flattery)
- `rating_frameworks` — `version`, `status` (draft, published), `industry_variant` (nullable), `production_ready` (bool — false until WO-87b sets founding weights/descriptors), `published_at`, `published_by_user_id` — **admin-managed, learning-evolved; never hardcoded**
- `rating_criteria` — `rating_framework_id`, `number` (1–11), `name`, `weight`, `descriptors` (jsonb — per band), `is_placeholder` (bool) — the **11 founding criteria** (spec §17.6 / Appendix C: Type of business; Location; Means of doing business; Discuss the industry; What sets the business apart; Describe unique success factors; Mission and Vision statement; Intellectual property; Goals and objectives; Culture; Legal Environment) seed in WO-87a; weights/descriptors are admin-managed (placeholder until WO-87b). Owner decision (P3-R3a, 2026-05-23): framework follows the spec's 11-item list; the rating-matrix PDF was its starting point and is retained as historical reference only.
- `plan_assessments` — `business_plan_id`, `round`, `rating_framework_id`, `ai_scores` (jsonb — first-pass per criterion), `advisor_scores` (jsonb — adjusted, each with mandatory note), `mentor_notes` (jsonb — per-section visible, overall visible, **private advisory note advisor-only**), `document_support` (jsonb — affects criterion score), `overall_grade` (exceptional, strong, developing, needs_work), `concept_pv_calculation_id`, `finalised_at`, `finalised_by_user_id`
- `plan_revisions` — `business_plan_id`, `round`, `submitted_at`, `progress_comparison` (jsonb — per-criterion deltas, trajectory %)
- `nz_resources` — `industry`, `business_type`, `title`, `url`, `gap_tags` (jsonb) — admin-managed resource layer; AI recommends by industry/type/gap
- `advisory_readiness_signals` — `entrepreneur_profile_id`, `score`, `surfaced_at`, `advisor_notified_at` — systematic signal when an entrepreneur nears advisory readiness

### 4.6 Learning governance (WO-93/94/95)
- `learning_updates` **extended** — `pre_implementation_notice_at` (7-day), `review_due_at` (30-day post), decision fields already present from Phase 1.
- `learning_update_decisions` — `learning_update_id`, `decision` (approve, approve_modified_date, defer, reject), `effective_date_override`, `decided_by_user_id`, `decided_at`, `note`
- `learning_rollbacks` — `learning_update_id`, `learning_update_implementation_id`, `reason`, `rolled_back_by_user_id`, `rolled_back_at`, `restored_state` (jsonb)

### 4.7 Platform polish (WO-97…101)
- `testimonials` — `client_id`, `nps_score`, `content`, `consent` (jsonb — use, named/anonymous), `status` (requested, captured, approved), `captured_at`
- `voice_notes` — `client_id`, `audio_document_id`, `transcript`, `summary` (jsonb — key points/decisions/actions), `created_by_user_id`
- `call_logs` — `client_id`, `type` (meeting, phone), `occurred_at`, `duration_minutes`, `participants` (jsonb), `summary`, `action_items` (jsonb — linked to milestones)
- `bulk_communications` — `template`, `audience` (jsonb), `scheduled_at`, `sent_at`, `open_stats` (jsonb), `created_by_user_id`
- Document expiry uses the existing `documents.expires_at` (add a reminder scheduler; optional `document_expiry_reminders` ledger for idempotency).
- Offline PWA — no new tables; service worker + encrypted local store + sync queue on the frontend.

---

## 5. Configuration & feature flags (Phase 3 additions)

```
# Commerce (WO-68/69)
FEATURE_STRIPE_LIVE=false
FEATURE_WINDCAVE_LIVE=false
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
WINDCAVE_API_USER=
WINDCAVE_API_KEY=
PAYMENT_PRIMARY_GATEWAY=stripe          # failover to windcave on persistent failure

# DD / legal integrations (WO-77)
FEATURE_FSP_LIVE=false
FEATURE_PPSR_LIVE=false
FEATURE_LINZ_LIVE=false
FEATURE_IPONZ_LIVE=false

# Calendar + voice (WO-98)
FEATURE_GOOGLE_CALENDAR_LIVE=false
FEATURE_MICROSOFT_GRAPH_LIVE=false
FEATURE_WHISPER_LIVE=false

# Proposal sign-off / panels
PROPOSAL_DEFAULT_EXPIRY_DAYS=30
PANEL_AGREEMENT_RESIGN_DAYS=14

# Entrepreneur
ENTREPRENEUR_ADVISOR_CAPACITY=30        # warn at 24, hard block at 30
BENCHMARK_MIN_COHORT=5                   # suppress aggregate plan benchmarks below this cohort size (privacy)
```

All payment/integration flags default off; without credentials the resilience layer degrades gracefully and tests run on fixtures. **Sign-off and payment must never silently fabricate a paid or signed state.** Two distinct failure cases (see §7.4 for the full rule):
- **Authority-capture failure** (the gateway cannot tokenise the payment method at the `authority` step): the proposal does **not** advance to `awaiting_signature` — it stays at the `payment_method`/`authority` step and notifies. No usable token ⇒ no `awaiting_signature`, no `signed`.
- **Charge failure** (a later scheduled charge in WO-69 fails): the proposal **stays `signed`** (signature already captured); a failed-payment alert + retry + failover fire. A charge failure never reverts `signed` and a gateway outage never fabricates a paid state.

---

## 6. Cross-cutting dependency map

```
Commerce track:
  WO-65 Goals/milestones (PV + proof-of-completion)
  WO-66 Sign-off (7-step) + authority capture (via gateway contract) ─> WO-67 Payment schedules ─> WO-68 Stripe+Windcave live ─> WO-69 Payment processing
        (WO-66 owns authority capture against the gateway *contract*; WO-68 fills the *live* gateway. No circular dep.)
        (WO-66 removes the Phase 2 phaseTwoReserved() guard and makes awaiting_signature/signed reachable only via the sign-off flow.)

Broker/Coach track:
  WO-70 Panel-portal foundation ─┬─> WO-71 Broker portal (FSP)
                                 └─> WO-72 Coach portal (5 specialisations)
  WO-73 Coaching referral signal detection (consumes coaching_signals)
  WO-74 Referral conflict + consent wiring

DD track:
  WO-75 DD onboarding + questionnaire ─> WO-76 Data room + guest upload ─> WO-77 8 workstreams (spine)
  WO-77 ─> WO-78 DD valuation (reuse PvEngine) ─> WO-80 DD report
  WO-77 + WO-84(!) ─> WO-79 DD business plan builder   [!] blocked until WO-84 (shared plan-builder engine)
  WO-80 ─> WO-81 Post-acquisition pipeline (migration + gap questionnaire + auto-proposal)

  ⚠️ CROSS-TRACK: WO-79 (DD) reuses the plan-builder engine built in WO-84 (Entrepreneur).
     Implement WO-84 before WO-79. If the DD track must run ahead of the Entrepreneur
     track, lift the plan-builder engine into a shared earlier WO first.

Entrepreneur track:
  WO-82 Readiness ─> WO-83 Idea validation (advisor gate) ─> WO-84 5-phase builder (shared engine, also used by WO-79)
  WO-85 AI-guided building + predictive score ; WO-86 section attachments + verification
  WO-87a Rating framework engine — seeds spec's 11 criteria (build now) ─> WO-88 AI first-pass + advisor assessment
  WO-87b Owner sets founding weights/descriptors via admin editor [gates entrepreneur GO-LIVE, not WO-88 dev]
  WO-88 ─> WO-89 Assessment report (4-part + concept PV)
  WO-90 Resubmission/round progress ; WO-91 benchmarking + readiness + living plan
  WO-92 Advisory conversion + DD plan integration

Learning + polish track (parallel):
  WO-93 Learning queue admin UI ─> WO-94 Rollback ─> WO-95 32-layer cadence dashboard
  WO-96 T&C version-manager UI polish
  WO-97 Testimonials ; WO-98 Voice-to-text + call logs ; WO-99 Bulk comms ;
  WO-100 Document expiry ; WO-101 Offline PWA
```

---

## 7. Cross-cutting foundations (build early)

### 7.1 Panel-portal abstraction (WO-70)
Spec §14/§15 explicitly state the Coach Panel uses the **same structural model** as the Broker Panel. Build one shared foundation: invite (invite-only, admin-issued) → configurable application form → pending-approval queue → panel agreement generation + digital signing + T&C gate → referral lifecycle (with per-referral messaging) → reverse referrals → mutual-referral commercial terms (no referral fees either direction). Broker and Coach are thin specialisations: broker adds FSP-register validation + periodic re-verification + lapse→auto-suspend; coach adds 5 fixed specialisations, admin-managed vetting (no mandatory register), and the wellbeing scope-boundary clause. A new external-portal layout mirrors the client/advisor portal shells with its own permissions.

### 7.2 DD data room + guest upload (WO-76)
Workstream-organised storage separate from the standard filing cabinet. Guest upload is a **tokenised link, not an account**: upload-only, cannot view anything, virus-scanned through `SecureFileWriter`, audit-logged, instantly revocable. Reuse the document pipeline; tag items with `Document` category `dd_artifact` and the workstream. Double-weight document verification for DD (spec §9 — direct buyer financial consequences).

### 7.3 Entrepreneur rating framework (WO-87a engine / WO-87b founding values) — admin-managed, learning-driven
The framework follows the **11 founding criteria** in spec §17.6 / Appendix C (owner decision P3-R3a, 2026-05-23: the rating-matrix PDF was the *starting point* for developing the spec; the spec is the refined authoritative version). The PDF (`docs/rating-criteria/Business_Plan_Rating_Matrix.pdf` + `founding-rating-matrix.md`) is retained as **historical reference** the advisor may draw on when setting admin-managed weights/descriptors. **WO-87a** builds the engine + admin editor and seeds the 11 criterion names (placeholder weights/descriptors); **WO-87b** is the owner setting the founding weights/descriptors/variants via the admin editor and flipping the framework to production-ready. **Everything is admin-managed** — criteria, weights, scoring descriptors, industry variants — editable from admin settings with no developer involvement, versioned in `rating_frameworks`/`rating_criteria`. The framework evolves only through the governed learning queue along three dimensions (criterion weighting evolution, scoring descriptor calibration, industry-specific variants). Criteria are **hidden during building**, revealed in the assessment-report appendix after finalisation (prevents gaming, enables informed revision). Heightened AI Integrity: the live predictive score reflects real quality with no inflation.

### 7.4 Proposal sign-off + payment pipeline (WO-66…69)
The **7-step** sign-off — `review` → `insurance_consent` → `coach_consent` → `payment_method` → `authority` → `signature` → `confirmation` — drives `proposal_signoff_steps` and flips the proposal through the reserved `awaiting_signature` → `signed` states.

**Exact status rule (resolves the awaiting_signature/signed ambiguity):**
- The proposal enters **`awaiting_signature`** once steps 1–5 (`review` … `authority`) are complete — i.e. consents elected and a **tokenised payment authority is captured** (the gateway must return a usable token; a gateway failure capturing the authority keeps the proposal pre-signature and raises an alert — it never advances).
- The proposal becomes **`signed`** when the **`signature` step is captured** (digital signature + `KeyEnvelope` evidence), provided a valid tokenised authority is on file. **`signed` requires signature + tokenised authority — it does NOT require a successful charge.**
- **Charging is asynchronous and separate (WO-69).** The first and recurring charges run off `payment_schedules`. A failed charge raises a failed-payment alert + retry + Stripe→Windcave failover; **a failed charge never reverts `signed`** and a gateway outage never fabricates a paid/signed state.

Payment uses Stripe (primary) + Windcave (automatic failover) via `ResilientHttp`; **PCI-DSS: gateway tokens only, never raw card data, tokens in `KeyEnvelope`.** Every state transition is audited.

### 7.5 Learning-queue approval UI + rollback (WO-93/94)
Surface the existing `learning_updates` candidates as Update Summary Cards (update id, type, source, what changes, impact scope, clients affected, magnitude, confidence, evidence, effective date, before/after preview). Admin decisions: Approve / Approve-with-modified-date / Defer / Reject. Approved updates get a 7-day pre-implementation notice, then implementation, then a 30-day post-implementation review, with rollback restoring prior state. Still **no silent self-modification** — the engine only acts on owner-approved updates.

### 7.6 Heightened AI Integrity for the Entrepreneur Module (spec §3.3)
Every entrepreneur-facing AI output (predictive score, gap detection, industry risk awareness, assessment feedback) is honest and evidence-cited ("Based on [X] plans in this industry assessed on this platform"), never flattering, always paired with genuine improvement guidance. A claim contradicted by the entrepreneur's own uploaded document is an accuracy discrepancy that blocks scoring until resolved. This is the most critical application of the principle.

---

## 8. Work Orders (Phase 3)

> Format: **ID** · **Title** · spec refs · goal · depends-on · key files · acceptance · tests · out-of-scope.

### Commerce track

#### WO-65 — Goals & milestones tracker (PV-linked, proof-of-completion)
**Spec refs:** §20; §12.1
**Goal:** Goals with PV targets; milestones linked to recommendations + PV of impact; actions with owner/due/priority; running PV-realised total on dashboard + progress reports; proof-of-completion uploads reviewed by AI for relevance to the milestone (via `DocumentVerificationGate`).
**Depends on:** Phase 2 WO-43 (PV), WO-18 (verification), WO-16 (portal).
**Key files:** `goals`/`milestones`/`milestone_actions`/`proof_of_completion` tables + models, `app/Services/Goals/*`, advisor + portal UI, PV-realised widget.
**Acceptance:** milestone links to a PV-of-impact calc; proof upload runs verification and blocks "completed" on an accuracy discrepancy; PV-realised total reconciles with completed milestones; client dashboard shows progress.
**Tests:** PV linkage; proof verification gate; PV-realised total; RLS on all four tables.
**Out of scope:** none.

#### WO-66 — Digital proposal sign-off flow (7-step)
**Spec refs:** §13 (Sign-Off Flow)
**Goal:** The 7-step flow (`review`, `insurance_consent`, `coach_consent`, `payment_method`, `authority`, `signature`, `confirmation`); insurance + coach consent elections (writes `consents`); tokenised payment-authority capture at the `authority` step; digital signature (signed-PDF + `KeyEnvelope` evidence). Status rule per §7.4: `awaiting_signature` after steps 1–5 (incl. a valid tokenised authority), `signed` on signature capture — **`signed` does not require a successful charge** (charging is WO-69).
**Depends on:** Phase 2 WO-56 (proposal), Phase 1 WO-11 (signed PDF). **Authority capture lives in this WO** (not WO-67) — it calls the **payment-gateway contract** (the Stripe/Windcave interfaces scaffolded in Phase 1); WO-68 fills the *live* gateway later. Tests use the fixture gateway, so WO-66 is fully testable without live credentials and there is **no dependency on WO-67/68**.
**Key files:** `proposal_signoff_steps` + **`payment_authorities`** tables, extend `proposals`, `app/Services/Proposals/SignoffFlow.php`, **`app/Services/Payments/AuthorityCapture.php`** (via the gateway contract), portal sign-off UI. **Remove/replace the Phase 2 `Proposal::booted()` `phaseTwoReserved()` guard** (and the `ProposalStatus::phaseTwoReserved()` helper) that currently throws on `awaiting_signature`/`signed`; replace it with a forward-only transition guard that permits the sign-off transitions while still rejecting illegal jumps. Keep the Phase 2 reserved-state test, inverted to assert the states are now reachable **only** via the sign-off flow.
**Acceptance:** all 7 steps enforced in order server-side; both consent elections stored + revocable from portal settings; the `authority` step captures a tokenised authority via the gateway contract (no raw PAN; token in `KeyEnvelope`); `awaiting_signature` reached only once a tokenised authority exists; `signed` reached only after the `signature` step; signed PDF with evidence; the old reserved-state guard is gone and replaced; every transition audited.
**Tests:** 7-step ordering; both consents capture/revoke; **authority-capture failure (fixture gateway error) keeps the proposal pre-`awaiting_signature`**; `awaiting_signature` blocked without a tokenised authority; `signed` only after signature; **no paid/signed state without the signature step**; replaced-guard test (reserved states reachable only via sign-off).
**Out of scope:** recurring schedules (WO-67); live gateway + webhooks (WO-68); charges/receipts (WO-69).

#### WO-67 — Payment schedules
**Spec refs:** §13 (Payment Method, Authority)
**Goal:** Build `payment_schedules` (one-off + monthly retainer) from the tokenised authority captured in WO-66.
**Depends on:** WO-66 (authority capture).
**Key files:** `payment_schedules` table/model, `app/Services/Payments/ScheduleBuilder.php`.
**Acceptance:** a schedule is created from a signed proposal's authority; cadence (one-off / monthly) honoured; revoke cascades from authority revoke; audited.
**Tests:** schedule creation from authority; cadence; revoke cascade.
**Out of scope:** authority capture (WO-66); actual charges (WO-68/69).

#### WO-68 — Stripe + Windcave live integration (resilience failover)
**Spec refs:** §13; §4.2; §25
**Goal:** Fill the **live** Stripe + Windcave clients behind the gateway contract that WO-66/67 already use, via `ResilientHttp`; primary Stripe, automatic Windcave failover on persistent failure; webhook handling.
**Depends on:** WO-67 (and the gateway contract from WO-66).
**Key files:** live clients in `app/Services/Integration/{Stripe,Windcave}`, webhook controllers, `app/Services/Payments/Gateway.php` (binds live clients behind the contract).
**Acceptance:** a charge via fixture gateway still succeeds (contract unchanged); simulated primary failure fails over to secondary and records `failover_from`; both-gateway failure logged + notified; PCI-DSS: no raw card data persisted anywhere.
**Tests:** charge success; failover path; double-failure handling; no-PAN assertion; webhook signature verification.
**Out of scope:** scheduling/receipts (WO-69).

#### WO-69 — Monthly payment processing + receipts
**Spec refs:** §13 (Failed Payments)
**Goal:** Scheduled processing of `payment_schedules`; `payments` records with retry; receipts (PDF); failed-payment immediate advisor + client notification.
**Depends on:** WO-68.
**Key files:** `payments` + `receipts` tables/models, `app/Console/Commands/ProcessScheduledPayments.php`, receipt PDF, alerts.
**Acceptance:** due schedule charges and records a payment; failure triggers retry then failover then dual notification; receipt PDF generated on success; all audited; **a failed charge (incl. the first charge) raises an alert but never reverts the proposal's `signed` state** (per the §7.4 status rule — `signed` is independent of charge success).
**Tests:** scheduled charge; failed-payment notification; retry/failover; receipt generation; **failed first charge does not change proposal status from `signed`**.
**Out of scope:** none.

### Broker & Coach track

#### WO-70 — Panel-portal foundation (shared broker/coach model)
**Spec refs:** §14; §15; Appendix D
**Goal:** Shared external-panel foundation per §7.1: invite → application → approval queue → panel agreement (gen + sign + T&C gate) → referral lifecycle + per-referral messaging → reverse referrals → no-fee mutual-referral terms. External-portal layout + permissions.
**Depends on:** Phase 1 WO-08 (invite/MFA), WO-11 (signed PDF/T&C), WO-07 (RBAC for broker/coach roles).
**Key files:** `panel_members`/`panel_agreements`/`referrals`/`referral_messages`/`reverse_referrals` tables + models, `app/Services/Panels/*`, external-portal layout, broker + coach roles wired.
**Acceptance:** invite → accept → application → admin approval → signed panel agreement gates portal access; referral lifecycle transitions audited; reverse referral one-click creates a prospect/profile without auto-granting platform access.
**Tests:** onboarding flow; agreement gate; referral stage transitions; reverse referral; RLS/permission isolation between panels.
**Out of scope:** broker FSP specifics (WO-71); coach specialisations (WO-72).

#### WO-71 — Insurance Broker portal
**Spec refs:** §14
**Goal:** Broker specialisation: FSP-register validation at onboarding + periodic re-verification (lapse → auto-suspend + advisor alert); broker referral stages; broker panel agreement key clauses.
**Depends on:** WO-70; fill `Fsp` integration contract.
**Key files:** broker fields, `app/Services/Panels/Broker/*`, FSP client (live via `ResilientHttp`), re-verification scheduler.
**Acceptance:** FSP validated at approval; periodic check flips `fsp_status` to lapsed and auto-suspends; broker referral stages match spec; fixtures back tests.
**Tests:** FSP validation; lapse → suspend; referral stages.
**Out of scope:** coach portal.

#### WO-72 — Coach portal (5 specialisations)
**Spec refs:** §15
**Goal:** Coach specialisation: 5 fixed specialisations; admin-managed vetting (no mandatory register; professional-body membership displayed where held); coach panel agreement incl. **wellbeing scope-boundary clause** (coaching only, not clinical mental health); coach referral stages; who-can-be-referred rules (owner, key staff w/ authorisation, entrepreneur).
**Depends on:** WO-70.
**Key files:** coach specialisation enum, `app/Services/Panels/Coach/*`, coach agreement clauses, coach referral stages.
**Acceptance:** 5 specialisations selectable; vetting bar admin-managed; agreement includes wellbeing scope boundary; key-staff referral requires client authorisation.
**Tests:** specialisation handling; agreement clauses; authorisation gate.
**Out of scope:** signal detection (WO-73).

#### WO-73 — Coaching referral signal detection
**Spec refs:** §15.4; §23 layer "Coach referral signal calibration"
**Goal:** Consume the Phase 2 `coaching_signals` rows; map signals → suggested specialisation per the §15.4 threshold table; **inform the advisor only — the advisor always makes the final referral decision; signals never auto-refer.** Signal-calibration learning layer produces governed candidates.
**Depends on:** WO-72; Phase 2 `coaching_signals` (WO-20/35/54/64).
**Key files:** `app/Services/Panels/Coach/SignalDetector.php`, advisor signal-review panel, calibration learning layer.
**Acceptance:** each §15.4 signal maps to the right specialisation suggestion; surfaced to the advisor; **no automatic referral**; calibration emits governed candidates only.
**Tests:** each threshold mapping; advisor-only/no-auto-refer; candidate emission.
**Out of scope:** none.

#### WO-74 — Referral conflict + consent wiring
**Spec refs:** §6; §14; §15
**Goal:** Require a fresh conflict-of-interest declaration before any broker/coach referral (extends the Phase 1 primitive); enforce the mandatory consent election (revocable) before a referral is sent.
**Depends on:** WO-70, Phase 1 WO-21 (conflict primitive), Phase 2 `Consent`.
**Key files:** `app/Services/Conflicts/*` extension, consent gate in referral creation.
**Acceptance:** cannot send a referral without a fresh per-type conflict declaration + active consent; revoking consent blocks/withdraws.
**Tests:** conflict gate; consent gate; revoke behaviour.
**Out of scope:** none.

### Due Diligence track

#### WO-75 — DD onboarding + acquisition target tab + DD questionnaire
**Spec refs:** §16.1; §8 (DD-Specific Set)
**Goal:** DD client onboarding (DD questionnaire only — standard advisory deferred to post-acquisition); dedicated acquisition-target tab (target docs/analysis separate from buyer's own data); mandatory conflict declaration before DD begins; adaptive DD questionnaire.
**Depends on:** Phase 2 WO-31 (spine), Phase 1 WO-17 (questionnaire engine, `dd_specific` set), WO-21 (conflict).
**Key files:** `dd_engagements` table + model, fill `dd_specific` questionnaire, acquisition-target tab UI, DD onboarding flow.
**Acceptance:** DD engagement created with conflict declaration; DD questionnaire presented (not standard advisory); target data isolated from buyer data; liability disclaimer shown.
**Tests:** DD onboarding; conflict gate; data isolation; disclaimer presence.
**Out of scope:** data room (WO-76).

#### WO-76 — DD virtual data room + guest upload
**Spec refs:** §16.3
**Goal:** Workstream-organised data room (separate from standard filing); tokenised guest upload (upload-only, no account, virus-scanned, audit-logged, instantly revocable).
**Depends on:** WO-75, Phase 1 WO-06 (SecureFileWriter/scanner).
**Key files:** `dd_data_room_items` + `dd_guest_links` tables/models, `app/Services/Dd/DataRoom.php`, guest upload controller (tokenised, no auth).
**Acceptance:** guest link uploads to the right workstream folder, cannot view anything, virus-scanned, audit-logged; revoke is instant; items tagged `dd_artifact`.
**Tests:** guest upload scope; no-view enforcement; virus scan; instant revoke; audit.
**Out of scope:** workstream analysis (WO-77).

#### WO-77 — DD eight workstreams (spine analysis + verification)
**Spec refs:** §16.2; §9 (double-weighting)
**Goal:** All 8 workstreams (Financial, Valuation, Legal, Tax, Commercial/Market, Operational, HR/People, NZ Regulatory) run on the analysis spine with **double-weighted** document verification; NZ-specific checks (PPSR, LINZ, IPONZ, IRD, Holidays Act liability, owner-dependency score).
**Depends on:** WO-76; fill `Ppsr`/`Linz`/`Iponz` contracts; Phase 2 spine + HR/compliance modules.
**Key files:** `dd_workstreams` table/model, `app/Services/Dd/Workstreams/*` (8 modules on the spine), legal integration clients.
**Acceptance:** each workstream produces spine findings with attribution + double-weighted document support; NZ-specific checks run; accuracy discrepancies pause the affected workstream.
**Tests:** each workstream module; double-weight verification; NZ checks; pause-on-discrepancy.
**Out of scope:** valuation (WO-78), report (WO-80).

#### WO-78 — DD business valuation + FX normalisation
**Spec refs:** §16.2 (Valuation); §16.3 (Foreign Currency); §12
**Goal:** DD valuation reusing the Phase 2 `PvEngine`/`BusinessValuation` (SDE, EBITDA, DCF+PV side-by-side, reconciled range, buyer negotiating position); RBNZ FX normalisation to NZD (rate timestamped, ±10% sensitivity).
**Depends on:** WO-77, Phase 2 WO-41 (business valuation), WO-36 (RBNZ feed).
**Key files:** `dd_valuations` table/model, `app/Services/Dd/Valuation.php`, FX normaliser.
**Acceptance:** three methods + PV reconcile; FX normalised with timestamped RBNZ rate + ±10% sensitivity; reuses Phase 2 PV engine (no duplicate valuation math).
**Tests:** valuation reconciliation; FX normalisation + sensitivity; PV-engine reuse.
**Out of scope:** none.

#### WO-79 — DD business plan builder
**Spec refs:** §16.3; §17.13
**Goal:** Business plan builder available during DD; DD findings auto-populate relevant plan sections as workstreams complete; completeness check on "acquisition proceeding". Shares the entrepreneur plan-builder engine.
**Depends on:** WO-77 **and WO-84 (the shared plan-builder engine)**. ⚠️ **Cross-track ordering:** WO-79 is numbered in the DD track but is **blocked until WO-84 lands** — the `business_plans`/`plan_phases`/`plan_sections` engine is built once in WO-84 and reused here. Do **not** start WO-79 before WO-84, regardless of numeric order. (Alternative if the DD track must proceed first: pull the plan-builder engine out of WO-84 into a shared earlier WO — but the default plan is WO-84-first.)
**Key files:** `business_plans` linkage (`dd_engagement_id`), `app/Services/Dd/PlanBuilder.php` (thin adapter over the WO-84 engine — no duplicate plan-builder logic).
**Acceptance:** DD findings auto-populate plan sections; completeness check at acquisition-proceeding; DD-built plan becomes the founding plan for the new advisory engagement (WO-81).
**Tests:** auto-population from workstreams; completeness gate; handoff to advisory.
**Out of scope:** none.

#### WO-80 — DD report generation
**Spec refs:** §16.4; §19 (DD Report)
**Goal:** Separate branded DD report (via `ReportComposer`, `ReportType::DUE_DILIGENCE`): exec summary, valuation (3 methods + PV), workstream findings, risk register (deal-killer/major/minor/informational), price-adjustment schedule, 100-day integration plan, buyer-readiness; three-outcome recommendation (Proceed / Renegotiate / Abandon). **Liability disclaimer on every output.**
**Depends on:** WO-77, WO-78; Phase 2 WO-57 (report engine), WO-42 (risk PV).
**Key files:** `dd_risk_register` + `dd_integration_plans` tables, DD report type, recommendation logic.
**Acceptance:** report includes all sections; risk register ranked by PV of cost; recommendation is one of three with rationale; disclaimer present; PDF + PowerPoint.
**Tests:** report composition; risk-register ranking; recommendation paths; disclaimer presence.
**Out of scope:** post-acquisition (WO-81).

#### WO-81 — Post-acquisition advisory pipeline
**Spec refs:** §16.4; §2.1
**Goal:** Advisor marks "Acquisition Proceeding" → acquired business becomes its own advisory client (DD docs migrated, "Sourced from DD" label); intelligent gap questionnaire (standard advisory cross-referenced against DD data, pre-populates answered fields, client completes gaps only); auto-generated post-acquisition proposal (PV baseline from DD, unusually precise outcome-based fee).
**Depends on:** WO-80; Phase 2 WO-56 (proposal); Phase 1 `post_acquisition_gap` set; `LifecycleManager`.
**Key files:** `post_acquisition_migrations` table, `app/Services/Dd/PostAcquisition.php`, gap-questionnaire generator, auto-proposal generator.
**Acceptance:** new advisory client created with migrated + labelled docs; gap questionnaire pre-populates from DD and asks only the gaps; auto-proposal carries the DD-derived PV baseline.
**Tests:** profile migration + labelling; gap-questionnaire cross-reference; auto-proposal PV baseline.
**Out of scope:** none.

### Entrepreneur track

#### WO-82 — Readiness assessment
**Spec refs:** §17.1
**Goal:** Entrepreneur onboarding (invite/MFA/T&C/entrepreneur fee proposal — reuse Phase 1/2); 15–20 question readiness assessment → Ready / Develop First / Not Yet, capturing personal-readiness barriers. **Also: retrofit the missing RLS on `entrepreneur_profiles`** (Phase 1 WO-15 created it without a policy) and add the `fsa_current_user_id()` SQL helper — both are prerequisites for entrepreneur-scoped RLS across the track (see §4.5 RLS note).
**Depends on:** Phase 1 WO-15 (entrepreneur profile), WO-17 (`entrepreneur_readiness` set), WO-02 (RLS harness — extend with `fsa_current_user_id()`).
**Key files:** `readiness_assessments` table/model + RLS policy, `entrepreneur_profiles` RLS retrofit migration, `fsa_current_user_id()` helper, `app/Services/Entrepreneurs/Readiness.php`, entrepreneur portal pages.
**Acceptance:** assessment produces an outcome; personal barriers recorded; a "Develop First" with personal barriers writes a raw `coaching_signals` row (consumed by WO-73); **`entrepreneur_profiles` and `readiness_assessments` enforce advisor/entrepreneur/super-admin RLS** (an unassigned advisor and a different entrepreneur see nothing).
**Tests:** outcome computation; barrier capture; coaching-signal write; **`entrepreneur_profiles` + `readiness_assessments` RLS isolation** (unassigned advisor / other entrepreneur blocked).
**Out of scope:** idea validation (WO-83).

#### WO-83 — Idea validation
**Spec refs:** §17.1
**Goal:** Problem/customer/solution/value-prop/demand/revenue capture; AI evaluation against past plan patterns (on the spine); concept viability alerts; **advisor gate before the plan builder opens**.
**Depends on:** WO-82, Phase 2 WO-31 (spine).
**Key files:** `idea_validations` table/model, `app/Services/Entrepreneurs/IdeaValidation.php`.
**Acceptance:** AI evaluation cites past-plan patterns; viability alerts raised (informational, not blockers); plan builder stays locked until the advisor passes the gate.
**Tests:** AI evaluation with FakeAiClient; viability alerts; advisor gate.
**Out of scope:** plan builder (WO-84).

#### WO-84 — 5-phase milestone plan builder *(shared engine — prerequisite for WO-79)*
**Spec refs:** §17.1
**Goal:** 5 phases (Foundation, Market, Strategy, Legal & Operations, Financial) with logical dependencies + dependency warnings when jumping ahead. **This is the shared plan-builder engine** — the DD plan builder (WO-79) is a thin adapter over it, so WO-84 must land before WO-79.
**Depends on:** WO-83.
**Key files:** `business_plans`/`plan_phases`/`plan_sections` tables/models, `app/Services/Entrepreneurs/PlanBuilder.php`, builder UI.
**Acceptance:** phases ordered with dependencies; dependency warning on jump-ahead; sections persist. (Shared engine reused by DD plan builder WO-79.)
**Tests:** phase dependencies; jump-ahead warning; persistence.
**Out of scope:** AI guidance (WO-85), scoring (WO-88).

#### WO-85 — AI-guided building + predictive score + NZ resources
**Spec refs:** §17.2
**Goal:** Section-specific AI guidance citing past-plan patterns ("Based on [X] plans in this industry…"); gap detection; industry risk awareness; **live predictive score (no flattery)**; NZ resource layer recommendations by industry/type/gap.
**Depends on:** WO-84, Phase 2 WO-31 (spine), heightened integrity (§7.6).
**Key files:** `nz_resources` table/model, `app/Services/Entrepreneurs/Guidance.php`, predictive scorer (governed by integrity).
**Acceptance:** guidance is evidence-cited; predictive score reflects real quality (no inflation — integrity test); resources recommended by gap.
**Tests:** evidence-cited guidance; no-flattery predictive score; resource recommendation.
**Out of scope:** formal assessment (WO-88).

#### WO-86 — Section-attached document verification
**Spec refs:** §17.3; §9
**Goal:** Entrepreneurs attach supporting docs to plan sections; full AI relevance/accuracy verification; **document support affects criterion score**; a claim contradicted by the entrepreneur's own document is an accuracy discrepancy that **blocks scoring until resolved**.
**Depends on:** WO-84, Phase 1 WO-18 (verification, `plan_attachment` category).
**Key files:** `app/Services/Entrepreneurs/PlanDocuments.php`, verification wiring.
**Acceptance:** attachments verified; verified evidence raises the criterion score vs unsupported claims; self-contradiction blocks scoring.
**Tests:** verification; score effect; block-on-discrepancy.
**Out of scope:** scoring mechanics (WO-88).

#### WO-87a — Admin-managed rating framework engine *(buildable now)*
**Spec refs:** §17.4; §17.6; Appendix C
**Goal:** The admin-managed framework engine: `rating_frameworks`/`rating_criteria` tables, the admin framework editor (edit criteria/weights/per-band descriptors/industry variants with **no code change**), versioning, and the governed-queue evolution path (3 dimensions). Seed the **11 founding criteria** from spec §17.6 / Appendix C (owner decision 2026-05-23: the framework follows the spec's 11-item list — the rating-matrix PDF was its *starting point*, the spec is the refined authoritative version; see P3-R3a). The 11 criterion names are fixed by the spec; weights, per-band scoring descriptors, and industry variants are **admin-managed** — seed with placeholder defaults flagged `is_placeholder=true` until WO-87b. Overall-grade bands per spec §17.5 (Exceptional 90%+, Strong 75–89%, Developing 60–74%, Needs Work <60%).
**Depends on:** Phase 2 learning-queue scaffolding.
**Key files:** `rating_frameworks`/`rating_criteria` tables/models, admin framework editor UI, criteria seeder (11 names from spec §17.6 / Appendix C; placeholder weights/descriptors flagged `is_placeholder=true`).
**Acceptance:** the 11 founding criteria seed (names per spec) with placeholder weights/descriptors flagged placeholder; admin can edit weights/descriptors/variants without code; changes versioned; overall-grade bands match §17.5; learning-driven changes go through the governed queue (no hardcoding, no silent update); **framework flagged "not production-ready" until WO-87b sets real values.**
**Tests:** 11-criteria seed; admin edit + version; grade-band thresholds; governed-change path; placeholder/production-ready flag surfaced.
**Out of scope:** the real founding weights/descriptors (WO-87b); scoring (WO-88).

#### WO-87b — Set founding weights & scoring descriptors *(owner data-entry via admin editor)*
**Spec refs:** §17.4; §17.6; Appendix C
**Goal:** The owner sets the **founding weights, per-band scoring descriptors, and any industry variants** for the 11 criteria via the WO-87a admin editor (the spec leaves these admin-managed — there are no spec-mandated values). The historical rating-matrix PDF (`docs/rating-criteria/Business_Plan_Rating_Matrix.pdf` + `founding-rating-matrix.md`) is available as reference the advisor may draw on. Clear `is_placeholder`; set `production_ready=true`.
**Depends on:** WO-87a; **owner-entered founding weights/descriptors.** ⛔ **Blocked on owner data-entry** (the values are admin-managed, not in the spec or PDF as a fixed set). Does **not** block WO-88/89 *development* (they run against the WO-87a placeholder framework); it gates entrepreneur **go-live**.
**Key files:** founding values entered via the WO-87a editor (likely no code) + a confirmation/seed step that flips the flags.
**Acceptance:** the 11 criteria carry the owner-set weights + descriptors + any industry variants; placeholder flags cleared; `production_ready=true`.
**Tests:** values persisted + production-ready gate flips; placeholder flags cleared.
**Out of scope:** none.

#### WO-88 — AI first-pass scoring + advisor assessment + mentor notes
**Spec refs:** §17.5
**Goal:** AI first-pass scores all **11 criteria** on submission using the current framework; advisor adjusts any score with a **mandatory note** (each adjustment teaches the AI via the governed queue); mentor notes (per-section visible, overall visible, **private advisory note advisor-only**); criteria hidden during building, revealed in the report appendix.
**Depends on:** **WO-87a** (the framework engine — WO-88 develops/tests against it), WO-86, Phase 2 WO-31 (spine), WO-32 (feedback). Real entrepreneur assessments require **WO-87b** (founding values) before go-live.
**Key files:** `plan_assessments` table/model, `app/Services/Entrepreneurs/Assessment.php`.
**Acceptance:** AI scores all 11 criteria; advisor adjustment requires a note and emits a governed learning candidate; private advisory note never visible to the entrepreneur; criteria hidden until finalisation.
**Tests:** first-pass scoring; mandatory-note enforcement; private-note visibility; criteria hidden/revealed.
**Out of scope:** report (WO-89).

#### WO-89 — Assessment report (4-part + concept PV)
**Spec refs:** §17.5; §19 (Entrepreneur Assessment Report)
**Goal:** 4-part report (per-criterion AI + advisor-adjusted scores w/ document-support notation + data-quality indicator; specific written feedback per criterion; overall grade Exceptional/Strong/Developing/Needs Work w/ rationale; prioritised improvement actions w/ NZ resources) + concept PV projection. Via `ReportComposer`, `ReportType::ENTREPRENEUR_ASSESSMENT`.
**Depends on:** WO-88, Phase 2 WO-57 (report engine), WO-43 (PV).
**Key files:** entrepreneur-assessment report type, concept-PV calc.
**Acceptance:** 4 parts present with notations; overall grade thresholds correct; concept PV projected; honest tone (integrity test).
**Tests:** 4-part composition; grade thresholds; concept PV; notation presence.
**Out of scope:** revision (WO-90).

#### WO-90 — Iterative resubmission + round-by-round progress
**Spec refs:** §17.7
**Goal:** Unlimited revision rounds; round-by-round per-criterion score comparison, biggest improvements highlighted, remaining gaps, overall trajectory %.
**Depends on:** WO-89.
**Key files:** `plan_revisions` table/model, progress-comparison UI.
**Acceptance:** resubmission re-assesses; round comparison shows per-criterion deltas + trajectory %.
**Tests:** resubmission; round comparison; trajectory.
**Out of scope:** none.

#### WO-91 — Benchmarking + advisory readiness + living plan
**Spec refs:** §17.7
**Goal:** Anonymous **aggregate** plan benchmarking vs prior same-industry plans (no plan content shared); advisory-readiness indicator (auto-alert to advisor + entrepreneur dashboard progress); living business plan (post-launch quarterly update prompts, re-assessed, divergence flags advisory readiness).
**Minimum-cohort privacy rule (spec §11 "minimum cohort sizes enforced"):** a benchmark is computed and shown **only if the cohort has at least `BENCHMARK_MIN_COHORT` prior finalised plans** (config, **default 5**). Below the threshold the benchmark is **suppressed** with a "not enough comparable plans yet" message — never a partial/identifiable figure. Only aggregate statistics (distribution, percentile band) are returned; no per-plan values, and no min/max that could reverse-identify a single plan when the cohort is near the floor.
**Depends on:** WO-89.
**Key files:** `advisory_readiness_signals` table/model, benchmarking aggregator (aggregate-only, min-cohort suppression), living-plan scheduler, `BENCHMARK_MIN_COHORT` config.
**Acceptance:** benchmarking is aggregate-only; **a cohort below `BENCHMARK_MIN_COHORT` is suppressed (no figure shown)**; readiness indicator alerts advisor; living-plan prompts quarterly and re-assesses.
**Tests:** aggregate-only benchmarking; **suppression below min cohort (e.g. cohort of 4 with default 5 → suppressed; 5 → shown)**; no single-plan-identifiable output near the floor; readiness alert; living-plan re-assessment.
**Out of scope:** Phase 4 anonymous community/peer network.

#### WO-92 — Advisory conversion + DD plan integration
**Spec refs:** §17.1; §17.7
**Goal:** Advisory conversion (advisor-initiated or entrepreneur-requested) pre-populates a new advisory profile from all entrepreneur data; advisor capacity management (extends `AdvisorEntrepreneurCapacity`); DD integration (plan builder available during DD — links to WO-79).
**Depends on:** WO-89, `LifecycleManager`, Phase 1 `AdvisorEntrepreneurCapacity`.
**Key files:** `app/Services/Entrepreneurs/AdvisoryConversion.php`, capacity wiring.
**Acceptance:** conversion pre-populates an advisory profile; capacity enforced (warn 24 / block 30); DD-built plans flow to advisory.
**Tests:** conversion data pre-population; capacity gates; DD plan handoff.
**Out of scope:** none.

### Learning governance & polish track

#### WO-93 — Learning update queue admin UI
**Spec refs:** §23
**Goal:** Surface `learning_updates` as Update Summary Cards; 4-decision approval (Approve / Approve-modified-date / Defer / Reject); 7-day pre-implementation notice; 30-day post-implementation review scheduling.
**Depends on:** Phase 1/2 learning scaffolding.
**Key files:** `learning_update_decisions` table, admin learning-queue UI, `app/Services/Learning/ApprovalFlow.php`.
**Acceptance:** cards show all summary fields; each decision recorded + audited; approval schedules the 7-day notice and 30-day review; nothing implements without approval.
**Tests:** card data; each decision path; notice/review scheduling; no-auto-implement guard.
**Out of scope:** rollback (WO-94).

#### WO-94 — Learning update rollback + audit
**Spec refs:** §23
**Goal:** Rollback an implemented update, restoring prior state; full audit trail.
**Depends on:** WO-93.
**Key files:** `learning_rollbacks` table, `app/Services/Learning/Rollback.php`.
**Acceptance:** rollback restores the prior state and is audited; a rolled-back update is clearly marked.
**Tests:** state restoration; audit; idempotency.
**Out of scope:** none.

#### WO-95 — 32-layer cadence setup + monitoring dashboard
**Spec refs:** §23.1
**Goal:** Wire scheduled cadence for all 32 layers (those not already running) into `LearningLayerRun`; admin monitoring dashboard (pending approvals w/ urgency, scheduled updates, rollback history).
**Depends on:** WO-93, WO-94.
**Key files:** layer schedulers, monitoring dashboard.
**Acceptance:** each layer runs on its spec §23 cadence and records a `LearningLayerRun`; dashboard shows the queue + history; layers only produce governed candidates.
**Tests:** cadence registration; layer-run recording; dashboard data.
**Out of scope:** Phase 4 active self-tuning behaviours.

#### WO-96 — T&C version-manager UI polish
**Spec refs:** §18
**Goal:** Round out the Phase 1 admin T&C editor: whole-doc + per-clause editing, material vs non-material classification UI, version history viewer. (Most logic exists from WO-10; this is the admin-UX completion.)
**Depends on:** Phase 1 WO-10.
**Key files:** admin T&C UI enhancements.
**Acceptance:** admin edits clauses + classifies material/non-material; version history browsable; material publish triggers the existing 30-day re-acceptance flow.
**Tests:** clause edit; classification; history; re-acceptance trigger.
**Out of scope:** none.

#### WO-97 — Testimonial capture
**Spec refs:** §6 (Testimonial Capture)
**Goal:** Triggered at NPS 8+; explicit consent for FSA marketing; named or anonymous; stored in a testimonial library with consent status.
**Depends on:** Phase 1 WO-12 (notifications), offboarding/milestone NPS.
**Key files:** `testimonials` table/model, capture flow, library view.
**Acceptance:** NPS 8+ triggers a request; consent (named/anonymous) captured; library shows approved testimonials with consent status.
**Tests:** NPS trigger; consent capture; library filter.
**Out of scope:** none.

#### WO-98 — Voice-to-text notes + call logs
**Spec refs:** §21 (Voice-to-Text, Meeting Notes, Phone Call Logs)
**Goal:** In-app voice recording → Whisper transcription → AI summary (key points/decisions/actions); structured meeting notes + phone-call logs with action items linked to milestones.
**Depends on:** Phase 1 storage; fill `Whisper` contract; WO-65 (milestones for action links).
**Key files:** `voice_notes` + `call_logs` tables/models, Whisper client (live via `ResilientHttp`), summary via `AiClient`.
**Acceptance:** recording transcribes (fixture in tests); AI summary extracts actions; action items link to milestones; all through `AiClient`/`ResilientHttp`.
**Tests:** transcription (fixture); summary extraction; action linkage.
**Out of scope:** voice assistant (Phase 4).

#### WO-99 — Bulk communications
**Spec refs:** §21 (Bulk Communications)
**Goal:** Branded templates; selected clients or all; scheduling; open-rate tracking; channel-preference aware.
**Depends on:** Phase 1 WO-12 (channel resolver), WO-26 (email).
**Key files:** `bulk_communications` table/model, scheduler, open-tracking.
**Acceptance:** branded template sent to an audience respecting channel preference; scheduled sends fire; open rate tracked.
**Tests:** audience selection; channel preference; scheduling; open tracking.
**Out of scope:** none.

#### WO-100 — Document expiry tracking
**Spec refs:** §26 Phase 3 row
**Goal:** Track `documents.expires_at`; reminder notifications ahead of expiry (idempotent).
**Depends on:** Phase 1 WO-06 (documents), WO-12.
**Key files:** expiry reminder scheduler, optional `document_expiry_reminders` ledger.
**Acceptance:** documents with an expiry produce a reminder ahead of time, once; routed via `ChannelResolver`.
**Tests:** reminder timing; idempotency.
**Out of scope:** none.

#### WO-101 — Offline mode for client portal (PWA)
**Spec refs:** §7 (Offline Mode)
**Goal:** PWA architecture; questionnaire responses + document upload queue captured offline; encrypted local storage; auto-sync on reconnect.
**Depends on:** Phase 1 WO-16 (portal), WO-17 (questionnaire), WO-18 (upload).
**Key files:** service worker, offline store (encrypted), sync queue, PWA manifest.
**Acceptance:** portal works offline for questionnaire + upload queue; local store encrypted; reconnect syncs without data loss or duplicates.
**Tests:** offline capture; encrypted store; sync + dedupe (frontend tests where feasible).
**Out of scope:** native mobile app (Phase 4).

---

## 9. Acceptance & Definition of Done (per WO)

Same bar as Phase 1/2, plus:

1. All listed files exist; no orphans. New tables documented in `docs/architecture/schema.md`; new client-scoped tables have an RLS policy **and** an RLS test.
2. Every acceptance criterion demonstrably true (cite test names).
3. `composer test`, Pint, ESLint, Prettier, `tsc --noEmit` all pass.
4. No `TODO`/`FIXME` without a linked WO; no `dd()`/`dump()`/`console.log()`.
5. No Phase 4 feature introduced.
6. **DD/entrepreneur analysis routes through the spine; no module re-implements integrity/verification/data-quality gates.**
7. **Every DD output carries the liability disclaimer.**
8. **No payment surface stores raw card data; all gateway calls through `ResilientHttp`; a gateway failure never yields a false paid/signed state.**
9. **No learning update changes behaviour without owner approval; the rating framework is never hardcoded.**
10. Architecture doc added/updated under `docs/architecture/`.

## 10. Test strategy (Phase 3 additions)

- **Payment safety tests** — no-PAN-persisted assertion; Stripe→Windcave failover; dual-failure handling; gateway-outage never flips to `signed`/paid.
- **Sign-off ordering test** — all **7** steps enforced in order; `awaiting_signature` requires a tokenised authority; `signed` only after the `signature` step; a failed/later charge never reverts `signed`.
- **DD double-weight verification test** — DD uploads block analysis on outstanding flags, double-weighted.
- **DD disclaimer test** — every DD output renders the liability disclaimer.
- **Guest-upload security test** — upload-only, no view, virus-scanned, instant revoke, audited.
- **Entrepreneur integrity tests** — predictive score not inflated; self-contradicting document blocks scoring; private advisory note never visible to the entrepreneur; criteria hidden until finalisation.
- **Rating-framework governance test** — framework changes only via the governed queue; never hardcoded.
- **Learning approval/rollback tests** — no auto-implement; 4 decisions; rollback restores state.
- **Panel isolation tests** — broker/coach portals are RLS/permission-isolated; reverse referral grants no platform access.
- **RLS tests** — every new client-scoped table (goals, milestones, proposals' children, panel/referral, all `dd_*`). **Entrepreneur-scoped tables** (incl. the retrofitted `entrepreneur_profiles`) get their own RLS tests proving advisor/entrepreneur/super-admin visibility via `entrepreneur_profiles` ownership — an unassigned advisor and a different entrepreneur are blocked (see §4.5).
- **XOR-ownership constraint tests** — `referrals` (client XOR entrepreneur) and `business_plans` (entrepreneur XOR DD) reject both-set and neither-set inserts at the DB level; `reverse_referrals` rejects both `created_*` set.
- **`FakeAiClient` everywhere; live client never bound in `testing`.**

## 11. Phase 4 — what comes after (unchanged roadmap)

See `PLAN.md` §11. Phase 4: full continuous-learning engine (32 layers active), automated bias calibration, DD/plan-quality/conversion-outcome learning, predictive-validity testing, cross-client + shared + anonymous-community + peer-network intelligence, voice assistant, mobile app, advisor API, multi-advisor scaling, NZ business-tool integrations, industry-WACC automation, annual security/legal audit framework, and **PQC + HSM** (SD-01/SD-02).

## 12. Open risks & decisions to revisit (Phase 3)

| # | Risk / open item | Owner action | Trigger |
|---|---|---|---|
| P3-R1 | Stripe + Windcave **merchant onboarding takes weeks** + PCI-DSS attestation | Start merchant applications immediately | Before WO-68 live |
| P3-R2 | Payment is the highest-risk surface — needs NZ-qualified review (spec §27) before real charges | Engage reviewer | Before WO-69 production |
| P3-R3 | ~~`Business_Plan_Rating_Matrix.pdf` missing~~ — **RESOLVED 2026-05-23.** PDF supplied and in `docs/rating-criteria/`, transcribed to `founding-rating-matrix.md`. | — | Closed |
| P3-R3a | ~~Spec §17.6/Appendix C vs the PDF disagree~~ — **RESOLVED 2026-05-23.** Owner decision: the PDF was the *starting point* for developing the spec; **the framework follows the spec's 11-item list** (§17.6 / Appendix C). PDF retained as historical reference. WO-87a seeds the 11 criteria; WO-87b is owner data-entry of admin-managed weights/descriptors. | — | Closed (founding weights/descriptors still owner-set in WO-87b before go-live) |
| P3-R4 | FSP Register / PPSR / LINZ / IPONZ access (some need agreements) | Register/arrange | Before WO-71 (FSP) / WO-77 (legal workstream) live |
| P3-R5 | DD liability disclaimer + panel agreements + entrepreneur fee T&C need NZ lawyer review | Engage lawyer | Before any DD output / panel agreement / entrepreneur engagement goes live |
| P3-R6 | Whisper (or AWS Transcribe) credentials + cost for voice-to-text | Provision + budget | Before WO-98 live |
| P3-R7 | Calendar two-way sync (Google/Microsoft) OAuth apps + review | Register apps | Before WO-98 calendar linkage |
| P3-R8 | Entrepreneur module heightened-integrity exposure (vulnerable users) — extra QA on predictive score honesty | Owner QA + integrity tests | Before entrepreneur module goes live |
| P3-R9 | Carryover: Anthropic key, Meridian Warm brand kit, lawyer-reviewed advisory T&C | Owner supply | Before client-facing Phase 3 output |

## 13. Phase 3 feature → WO mapping (traceability)

| Spec §26 Phase 3 item | WO(s) |
|---|---|
| Goals & milestones tracker (PV + proof) | WO-65 |
| Digital proposal sign-off (7-step) | WO-66 |
| Insurance/coach consent in proposal | WO-66, WO-74 |
| Card + direct-debit authority (capture) | WO-66 |
| Payment schedules | WO-67 |
| Stripe + Windcave failover | WO-68 |
| Monthly payment processing + receipts | WO-69 |
| Proposal expiry (countdown/alert/renewal) | Phase 2 WO-56 (base) + WO-66 (sign-off) |
| Broker portal (full) | WO-70, WO-71 |
| Coach portal (full, 5 specialisations) | WO-70, WO-72 |
| Coach panel agreement | WO-70, WO-72 |
| Coaching referral signal detection | WO-73 |
| Conflict declaration (broker/coach) | WO-74 |
| DD questionnaire + adaptive | WO-75 |
| DD virtual data room | WO-76 |
| DD guest upload | WO-76 |
| DD 8 workstreams | WO-77 |
| DD valuation (3 methods + PV) | WO-78 |
| DD FX normalisation + sensitivity | WO-78 |
| DD business plan builder | WO-79 |
| DD report | WO-80 |
| Post-acquisition pipeline | WO-81 |
| Post-acquisition auto-proposal | WO-81 |
| Entrepreneur readiness | WO-82 |
| Entrepreneur idea validation | WO-83 |
| Entrepreneur 5-phase builder | WO-84 |
| AI-guided building + predictive score | WO-85 |
| Section-attached verification | WO-86 |
| Admin-managed rating framework (engine, 11 criteria) | WO-87a |
| Rating framework founding weights/descriptors (owner-set) | WO-87b |
| AI first-pass + advisor assessment + mentor notes | WO-88 |
| Assessment report (4-part + concept PV) | WO-89 |
| Iterative resubmission + round progress | WO-90 |
| NZ resources / benchmarking / viability / readiness / living plan | WO-85 (resources/viability), WO-91 (benchmarking/readiness/living) |
| Advisory readiness indicator + conversion | WO-91, WO-92 |
| Advisor capacity management | WO-92 |
| Entrepreneur DD integration | WO-79, WO-92 |
| T&C version manager UI | WO-96 |
| Learning update queue (admin UI, 4-decision) | WO-93 |
| Learning audit trail + rollback | WO-94 |
| All 32 learning layers cadence | WO-95 |
| Testimonial capture | WO-97 |
| Voice-to-text notes | WO-98 |
| Bulk communications | WO-99 |
| Document expiry tracking | WO-100 |
| Offline mode (PWA) | WO-101 |

---

*End of Phase 3 plan. Phase 4 plan (active learning engine, cross-client/shared/community intelligence, voice assistant, mobile, advisor API, PQC + HSM) to be authored after Phase 3 acceptance. `PLAN.md` and `PLAN-PHASE2.md` are intentionally left unchanged.*
