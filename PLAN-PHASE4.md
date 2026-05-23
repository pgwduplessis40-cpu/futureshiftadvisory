# Future Shift Advisory — Phase 4 Implementation Plan (Intelligence Expansion)

**Source spec:** `docs/spec/Future_Shift_Advisory_App_Specification_v2.4.docx` (definitive, May 2026)
**Plan scope:** Phase 4 (Months 9–12) — the final phase. Brings the platform to the complete V2.4 vision.
**Predecessors:** Phase 1 (WO-01…30) and Phase 2 (WO-31…64) — **complete and full-suite-verified**; Phase 3 (WO-65…101) — **complete and structurally verified** (all WOs committed, services present, static checks green, zero forbidden markers); the full-suite pass count is being recorded (see §3.0). On `featureApp`.
**Work orders:** WO-102 … WO-120 (continues the single global sequence).
**Plan version:** 1.0

> Companion to `PLAN.md`, `PLAN-PHASE2.md`, `PLAN-PHASE3.md` — they stay as-is. Phase 4 is "Intelligence Expansion": it turns on the learning behaviours scaffolded earlier, adds population-scale (privacy-critical) intelligence, extends platform reach (voice/mobile/API/integrations), and closes the two security deferrals (**PQC + HSM**, SD-01/SD-02).

---

## 0. How to use this plan

1. Read §1–§7 once before starting. They carry forward the non-negotiable principles and lock the Phase 4 architecture (governed *active* learning, population-scale privacy, the PQC envelope swap, external-reach security).
2. Implement **Work Orders** in §8. Phase 4 has five tracks (Active Learning, Population Intelligence, Platform Reach, Security Hardening, Scaling) — see §6. Within a track, follow numeric order.
3. **Commit cadence (unchanged from Phase 3 §0):** single `featureApp` branch, **no WO branches/PRs**; **one commit per WO** (`WO-<id>: <slug>`); **`IMPLEMENTATION.md` updated in the same commit**; full verification + push **at each track boundary and at the Phase 4 boundary**. Regenerate Wayfinder (`--with-form`) on any controller change.
4. Every WO ships with its tests. A WO is done only when acceptance criteria are demonstrably true and `composer test`, `npm run lint:check`, `npm run types:check`, `npm run format:check` all pass.
5. **Do not exceed the V2.4 spec.** Phase 4 completes V2.4; anything beyond is a future V2.5+ decision, not this plan.
6. **"Active learning" never means "autonomous."** Every learning layer still emits *governed candidates* into the WO-93 approval queue. No layer modifies platform behaviour, scoring, weights, or prompts without owner approval — even at full scale. This is the single most important Phase 4 invariant.
7. **Population-scale features are privacy-critical.** Cross-client, community, and peer features enforce anonymisation + minimum-cohort (k-anonymity) suppression + moderation. Treat these like the payment surface was in Phase 3: highest-risk, most-tested.
8. **PQC/HSM need NZ-qualified review before production** (spec §27). The crypto swap is real cryptography — do not improvise.

---

## 1. Non-negotiable principles (carried forward — still binding)

Everything in `CLAUDE.md`, `PLAN.md` §1, `PLAN-PHASE2.md` §1, and `PLAN-PHASE3.md` §1 remains in force. Phase 4 stresses three of them hardest:

- **No silent learning (spec §3, §23).** The 32-layer engine goes "active" — it detects patterns and proposes changes continuously across all layers. But **every** proposal is a governed `learning_updates` candidate routed through the WO-93 approval flow (Approve / Approve-modified-date / Defer / Reject) with the 7-day pre-implementation notice, 30-day review, and WO-94 rollback. Detected bias still **enters the queue, never auto-corrects** (spec §3.2). Score/rating/weight/prompt changes never self-apply. A test suite asserts no Phase 4 layer can mutate behaviour without an approved, implemented `learning_update`.
- **AI Integrity Principle (spec §3) at population scale.** Cross-client and benchmarking outputs are evidence-based and source-attributed ("Based on [N] anonymised plans/clients…"), honest, and never fabricated when cohorts are too small (suppressed instead). Heightened entrepreneur integrity (spec §3.3) still holds for industry benchmarks and conversion-outcome signals.
- **Security baseline → completed (spec §4, §27).** Phase 4 closes the Phase 1 deferrals: PQC envelope (**ML-KEM** / FIPS 203 + **ML-DSA** / FIPS 204 — the standardised names for CRYSTALS-Kyber / CRYSTALS-Dilithium — via liboqs) and HSM-backed keys. TLS 1.3 / AES-256 / RLS / immutable audit / invite-only / MFA all remain. All external calls through `ResilientHttp`; all AI through `AiClient`; all audit through `AuditWriter`; all encryption through `KeyEnvelope` (now PQC-capable).
- **Privacy (Privacy Act 2020; spec §11).** Anonymous community/peer/benchmarking features enforce k-anonymity (minimum cohort), aggregation-only outputs, opt-in consent, and human moderation. No feature can reverse-identify a client, entrepreneur, or plan.

---

## 2. Phase 4 goal & scope

### 2.1 Goal (Months 9–12)

Complete the V2.4 vision: the platform learns continuously (under governance), shares anonymised intelligence across the practice and an opt-in community, reaches advisors on mobile and via voice and API, integrates the wider NZ business-tool ecosystem, scales to multiple advisors with team management, and runs production-grade post-quantum cryptography on HSM-managed keys with an annual third-party security/legal audit.

By end of Phase 4 the platform is the complete spec-V2.4 product.

### 2.2 Out of scope (post-V2.4 — do not build)

- Anything not in spec V2.4. New engagement types, new portals, new analysis modules, or AI autonomy beyond the governed queue are V2.5+ decisions.
- **Autonomous (un-approved) learning.** Explicitly forbidden — see §1.
- Non-NZ market features, white-label/multi-tenant-practice, or third-party app marketplace — not in V2.4.

---

