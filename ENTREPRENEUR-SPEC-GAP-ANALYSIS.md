# Entrepreneur Module — Spec-vs-Build Gap Analysis

**Compares:** `FSA_Entrepreneurial_Platform_TechSpec_v1.docx` (v1.0, May 2026 — the standalone entrepreneur
platform spec) **→** the entrepreneur module as actually built **inside the Laravel FSA monolith**.
**Why:** a review found gamification missing; this checks whether other TechSpec-v1 features were also
dropped in the Next.js-platform → Laravel-module re-architecture.
**Method:** each TechSpec-v1 capability checked against the repo (`app/Services/Entrepreneurs/*`, models,
controllers, config, grep). **Each row carries exactly one status glyph** — ✅ Present · ⚠️ Partial/Different ·
❌ Absent · ◻️ Intentionally out of scope — optionally with a short parenthetical qualifier. A capability whose
parts differ in status is **split into separate rows** (e.g. 8a/8b) rather than given a mixed glyph.
**Date:** 2026-06-23. **Last reconciled against the repo:** 2026-06-23 (commit `7586ba37`). **Confidence:**
high for ✅/❌ items verified by code.

> **⚠️ Living document — current as of commit `7586ba37`.** Since the first cut of this analysis,
> **gamification (mostly) and the AI content-isolation CI guard have been built and committed** (`7586ba37`,
> "Implement entrepreneur gamification workflow"): migration `2026_06_23_010000_add_entrepreneur_gamification.php`,
> `EntrepreneurGamification` / `EntrepreneurMilestones` / `EntrepreneurStreak` services, advisor + portal
> routes, `EntrepreneurPromptRegistry`, and `GamificationTest` / `AiContentIsolationTest`. **One plan
> requirement is *not* shipped:** the **daily streak recompute/reset cron** — `EntrepreneurStreak::recompute()`
> exists but is only event/toggle-driven, and `bootstrap/app.php`'s scheduler has no gamification command, so
> a streak won't drop to 0 after a full lapsed day until the next event. So gamification is **⚠️ mostly built**
> (see items 11/13). Re-verify against HEAD before treating any ❌ as current.

> **Read the framing first.** TechSpec v1 describes a *standalone Next.js/Prisma/Supabase/Vercel app*; the
> module was instead built into the *Laravel/Inertia/Postgres monolith*. So many differences are **deliberate
> re-architecture, not defects** — same capability, different implementation. The rows that matter are the
> **❌ Absent** ones: genuine capabilities the build does not have.

---

## 1. Summary — what's actually missing

**Since built (was ❌ in the first cut) — commit `7586ba37`:**
- **Gamification — ⚠️ mostly built.** `entrepreneur_profiles.gamification_on` + advisor toggle route,
  `submitted_at`, `entrepreneur_milestone_awards` + `entrepreneur_streak_events` (append-only / set-once /
  RLS triggers), `EntrepreneurGamification` + `EntrepreneurMilestones` + `EntrepreneurStreak` services, portal
  payload, and `GamificationTest`. The schema/triggers/awarder match
  [PLAN-ENTREPRENEUR-GAMIFICATION.md](PLAN-ENTREPRENEUR-GAMIFICATION.md)'s hardening (true-transition
  `submitted_at` guard, allowlist award trigger, etc.). **Gap: the plan's *daily streak recompute/reset cron*
  is not wired** (no scheduler entry) — without it, a cached `current_streak` stays stale after a full lapsed
  day until the next event/toggle. **Verify + add the cron; don't rebuild the rest.**
- **AI content-isolation CI guard** — `AiContentIsolationTest` + `EntrepreneurPromptRegistry`, running in
  the PHPUnit gate (`tests.yml`). This is **Option B** from
  [PLAN-ENTREPRENEUR-AI-ISOLATION.md](PLAN-ENTREPRENEUR-AI-ISOLATION.md) — the content-firewall test, not the
  two-key infra split. The two-key split remains **deliberately not done (owner-approved)**, so it is **not a
  gap** — see item 7.

**Genuinely still missing (❌):**
1. **Investor-readiness score** — `investor_score_on` advisor toggle + `Assessment.investor_score/grade/
   feedback`. The sibling of gamification in the same spec section; **still absent**.
