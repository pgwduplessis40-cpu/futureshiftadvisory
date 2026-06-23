# Entrepreneur Module — Spec-vs-Build Gap Analysis

**Compares:** `FSA_Entrepreneurial_Platform_TechSpec_v1.docx` (v1.0, May 2026 — the standalone entrepreneur
platform spec) **→** the entrepreneur module as actually built **inside the Laravel FSA monolith**.
**Why:** a review found gamification missing; this checks whether other TechSpec-v1 features were also
dropped in the Next.js-platform → Laravel-module re-architecture.
**Method:** each TechSpec-v1 capability checked against the repo (`app/Services/Entrepreneurs/*`, models,
controllers, config, grep). Status is one of: ✅ Present · ⚠️ Partial/Different · ❌ Absent · ◻️ Intentionally
out of Phase-1 scope.
**Date:** 2026-06-23. **Confidence:** high for ✅/❌ items verified by code; medium where noted.

> **Read the framing first.** TechSpec v1 describes a *standalone Next.js/Prisma/Supabase/Vercel app*; the
> module was instead built into the *Laravel/Inertia/Postgres monolith*. So many differences are **deliberate
> re-architecture, not defects** — same capability, different implementation. The rows that matter are the
> **❌ Absent** ones: genuine capabilities the build does not have.

---

## 1. Summary — what's actually missing

**Genuinely dropped capabilities (❌):**
1. **Gamification** — advisor toggle, streaks, milestone celebrations, progress badges. *(Already has a
   remediation plan: [PLAN-ENTREPRENEUR-GAMIFICATION.md](PLAN-ENTREPRENEUR-GAMIFICATION.md).)*
2. **Investor-readiness score** — `investor_score_on` advisor toggle + `Assessment.investor_score/grade/
   feedback`. The sibling of gamification in the same spec section; same omission.
3. **Pre-flight check** — AI gap-check before submission (structured issue list / submission checklist).
4. **Financial guidance mode** — the plan-assistant's startup-cost builder, revenue-forecast builder,
   P&L/cash-flow/break-even, and advisory-rate injection *inside the entrepreneur wizard*.
5. **Business Model Canvas (BMC)** — pre-wizard ideation tool that pre-populates plan sections.
6. **Pitch-deck generator** — AI slide outline from the completed plan (PPTX). *Infra exists (see ⚠️).*

**Architecture-level difference worth a decision (⚠️ → arguably ❌):**
7. **Two-isolated-AI architecture** — the spec's #1 HARD RULE (separate plan-assistant vs scoring-engine
   API clients, separate keys, CI cross-contamination test). The build uses **one shared `AiClient`** for
   both guidance and scoring. Logical separation may exist via prompts, but the **infrastructure-level
   isolation and its CI guard do not**.

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
| 6 | Plan assistant AI (guidance) | `Services\Entrepreneurs\Guidance` via `AiClient` | ⚠️ present, not isolated |
| 7 | Scoring engine AI (evaluate vs matrix) | `Services\Entrepreneurs\Assessment` via `AiClient` | ⚠️ present, not isolated |
| 8 | **Two-AI isolation (separate keys + CI test)** | single shared `AiClient` | ❌ |
| 9 | **Pre-flight check before submission** | — | ❌ |
| 10 | Idea validation gate | `IdeaValidationService` + advisor gate | ✅ |
| 11 | **Gamification (toggle, streaks, badges, milestones)** | — | ❌ |
| 12 | **Investor-readiness score (`investor_score_on`)** | — | ❌ |
| 13 | **Streaks (`current_streak`, daily cron)** | — | ❌ |
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
| 27 | Admin analytics (completion, scores, abandonment) | dashboards + funnel analytics | ✅ (partial) |
| 28 | Multi-advisor readiness (`advisor_id` throughout) | `assigned_advisor_id` throughout | ✅ |
| 29 | **White-label (`tenant_id`)** | — | ◻️ not a Phase-1 goal |
| 30 | Living plan (cadence, divergence) | `Services\Entrepreneurs\LivingPlan` + `BusinessPlan.living_plan_*` | ✅ (monolith addition) |

---

## 3. Recommended priority to reinstate

Ordered by user-visible value vs. effort; all are net-new on the Laravel side:

1. **Gamification** — plan already written; advisor-controlled, plan-progress-driven, honest. *Ready to build.*
2. **Investor-readiness score** — small, advisor-controlled, reuses the assessment pipeline; natural pairing
   with gamification (same toggle surface). Recommend its own short plan.
3. **Pre-flight check** — high value, modest effort: an `AiClient` gap-check before submission, structured
   issues, surfaced as a pre-submit checklist. Honesty-friendly (flags weaknesses).
4. **Two-AI isolation** — *governance decision, not just a feature.* Decide whether the FSA AI-integrity
   model already satisfies the intent, or whether the entrepreneur scoring path must move to a separately
   keyed client with a CI cross-contamination test. **Flag to owner — it was the spec's #1 hard rule.**
5. **Pitch-deck generator** — infra (`PptxGenerator`) already exists; mostly wiring an entrepreneur path.
6. **Financial guidance builders** — larger; the wizard-embedded cost/revenue/forecast tooling.
7. **BMC tool** — pre-wizard ideation; larger, lower urgency.
8. *(Deferred by phase)* SMS/WhatsApp, white-label.

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