## 3.0 Phase 3 baseline

Phase 3 was reviewed and confirmed **structurally complete** before Phase 4 planning. The full-suite pass count is being recorded from an in-progress run and will be filled here before Phase 4 *implementation* starts (the starting gate is not "green" until this row is filled). Baseline; do not regress:

| Gate | Result at handoff |
|---|---|
| WO commits on `featureApp` | **101 / 101** (WO-01 … WO-101, incl. WO-87a/87b) ✅ |
| Forbidden markers (`TODO`/`FIXME` in `app/`+`routes/`, `dd()`/`dump()`, `console.log`) | **0** ✅ |
| Pint · ESLint · `tsc --noEmit` · Prettier | green at Phase 2 handoff; re-confirm at Phase 3 full-suite run ⏳ |
| PHPUnit suite (against PostgreSQL `futureshift_test`) | **⏳ recording — run in progress** (Phase 2 baseline was 293/2190; Phase 3 adds substantially). **Fill the exact count here before starting WO-102.** |

Inventory at handoff: 84 migrations · 103 models · 119 feature-test files · 53 controllers · `app/Services/{…,Dd,Goals,Learning,Panels,Payments,Plans,Testimonials,Voice}`.

> **Running the suite locally** (unchanged): on standalone PostgreSQL, point the test connection at real credentials via the process environment (`DB_*` for `futureshift_test`) before `php artisan test`; `.env.testing` ships Herd defaults.

**Carryover owner inputs that gate Phase 4 surfaces:** liboqs build toolchain + NZ-qualified crypto reviewer (PQC/HSM — WO-117/118/119); CloudHSM / Azure Dedicated HSM provisioning; Apple Developer + Google Play accounts (mobile — WO-115); Siri/Google Assistant developer setup (voice — WO-114); Employment Hero / Cin7 / Tradify API access (WO-113); NZ Exchange / industry-WACC data source (WO-116); legal + security audit firm engagement (WO-119); community/peer moderation policy + privacy counsel sign-off (WO-110/111). Tracked in §12.

---

## 3. What Phase 4 builds on (Phase 1–3 inventory)

Reuse directly — do not re-create.

| Asset | Location | Phase 4 use |
|---|---|---|
| `KeyEnvelope` (tagged `{v,alg,kid,body}`, version-dispatched) | `app/Services/Storage/KeyEnvelope.php` | **PQC swap-in** (WO-117) adds `v2`/`alg=kyber-1024+aes-256-gcm`; the dispatch seam was built in WO-02 |
| `learning_updates` / `learning_update_implementations` / `learning_rollbacks` / `LearningLayerRun` | models | The governed queue every active layer (WO-102…107) feeds |
| `ApprovalFlow` / `Rollback` / `LayerCadenceRegistry` / `LayerCadenceRunner` / `LearningMonitorDashboard` | `app/Services/Learning` | Phase 4 turns the layers "active" through this existing governance |
| `BiasDetector` / `BiasMonitor` | `app/Services/Ai/Integrity` | WO-103 adds automated calibration (still queue-governed) |
| `Benchmarking` (entrepreneur, aggregate + `BENCHMARK_MIN_COHORT`) | `app/Services/Entrepreneurs/Benchmarking.php` | WO-105/110 extend the min-cohort/anonymisation pattern |
| `AnalysisRunner` + `AnalysisFinding` + `RedFlagPromoter` | `app/Services/Analysis` | Cross-client intelligence (WO-108) aggregates findings |
| `dd_*` tables + `Dd/*` services; `plan_assessments`; `advisory_readiness_signals` | models + services | DD-pattern, valuation-accuracy, conversion-outcome learning (WO-104/106) |
| `PvEngine` / `ValuationMultipleProvider` / `DiscountRateResolver` | `app/Services/Pv` | Industry-WACC automation (WO-116) feeds the WACC discount method |
| `ResilientHttp` + integration scaffolds (incl. unfilled: Employment Hero/Cin7/Tradify not yet scaffolded) | `app/Services/Integration` | New integrations (WO-113) follow the established pattern |
| `AiClient` + `FakeAiClient` + `PromptEnvelope`/`AiResponse` | `app/Services/Ai` | All Phase 4 AI; voice assistant + API responses route through it |
| `Voice/*` (Whisper contract + fake + live) | `app/Services/Voice` | Voice assistant (WO-114) builds on the transcription layer |
| RBAC (Spatie, 9 roles) + `EnsureRole`/`EnsurePermission` + `EnforceClientScope` | WO-07/02 | Advisor API scoping (WO-112); multi-advisor team management (WO-120) |
| Immutable `audit_events` + `AuditWriter` | WO-03 | Every API call, learning approval, crypto rotation, community post audited |
| Consent ledger (`consents`) | model | Community/peer/benchmarking opt-in consent (WO-110/111) |

**Stack additions for Phase 4** (via their WO): liboqs PHP binding/FFI (PQC); HSM SDK (CloudHSM/Azure); a mobile framework (React Native or PWA-to-native — decide in WO-115); iOS App Intents / Siri Shortcuts + Android App Actions (voice — **launch shortcuts only**, not the sunset Conversational Actions); Laravel Sanctum for the first-party mobile API + token issuance/throttling for the external advisor API.

---

## 4. New database schema (Phase 4)

Same conventions: `uuid` PKs via `gen_random_uuid()`, `jsonb`, `client_id` + RLS on client-scoped tables, audit via `AuditWriter`. Population-scale tables are **aggregate/anonymised** and carry no raw client identifiers.