2. **Pre-flight check** — AI gap-check before submission (structured issue list / submission checklist).
3. **Financial guidance mode** — the plan-assistant's startup-cost builder, revenue-forecast builder,
   P&L/cash-flow/break-even, and advisory-rate injection *inside the entrepreneur wizard*.
4. **Business Model Canvas (BMC)** — pre-wizard ideation tool that pre-populates plan sections.
5. **Pitch-deck generator** — AI slide outline from the completed plan (PPTX). *Infra exists (see ⚠️).*

**Architecture decision (resolved):**
7. **Two-isolated-AI architecture** — the spec's #1 HARD RULE. **Resolved as Option B (owner-approved):** the
   **content-firewall + CI cross-contamination guard is now built** (`AiContentIsolationTest`); the separate
   plan-assistant/scoring-engine **API-key infrastructure split is intentionally *not* built**. No longer an
   open gap — it's a recorded decision.

**Intentionally out of current scope (◻️) — not defects:**
8. **SMS/WhatsApp (Twilio)** notifications — no config present; mobile/voice is later-phase in the monolith.
9. **White-label (`tenant_id`)** — not a Phase-1 goal of the monolith.

Everything else in the spec is **present** (often built differently) — see the table.

---

## 2. Full feature map

| # | TechSpec v1 capability | In the Laravel build | Status |
|---|---|---|---|
| 1 | Invite-only auth, no public registration | Fortify + invite layer, register route disabled | ✅ (diff stack) |
| 2 | RBAC — Entrepreneur / Advisor / Admin | 9 user types incl. entrepreneur / advisor / super_admin | ✅ (superset) |
| 3 | Row-Level Security | Postgres RLS (`fsa_*` helpers, `EnforceClientScope`) | ✅ |
| 4 | Dynamic section + scoring-matrix admin (versions, criteria, anchors) | `RatingFramework` + `RatingCriterion` + `RatingFrameworkManager` | ✅ (diff model) |
| 5 | Plan wizard — dynamic sections, status, auto-save | `PlanBuilder`, `PlanSection`, portal plan workspace | ✅ |
| 6 | Plan assistant AI (guidance) | `Services\Entrepreneurs\Guidance` via `AiClient`; content-firewall CI-guarded | ✅ (one key, firewall-tested) |
| 7 | Scoring engine AI (evaluate vs matrix) | `Services\Entrepreneurs\Assessment` via `AiClient`; content-firewall CI-guarded | ✅ (one key, firewall-tested) |
| 8a | Two-AI isolation — **content-firewall CI guard** | `AiContentIsolationTest` + `EntrepreneurPromptRegistry`, in the PHPUnit gate | ✅ |
| 8b | Two-AI isolation — **separate-key infra** (spec's exact two-key rule) | single `AiClient` binding (`AiServiceProvider`); the two-key split is **not** implemented | ◻️ owner-deferred (Option B) |
| 9 | **Pre-flight check before submission** | — | ❌ |
| 10 | Idea validation gate | `IdeaValidationService` + advisor gate | ✅ |
| 11 | **Gamification (toggle, badges, milestones)** | **Built** — `gamification_on` + awards/streak tables + triggers + services + routes + `GamificationTest` (`7586ba37`); **daily streak-reset cron not wired** | ⚠️ mostly built |
| 12 | **Investor-readiness score (`investor_score_on`)** | — | ❌ |
| 13 | **Streaks (`current_streak` + ledger)** | **Built** — `entrepreneur_streak_events` ledger + `current_streak`/`last_active_at` + event/toggle recompute; **no daily recompute cron** (`bootstrap/app.php` scheduler) | ⚠️ cron missing |
| 14 | **Financial guidance mode (cost/revenue/P&L/cash-flow/break-even builders)** | general PV/financial services exist; **not** the entrepreneur-wizard builders | ❌ (for the wizard) |
| 15 | Advisory-rate injection (market rate, no FSA branding) | monolith *is* FSA — concept differs (rate cards/reference data) | ⚠️ N/A by design |
| 16 | Assessment workspace — AI scores + advisor override | `Assessment` + advisor finalise (`EntrepreneurActionController`) | ✅ |
| 17 | Report generation (PDF) | Browsershot PDF via `ReportComposer` | ✅ (diff renderer) |
| 18 | Revision mode (unlock + improvement guidance) | `Services\Entrepreneurs\Revision` | ✅ |
| 19 | FSA migration pipeline (entrepreneur → FSA client) | `AdvisoryConversion` (in-monolith conversion, not cross-platform API) | ✅ (diff: in-app) |
| 20 | Advisor portfolio dashboard | advisor entrepreneurs index/show + capacity | ✅ |
| 21 | In-app messaging + private advisor notes | `EntrepreneurMessageController` + messaging | ✅ |
| 22 | Notifications — in-app + email | in-app (db channel) + email | ✅ |
| 23 | **Notifications — WhatsApp/SMS (Twilio)** | no config/channel | ◻️ later-phase |
| 24 | Re-engagement nudges (inactive 7/14/21d) | `SendReengagementReminders` cron | ✅ |
| 25 | **Business Model Canvas tool** | — | ❌ |
| 26 | **Pitch-deck generator (PPTX from plan)** | `PptxGenerator`/`OpenXmlPptxGenerator` exist but wired to **reports**, not entrepreneur pitch decks | ⚠️ infra only |
| 27 | Admin analytics (completion, scores, abandonment) | dashboards + funnel analytics (not the full TechSpec set) | ⚠️ partial |
| 28 | Multi-advisor readiness (`advisor_id` throughout) | `assigned_advisor_id` throughout | ✅ |
| 29 | **White-label (`tenant_id`)** | — | ◻️ not a Phase-1 goal |
| 30 | Living plan (cadence, divergence) | `Services\Entrepreneurs\LivingPlan` + `BusinessPlan.living_plan_*` | ✅ (monolith addition) |

---

## 3. Recommended priority to reinstate

Ordered by user-visible value vs. effort. **(Gamification and the two-AI content-firewall guard are
substantially built — `7586ba37`. The one outstanding gamification piece is below at #0.)**

0. **Daily streak recompute cron** — *small, finishes gamification.* Add a console command that calls
   `EntrepreneurStreak::recompute()` for active profiles (system context) + a `bootstrap/app.php` scheduler
   entry (e.g. `dailyAt`), so a streak drops to 0 after a full lapsed day per the plan. Currently recompute is
   only event/toggle-driven.
1. **Investor-readiness score** — small, advisor-controlled, reuses the assessment pipeline; natural pairing
   with the now-built gamification toggle surface. Recommend its own short plan. *(Highest-value net-new.)*
2. **Pre-flight check** — high value, modest effort: an `AiClient` gap-check before submission, structured
   issues, surfaced as a pre-submit checklist. Honesty-friendly (flags weaknesses).
3. **Pitch-deck generator** — infra (`PptxGenerator`) already exists; mostly wiring an entrepreneur path.
4. **Financial guidance builders** — larger; the wizard-embedded cost/revenue/forecast tooling.
5. **BMC tool** — pre-wizard ideation; larger, lower urgency.
6. *(Deferred by phase)* SMS/WhatsApp, white-label.

> **Verify, don't rebuild:** Gamification (items 11/13 — bar the #0 streak cron) and the AI content-isolation
> CI guard (item 8a, Option B). The separate-key infra split (8b) stays intentionally out of scope.

---

## 4. Caveats (honesty)

- This compares against **TechSpec v1**, which the monolith deliberately superseded for stack/architecture.
  Items 15, 19, 23, 29 are **intended divergences**, not regressions — don't "fix" them without confirming
  intent.
- ⚠️ items 14 and 26 are **medium-confidence**: general financial services and a PPTX generator exist in the
  repo, so a future builder might reuse them — they're "absent for the entrepreneur wizard," not "absent
  everywhere." Verify against intent before scoping.
- This is a **capability** map, not a line-by-line audit; before building any item, confirm scope against the
  current monolith spec (the V2.4 line), since some TechSpec-v1 features may have been intentionally cut.

---

*Companion to [PLAN-ENTREPRENEUR-GAMIFICATION.md](PLAN-ENTREPRENEUR-GAMIFICATION.md). Produced at owner
request ("gamification now + gap analysis next").*