### 4.1 Active learning (WO-102…107)
- `learning_layer_state` — `layer_id` (1–32 per spec §23), `active` (bool), `last_run_at`, `next_due_at`, `min_sample`, `config` (jsonb) — turns each layer on and records its cadence; the layer *writes candidates to `learning_updates`*, never applies them.
- `dd_outcome_records` — `dd_engagement_id`, `recorded_price`, `actual_outcome` (jsonb), `recorded_at` — feeds DD valuation-accuracy learning (WO-104).
- `conversion_outcomes` — `entrepreneur_profile_id` (or `client_id`), `plan_assessment_id`, `outcome_signal` (jsonb — long-term business-success indicators), `observed_at` — feeds conversion-outcome learning (WO-106).
- `rating_validity_tests` — `rating_framework_id`, `period`, `correlation` (jsonb — predicted score vs realised outcome), `tested_at` — semi-annual predictive-validity (WO-107).

### 4.2 Population intelligence (WO-108…111) — anonymised, k-anonymity enforced
- `industry_intelligence_signals` — `industry_code`, `signal_type`, `aggregate` (jsonb), `cohort_size`, `generated_at`, `suppressed` (bool) — cross-client patterns surfaced as **one advisor alert** (WO-108); suppressed when `cohort_size < config('privacy.min_cohort')`.
- `benchmark_aggregates` — `domain` (sme | entrepreneur), `industry_code`, `metric`, `distribution` (jsonb — percentile bands only), `cohort_size`, `quarter`, `suppressed` — the anonymous NZ-SME + entrepreneur benchmarking community (WO-110); **no per-entity values**.
- `peer_network_members` — `user_id`, `community` (sme | entrepreneur), `joined_at`, `consent_id` (→ `consents`), `status` (active, suspended) — opt-in, separate communities.
- `peer_posts` / `peer_post_moderation` — `peer_network_member_id` (pseudonymous handle), `body`, `posted_at`; moderation row: `status` (pending, approved, rejected), `moderated_by_user_id`, `reason` — **moderated** before visibility.
- `shared_intelligence_patterns` — `source_domain`, `target_domain`, `pattern` (jsonb, anonymised), `cohort_size`, `generated_at` — entrepreneur↔advisory anonymised pattern sharing (WO-109).

### 4.3 Platform reach (WO-112…116)
- `advisor_api_clients` — `advisor_user_id`, `name`, `scopes` (jsonb — read-only + the limited writes: meeting notes, actions only), `token_hash`, `approved_by_user_id` (super-admin), `status`, `last_used_at` — per-integration super-admin approval (spec §25).
- `advisor_api_audit` — (or reuse `audit_events`) every API call logged with scope + outcome.
- `nz_tool_connections` — `client_id`, `provider` (employment_hero, cin7, tradify, …), `status`, `token_envelope` (KeyEnvelope), `connected_by_user_id`, `last_synced_at` — WO-113 (mirrors `accounting_connections`).
- `voice_assistant_sessions` — `advisor_user_id`, `launch_source` (in_app, ios_app_intent, android_app_action), `intent` (note, action, status), `transcript` (captured + stored **in-app**), `occurred_at` — capture/transcription are in-app (WO-98 Whisper); the OS shortcut only deep-links a fixed intent, so no client content crosses the OS boundary (WO-114).
- `industry_wacc_data` — `industry_code`, `wacc`, `components` (jsonb), `source` (nzx, …), `quarter`, `fetched_at`, `superseded_at` — WO-116; feeds the `DiscountMethod::IndustryWacc` resolver (full automation of the Phase 2 manual feed).
- `device_registrations` (WO-115) — `user_id`, `platform` (ios, android), `push_token_envelope` (KeyEnvelope), `last_seen_at` — push/device registration for the mobile app. (Correction: the mobile app does **add** this table; it does **not** simply reuse existing tables — and it consumes a **first-party mobile API** built in WO-115, distinct from the WO-112 external advisor-integration API.)

### 4.4 Security hardening (WO-117…119)
- `key_envelopes_registry` (optional) — tracks `kid` → key metadata + `alg` version for rewrap/rotation; or rely on the envelope `kid` field.
- `crypto_rotations` — `rotation_id`, `from_alg`, `to_alg`, `started_at`, `completed_at`, `envelopes_rewrapped`, `status` — the PQC rewrap run (WO-117).
- `security_audits` — `period`, `auditor`, `scope`, `report_path`, `findings` (jsonb), `status` — annual third-party audit framework (WO-119).

### 4.5 Scaling (WO-120)
- `advisor_teams` — `name`, `lead_advisor_user_id`, `created_at`.
- `advisor_team_members` — `advisor_team_id`, `user_id`, `role` (lead, member), `joined_at`.
- **`client_team` extension** (the existing access model — `App\Models\ClientTeamMember`, table `client_team` from WO-14; there is **no** `client_assignments` table) — extend it so a team lead inherits access to the team's clients. This means extending the `RequestContext`/`EnforceClientScope` scope resolution (WO-02) and the `client_team` RLS policy to include team-derived membership — **not** a rewrite and **not** a new assignments table.

---

## 5. Configuration & feature flags (Phase 4 additions)

```
# Active learning
FEATURE_ACTIVE_LEARNING=false           # master switch; individual layers gated in learning_layer_state
LEARNING_REQUIRE_APPROVAL=true          # MUST stay true — no autonomous application (asserted by test)

# Population intelligence (privacy)
# Single central privacy floor. The Phase 3 BENCHMARK_MIN_COHORT (config/entrepreneurs.php)
# is aliased to this so the two cannot drift: config('privacy.min_cohort') is the one source,
# defaulting from BENCHMARK_MIN_COHORT for back-compat.
BENCHMARK_MIN_COHORT=5                   # existing Phase 3 env; now also the platform-wide privacy floor
FEATURE_CROSS_CLIENT_INTELLIGENCE=false
FEATURE_BENCHMARK_COMMUNITY=false
FEATURE_PEER_NETWORK=false
PEER_NETWORK_MODERATION=manual          # posts require approval before visibility

# Platform reach
FEATURE_ADVISOR_API=false
FEATURE_VOICE_ASSISTANT=false
FEATURE_MOBILE=false
FEATURE_EMPLOYMENT_HERO_LIVE=false
FEATURE_CIN7_LIVE=false
FEATURE_TRADIFY_LIVE=false
FEATURE_INDUSTRY_WACC_LIVE=false

# Security (PQC/HSM)
FEATURE_PQC=false                       # KeyEnvelope writes v2 (ML-KEM-1024 + AES-256-GCM) when true; reads both always
HSM_DRIVER=                             # cloudhsm | azure_dedicated_hsm (empty = software keys, dev only)
```

`LEARNING_REQUIRE_APPROVAL=true` is a hard invariant. `privacy.min_cohort` governs every anonymised output. PQC reads are always dual-version (v1 + v2) so historical envelopes stay decryptable; `FEATURE_PQC` only controls which version *new* writes use.

---

## 6. Cross-cutting dependency map

```
Active Learning track (governed — feeds the WO-93 queue):
  WO-102 Activate 32 layers ─┬─ WO-103 Bias auto-calibration
                             ├─ WO-104 DD pattern + valuation-accuracy learning
                             ├─ WO-105 CohortGuard + industry plan-quality benchmarks
                             ├─ WO-106 Conversion-outcome learning
                             └─ WO-107 Rating predictive-validity testing

Population Intelligence track (privacy-critical, k-anonymity + moderation):
  WO-105(!) ─> WO-108 Cross-client competitive intelligence   [!] reuses CohortGuard built in WO-105
  WO-108 ─> WO-109 Shared intelligence layer (entrepreneur <-> advisory)
  WO-108 ─> WO-110 Anonymous NZ-SME + entrepreneur benchmarking community ─> WO-111 Anonymous peer network (moderated)
  ⚠️ CROSS-TRACK: WO-108–111 reuse CohortGuard (App\Services\Privacy\CohortGuard) built in WO-105.
     Implement WO-105 before WO-108.

Platform Reach track:
  WO-112 External advisor-integration API (read-only + limited write, super-admin approval)  [NOT the mobile API]
  WO-113 NZ business-tool integrations (Employment Hero/Cin7/Tradify)
  WO-115 Mobile app foundation + first-party mobile API (Sanctum, MFA/T&C/RLS) ─> WO-114 in-app voice + OS launch shortcuts
  WO-116 Industry-WACC full automation ─> feeds Phase 2 DiscountMethod::IndustryWacc

Security Hardening track (needs NZ-qualified review):
  WO-117 PQC envelope swap-in (KeyEnvelope v2) ─> WO-118 HSM key management ─> WO-119 Annual audit framework

Scaling track:
  WO-120 Multi-advisor scaling + team management (extends RBAC + RLS scope)
```

Tracks are largely independent and parallelisable. **WO-102 must precede 103–107** (it activates the engine they extend). **WO-117 must precede 118** (PQC before HSM-backing the PQC keys).

---

## 7. Cross-cutting foundations

### 7.1 Governed active learning (WO-102)
"Activate all 32 layers" means flip `learning_layer_state.active` per layer and let each layer run on its spec §23 cadence, **producing `learning_updates` candidates**. It does **not** mean any layer applies changes. The WO-93 `ApprovalFlow` remains the only path to a behaviour change; WO-94 `Rollback` remains the undo. A guard test asserts: with `FEATURE_ACTIVE_LEARNING=true` and every layer active, no scoring weight, prompt, rating descriptor, or framework value changes without an approved+implemented `learning_update`. Detected bias (WO-103) enters the queue; it is never auto-corrected (spec §3.2).

### 7.2 k-anonymity + moderation (`CohortGuard` built in WO-105; reused by WO-108…111)
Every population-scale output passes a single `CohortGuard` (`App\Services\Privacy\CohortGuard`, **built in WO-105** by generalising the Phase 3 `Benchmarking` min-cohort logic; WO-108–111 reuse it and depend on WO-105): aggregate only, suppress when `cohort_size < min_cohort`, never emit min/max or any value that could reverse-identify a member. The privacy floor is the **single central config `privacy.min_cohort`** (see §5) — the Phase 3 `benchmark_min_cohort` is aliased to it so floors cannot drift. Peer/community membership is opt-in (`consents`), pseudonymous, and **moderated** (`PEER_NETWORK_MODERATION=manual`) before any post is visible. The shared-intelligence layer (WO-109) shares only anonymised patterns, never records. Privacy counsel signs off before go-live (P4-R2).

### 7.3 PQC envelope swap-in (WO-117) — the SD-01 closure
`KeyEnvelope` already dispatches by `{v, alg}`. WO-117 adds the `v2` path: **ML-KEM-1024 (FIPS 203, derived from CRYSTALS-Kyber)** KEM wrapping an AES-256-GCM content key, with **ML-DSA (FIPS 204, derived from CRYSTALS-Dilithium)** signatures where envelopes are signed. (Spec V2.4 names Kyber/Dilithium; use the standardised FIPS names ML-KEM / ML-DSA in code and docs, noting the derivation.) `decrypt()` gains a `v2` branch (v1 AES envelopes stay readable forever); `encrypt()` writes v2 when `FEATURE_PQC=true`. A `php artisan envelopes:rewrap` command (idempotent, audited, recorded in `crypto_rotations`) streams existing v1 envelopes → v2. `KeyEnvelope::CURRENT_VERSION` flips to 2 at cutover. **NZ-qualified crypto review required before production** (spec §27).

### 7.4 HSM key management (WO-118) — the SD-02 closure
Keys move from env/software to an HSM (CloudHSM or Azure Dedicated HSM); the key material never enters application memory (spec §4). `KeyEnvelope` calls the HSM for wrap/unwrap of content keys. Scheduled rotation via `crypto_rotations`. Dev keeps software keys (`HSM_DRIVER=` empty); production must set a driver. Update `docs/architecture/security-decisions.md` to mark SD-01/SD-02 **closed**.

### 7.5 Advisor API security (WO-112)
Read-only by default; the only writes permitted are meeting notes + actions (spec §25). Each integration is a per-advisor `advisor_api_clients` row requiring **super-admin approval**, with explicit scopes and a hashed token. Every call is RLS-scoped to the advisor's clients and audited. No bulk export. **Inbound rate-limiting uses Laravel's API throttling** (`RateLimiter` / `throttle` middleware per API client/token) — **not** `ResilientHttp`, which is for *outbound* third-party calls.

---

## 8. Work Orders (Phase 4)

> Format: **ID** · **Title** · spec refs · goal · depends-on · key files · acceptance · tests · out-of-scope.

### Active Learning track

#### WO-102 — Activate the 32-layer learning engine (governed)
**Spec refs:** §11; §23
**Goal:** Flip each of the 32 layers (spec §23.1) to active on its cadence via `learning_layer_state`; each layer detects + proposes, writing `learning_updates` candidates only. Surface activity on the WO-95 monitoring dashboard.
**Depends on:** Phase 3 WO-93/94/95 (queue + rollback + cadence), Phase 2 WO-32/33.
**Key files:** `learning_layer_state` table, `app/Services/Learning/ActiveLayerEngine.php`, layer adapters, dashboard.
**Acceptance:** all 32 layers run on cadence and emit candidates; **a guard test proves no behaviour changes without an approved+implemented `learning_update`** (`LEARNING_REQUIRE_APPROVAL=true`); master `FEATURE_ACTIVE_LEARNING` gates it.
**Tests:** per-layer candidate emission; **no-autonomous-application guard**; cadence scheduling; dashboard surfacing.
**Out of scope:** the specific advanced layers (WO-103…107).

#### WO-103 — Bias detection automated calibration
**Spec refs:** §3.2; §11
**Goal:** From accumulated feedback (`analysis_feedback`, advisor adjustments), the bias monitor proposes calibration adjustments — **as governed candidates**; detected systematic bias is queued, never auto-corrected.
**Depends on:** WO-102, Phase 2 WO-33.
**Key files:** `app/Services/Ai/Integrity/BiasCalibration.php`.
**Acceptance:** seeded systematic skew produces a calibration candidate + alert; nothing auto-applies; super-admin notified.
**Tests:** skew→candidate; no auto-correct; alert routing.
**Out of scope:** none.

#### WO-104 — DD finding-pattern + valuation-accuracy learning
**Spec refs:** §23 (DD layers)
**Goal:** Learn DD finding patterns (after each DD engagement) and valuation accuracy (after `dd_outcome_records` capture an acquisition price); propose checklist/valuation refinements as governed candidates.
**Depends on:** WO-102, Phase 3 DD (WO-77/78/80/81).
**Key files:** `dd_outcome_records` table, `app/Services/Learning/Layers/DdLearning.php`.
**Acceptance:** recorded acquisition price feeds a valuation-accuracy candidate; finding patterns proposed; governed-only.
**Tests:** outcome capture; candidate emission; governed-only.
**Out of scope:** none.

#### WO-105 — `CohortGuard` privacy primitive + industry-specific plan-quality benchmarks
**Spec refs:** §17.7; §23; §11
**Goal:** **Build the reusable `CohortGuard` here** (generalising the Phase 3 `Benchmarking` min-cohort logic into a shared service) — it is the privacy primitive that WO-108–111 reuse. Then add per-industry entrepreneur plan-quality benchmarks (min industry cohort enforced via `CohortGuard`); feed entrepreneur guidance/benchmarking. **`CohortGuard` is created in this WO** (first consumer) and owned as a §7.2 cross-cutting foundation.
**Depends on:** WO-102, Phase 3 WO-87/88/91 (and the Phase 3 `Benchmarking` min-cohort it generalises).
**Key files:** **`app/Services/Privacy/CohortGuard.php`** (new — the shared primitive), `app/Services/Learning/Layers/PlanQualityBenchmarks.php`.
**Acceptance:** `CohortGuard` suppresses below `privacy.min_cohort`, returns aggregate-only, and cannot reverse-identify; industry benchmarks computed only at/above `privacy.min_cohort`, suppressed otherwise, aggregate-only.
**Tests:** `CohortGuard` suppression at floor−1 vs floor + no-reidentification; per-industry computation; aggregate-only.
**Out of scope:** the population-track consumers (WO-108–111 reuse `CohortGuard`).

> ⚠️ **Cross-track note:** `CohortGuard` is built here (WO-105, Active-Learning track) because it is the first consumer; the Population-Intelligence track (WO-108–111) **reuses** it and therefore depends on WO-105 for it. Implement WO-105 before WO-108–111.

#### WO-106 — Conversion-outcome learning
**Spec refs:** §23 (conversion outcome — long-term signal)
**Goal:** Track plan quality → real business success over time (`conversion_outcomes`); long-running signal feeding rating predictive validity + guidance, as governed candidates.
**Depends on:** WO-102, WO-105.
**Key files:** `conversion_outcomes` table, `app/Services/Learning/Layers/ConversionOutcomeLearning.php`.
**Acceptance:** outcome signals recorded; long-term correlation proposed as candidate; governed-only; heightened-integrity (honest signals).
**Tests:** outcome capture; candidate emission; governed-only.
**Out of scope:** none.

#### WO-107 — Rating predictive-validity testing
**Spec refs:** §17.4; §23 (semi-annual)
**Goal:** Semi-annual correlation of predicted rating scores vs realised outcomes (`rating_validity_tests`); propose framework calibration as governed candidates.
**Depends on:** WO-106.
**Key files:** `rating_validity_tests` table, semi-annual scheduled command.
**Acceptance:** semi-annual run computes correlation; calibration proposed as candidate; governed-only.
**Tests:** correlation computation; schedule; governed-only.
**Out of scope:** none.

### Population Intelligence track

#### WO-108 — Cross-client competitive intelligence
**Spec refs:** §11 (Cross-Client Intelligence — Phase 4)
**Goal:** Detect patterns affecting multiple clients in the same industry; surface as **one** advisor alert. Anonymised aggregates only; reuses the `CohortGuard` built in WO-105 for suppression.
**Depends on:** **WO-105 (for `CohortGuard`)**; Phase 2 analysis findings.
**Key files:** `industry_intelligence_signals` table, `app/Services/Intelligence/CrossClient.php` (consumes `App\Services\Privacy\CohortGuard`).
**Acceptance:** multi-client industry pattern → single advisor alert; suppressed below `privacy.min_cohort`; no client identifiable.
**Tests:** pattern detection; single-alert dedupe; cohort suppression; no-reidentification.
**Out of scope:** community/peer (WO-110/111).

#### WO-109 — Shared intelligence layer (entrepreneur ↔ advisory)
**Spec refs:** §11 (Shared Intelligence Layer — Phase 4)
**Goal:** Entrepreneur outcomes feed advisory benchmarks and vice versa — **anonymised patterns only**, never records.
**Depends on:** WO-108.
**Key files:** `shared_intelligence_patterns` table, `app/Services/Intelligence/SharedLayer.php`.
**Acceptance:** patterns flow both directions, anonymised + cohort-gated; no record-level data crosses.
**Tests:** bidirectional pattern flow; anonymisation; cohort gate.
**Out of scope:** none.

#### WO-110 — Anonymous NZ-SME + entrepreneur benchmarking community
**Spec refs:** §11 (Anonymous Benchmarking — Phase 4); Privacy Act 2020
**Goal:** Opt-in anonymous benchmarking community; aggregate percentile bands only; min cohort enforced; SME and entrepreneur domains separate.
**Depends on:** WO-108, `consents`, `CohortGuard`.
**Key files:** `benchmark_aggregates` table, `peer_network_members` (membership), `app/Services/Intelligence/BenchmarkCommunity.php`.
**Acceptance:** opt-in consent required; aggregate-only; suppressed below `privacy.min_cohort`; no per-entity values; privacy-counsel sign-off recorded.
**Tests:** consent gate; aggregate-only; cohort suppression; no-reidentification.
**Out of scope:** peer posting (WO-111).

#### WO-111 — Anonymous peer network (moderated)
**Spec refs:** §11/§26 (Anonymous peer network — Phase 4)
**Goal:** Separate, moderated SME and entrepreneur peer communities; pseudonymous; posts moderated before visibility.
**Depends on:** WO-110.
**Key files:** `peer_posts` / `peer_post_moderation` tables, `app/Services/Intelligence/PeerNetwork.php`, moderation UI.
**Acceptance:** opt-in; pseudonymous; **every post moderated before visible**; separate communities; report/suspend flow.
**Tests:** moderation gate; pseudonymity; community separation; suspend.
**Out of scope:** none.

### Platform Reach track

#### WO-112 — Advisor API layer
**Spec refs:** §25 (Advisor API Layer — Phase 4)
**Goal:** Read-only API + limited writes (meeting notes, actions only); per-integration super-admin approval; scoped hashed tokens; RLS-scoped; rate-limited; fully audited.
**Depends on:** WO-07 (RBAC), WO-02 (RLS), WO-05 (resilience/rate-limit).
**Key files:** `advisor_api_clients` table, API controllers, token issuance, scope middleware.
**Acceptance:** super-admin approves each client; reads scoped to the advisor's clients; only the two write types allowed; every call audited; tokens hashed.
**Tests:** approval gate; scope enforcement; write-allowlist; audit; token hashing.
**Out of scope:** public/partner API (not in V2.4).

#### WO-113 — NZ business-tool integrations
**Spec refs:** §25 (Employment Hero, Cin7, Tradify, others — Phase 4)
**Goal:** Scaffold + fill Employment Hero / Cin7 / Tradify clients following the established integration pattern (contract + stub + live + `ResilientHttp` + feature flag); tokens in `KeyEnvelope`.
**Depends on:** WO-05; the integration pattern.
**Key files:** `app/Services/Integration/{EmploymentHero,Cin7,Tradify}/*`, `nz_tool_connections` table.
**Acceptance:** each integration has interface + stub (fixture-tested) + live (flag-gated); connections store encrypted tokens; resilience + health applied.
**Tests:** contract tests (live + stub); connection + token encryption; resilience fallback.
**Out of scope:** the analysis that consumes the data (incremental on existing modules).

#### WO-114 — Voice capture (in-app) + OS launch shortcuts
**Spec refs:** §21 (Voice Assistant — Phase 4)
**Goal:** Hands-free advisor use — notes, action capture, client-status queries — with **all voice capture and transcription happening inside the FSA app** (reusing the WO-98 Whisper layer + `AiClient`). OS assistant integration is **launch-only**: iOS **App Intents / Siri Shortcuts** and Android **App Actions / Shortcuts** that deep-link into the in-app voice screen for a fixed intent (e.g. "new meeting note"). **No client content and no free dictation is ever handed to Siri or Google** — the OS payload is only a static intent name, so there is nothing to redact at the OS boundary. (Google's *Conversational Actions* were sunset 2023-06-13; this plan uses App Actions/Shortcuts, the current Android path — **not** Conversational Actions. Targeting an OS assistant beyond launch shortcuts is out of scope; if App Actions prove infeasible, Android falls back to an in-app voice button with no assistant integration.)
**Depends on:** Phase 3 WO-98 (in-app voice notes + Whisper), `AiClient`; Phase 4 WO-115 (mobile shell hosts the capture screen).
**Key files:** `voice_assistant_sessions` table, `app/Services/Voice/Assistant.php` (in-app intent handling), iOS App Intents + Android App Actions definitions (launch shortcuts only).
**Acceptance:** the three intents (note, action, status) are handled **in-app**; the OS shortcut payload contains only a fixed intent identifier (asserted — no client data, no transcript leaves the app to the OS assistant); capture/transcription run in-app via Whisper; sessions audited.
**Tests:** in-app intent handling; **OS-shortcut payload is intent-only (no client content / no PII)**; transcription stays in-app; audit. (The "no PII to third party" guarantee is now structurally testable because nothing client-specific crosses the OS boundary.)
**Out of scope:** Conversational Actions / any assistant that would receive spoken client content (sunset / privacy-incompatible); full conversational voice UI (the three intents only).

#### WO-115 — Mobile app foundation (iOS + Android) + first-party mobile API
**Spec refs:** §26 (Mobile app foundation — Phase 4)
**Goal:** Mobile foundation for advisor + client. **There is no first-party mobile API today** — `routes/api.php` currently holds only the DD guest-upload + webhook endpoints, and the WO-112 advisor API is an *external integration* API (super-admin-approved client tokens, read-only + two writes). So this WO **builds a first-party mobile API**: a versioned `routes/api.php` namespace authenticated with **Laravel Sanctum** device tokens (or first-party OAuth), honouring the **MFA + T&C gates** and **RLS client scope**, exposing the advisor/client portal data the app needs. Secure device storage; push registration; framework decision (React Native vs PWA-to-native) in an ADR.
**Depends on:** Phase 1 auth/MFA/T&C + WO-02 RLS scope; **not** WO-112 (that API is for external integrations, not the first-party app).
**Key files:** first-party mobile API routes + controllers + Sanctum auth, mobile app skeleton, `device_registrations` table/model, push integration, `docs/architecture/mobile.md` (framework + first-party-API ADR).
**Acceptance:** advisor + client authenticate over the first-party mobile API with MFA + T&C enforced; reads are RLS-scoped; core data viewable on device; encrypted local storage; push registration works.
**Tests:** mobile-API auth with MFA + T&C gates; RLS scope on mobile-API reads; device-registration; encrypted storage.
**Out of scope:** full feature parity with web (foundation only); the external advisor-integration API (WO-112).

#### WO-116 — Industry-WACC full automation
**Spec refs:** §24 (Industry WACC); §12 (Method 2)
**Goal:** Automate the Phase 2 manual valuation-multiple/WACC feed — NZ Exchange + industry data into `industry_wacc_data`, quarterly, feeding `DiscountMethod::IndustryWacc`; material changes queued as governed candidates.
**Depends on:** Phase 2 WO-39/40; `ResilientHttp`.
**Key files:** `industry_wacc_data` table, live client, quarterly refresh command.
**Acceptance:** automated quarterly refresh; WACC discount method reads it; material change → governed candidate; fixtures back tests.
**Tests:** refresh; discount-method consumption; candidate emission.
**Out of scope:** none.

### Security Hardening track

#### WO-117 — PQC envelope swap-in (ML-KEM / ML-DSA) — closes SD-01
**Spec refs:** §4; §27; Appendix B (NIST PQC — FIPS 203 ML-KEM, FIPS 204 ML-DSA); `docs/architecture/key-envelope.md`
**Goal:** Add the `v2` PQC path to `KeyEnvelope` (**ML-KEM-1024** KEM + AES-256-GCM; **ML-DSA** signatures — FIPS 203/204, derived from Kyber/Dilithium), the dual-version read, the `envelopes:rewrap` command (`crypto_rotations`), and the `FEATURE_PQC` write switch. **NZ-qualified crypto review before production.**
**Depends on:** WO-02 (`KeyEnvelope` seam).
**Key files:** `KeyEnvelope` v2 path, `app/Services/Storage/Pqc/*`, `crypto_rotations` table, `envelopes:rewrap` command, updated `docs/architecture/key-envelope.md` + `security-decisions.md` (SD-01 closing).
**Acceptance:** v2 envelopes round-trip; v1 still decrypts; rewrap is idempotent + audited; `CURRENT_VERSION` flips at cutover; no call sites changed.
**Tests:** v2 round-trip; v1 back-compat; rewrap idempotency; version dispatch.
**Out of scope:** HSM backing (WO-118).

#### WO-118 — HSM key management — closes SD-02
**Spec refs:** §4; §27
**Goal:** Move key wrap/unwrap to an HSM (CloudHSM / Azure Dedicated HSM); key material never in app memory; scheduled rotation. Dev keeps software keys.
**Depends on:** WO-117.
**Key files:** `app/Services/Storage/Hsm/*`, `KeyEnvelope` HSM hook, rotation schedule, `security-decisions.md` (SD-02 closing).
**Acceptance:** with `HSM_DRIVER` set, wrap/unwrap go through the HSM; keys never materialise in app memory; rotation works; dev fallback intact.
**Tests:** HSM wrap/unwrap (mock HSM); rotation; no-key-in-memory assertion; dev fallback.
**Out of scope:** none.

#### WO-119 — Annual third-party security & legal audit framework
**Spec refs:** §26 (annual audit — Phase 4); §27
**Goal:** A repeatable framework + records (`security_audits`) for the annual third-party security and legal audit; checklist, evidence collection, findings tracking, remediation.
**Depends on:** WO-117/118 (crypto to audit).
**Key files:** `security_audits` table, audit-prep command/checklist, `docs/architecture/security-audit.md`.
**Acceptance:** an audit cycle can be recorded with scope/findings/remediation; evidence assembled; findings tracked to closure.
**Tests:** audit record lifecycle; findings tracking.
**Out of scope:** the audit itself (external).

### Scaling track

#### WO-120 — Multi-advisor scaling + team management
**Spec refs:** §26 (Multi-advisor scaling — Phase 4)
**Goal:** Teams (`advisor_teams`), team membership, team-lead visibility across the team's clients by **extending the existing `client_team` access model** (`ClientTeamMember` + the `client_team` RLS policy + `RequestContext`/`EnforceClientScope`) — not a rewrite, not a new assignments table; capacity/load views across the team.
**Depends on:** WO-07 (RBAC), WO-02 (`RequestContext`/RLS), WO-14 (`client_team`/`ClientTeamMember`).
**Key files:** `advisor_teams` / `advisor_team_members` tables, `client_team` RLS-policy + scope-resolver extension (team-derived membership), team management UI.
**Acceptance:** a team lead sees the team's clients (RLS-enforced); members see their own; reassignment audited; capacity view aggregates the team.
**Tests:** team RLS scope; lead-vs-member visibility; reassignment audit.
**Out of scope:** cross-practice/white-label (not in V2.4).

---

## 9. Acceptance & Definition of Done (per WO)

Same bar as Phases 1–3, plus:

1. New tables documented in `docs/architecture/schema.md`; new client-scoped tables have an RLS policy **and** RLS test; population-scale tables prove anonymisation + cohort suppression.
2. `composer test`, Pint, ESLint, Prettier, `tsc --noEmit` all pass.
3. No Phase-beyond-V2.4 feature.
4. **No learning layer applies a change without an approved+implemented `learning_update`** (the §7.1 guard test).
5. **Every anonymised output passes `CohortGuard`** (suppress below `privacy.min_cohort`; aggregate-only; no reidentification).
6. **PQC: v1 envelopes remain decryptable forever; no call site changed; rewrap idempotent + audited.**
7. **HSM: key material never enters app memory in production.**
8. **Advisor API: read-only + the two permitted writes only; per-integration super-admin approval; RLS-scoped; audited.**
9. Architecture doc added/updated; `security-decisions.md` marks SD-01/SD-02 closed at WO-117/118.

## 10. Test strategy (Phase 4 additions)

- **No-autonomous-learning guard** — with all layers active, assert no weight/prompt/descriptor/framework value changes without an approved+implemented `learning_update`.
- **CohortGuard tests** — every population-scale feature suppresses below `privacy.min_cohort`, emits aggregate-only, and cannot reverse-identify (test with cohort = floor−1 and floor).
- **Bias-calibration governance test** — detected bias queues a candidate, never auto-corrects.
- **PQC tests** — v2 round-trip; v1 back-compat; rewrap idempotency; dual-version dispatch.
- **HSM tests** — wrap/unwrap via mock HSM; no-key-in-memory assertion; dev software fallback.
- **Advisor API tests** — approval gate, scope enforcement, write-allowlist (only meeting notes + actions), audit, token hashing.
- **Moderation/consent tests** — peer posts hidden until approved; community membership requires consent.
- **RLS tests** — every new client-scoped table incl. team-scoped access (WO-120).
- **`FakeAiClient` everywhere; live client never bound in `testing`.**

## 11. After Phase 4 — V2.4 complete

Phase 4 completes spec V2.4. Anything further (V2.5+) — new markets, white-label, partner API, additional engagement types, deeper AI autonomy — is a separate specification and plan, not covered here. At Phase 4 sign-off, update `IMPLEMENTATION.md` to mark **all four phases complete** and record the final verified baseline.

## 12. Open risks & decisions to revisit (Phase 4)

| # | Risk / open item | Owner action | Trigger |
|---|---|---|---|
| P4-R1 | **PQC/HSM need NZ-qualified crypto review before production** (spec §27); liboqs build + HSM provisioning are specialist work | Engage reviewer; provision HSM | Before WO-117/118 production |
| P4-R2 | **Privacy counsel sign-off** for cross-client / community / peer features (Privacy Act 2020) | Engage privacy counsel; define moderation policy | Before WO-108/110/111 go-live |
| P4-R3 | Autonomous-learning risk — the cardinal invariant is "no change without approval"; must be continuously guard-tested | Keep `LEARNING_REQUIRE_APPROVAL=true`; the §7.1 guard test in CI | Ongoing |
| P4-R4 | Mobile framework decision (React Native vs PWA-to-native) affects effort + maintenance | Decide + ADR in WO-115 | WO-115 start |
| P4-R5 | Apple Developer + Google Play accounts; Siri/Google Assistant developer setup | Owner provision | Before WO-114/115 |
| P4-R6 | Employment Hero / Cin7 / Tradify + NZX/WACC data access (agreements, rate limits) | Owner arrange | Before WO-113/116 live |
| P4-R7 | Annual security/legal audit firm engagement | Owner engage | Before WO-119 first cycle |
| P4-R8 | Voice assistant PII risk — no client PII to third-party assistants; redaction-before-egress is mandatory | Enforce + test | Before WO-114 go-live |
| P4-R9 | Carryover: lawyer-reviewed T&C / panel agreements / DD disclaimer; brand kit; live AI + payment + NZ-API credentials | Owner supply | Before respective go-lives |

## 13. Phase 4 feature → WO mapping (traceability)

| Spec §26 Phase 4 item | WO(s) |
|---|---|
| Full continuous learning engine (32 layers active) | WO-102 |
| Bias detection automated calibration | WO-103 |
| DD finding-pattern + valuation-accuracy learning | WO-104 |
| Industry-specific plan-quality benchmarks | WO-105 |
| Conversion-outcome learning | WO-106 |
| Rating predictive-validity testing | WO-107 |
| Cross-client competitive intelligence | WO-108 |
| Shared intelligence layer | WO-109 |
| Anonymous NZ-SME benchmarking community | WO-110 |
| Anonymous peer network | WO-111 |
| Advisor API layer | WO-112 |
| NZ business-tool integrations | WO-113 |
| Voice assistant integration | WO-114 |
| Mobile app foundation | WO-115 |
| Industry WACC data feed full automation | WO-116 |
| PQC cryptography (ML-KEM / ML-DSA, ex-Kyber/Dilithium) | WO-117 |
| HSM key management | WO-118 |
| Annual third-party security & legal audit framework | WO-119 |
| Multi-advisor scaling + team management | WO-120 |

---

*End of Phase 4 plan — the final phase of spec V2.4. `PLAN.md`, `PLAN-PHASE2.md`, and `PLAN-PHASE3.md` are intentionally left unchanged. On Phase 4 acceptance, the platform realises the complete Future Shift Advisory V2.4 specification.*
