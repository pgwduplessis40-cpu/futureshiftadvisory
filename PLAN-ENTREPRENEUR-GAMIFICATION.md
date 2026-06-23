# Future Shift Advisory — Entrepreneur Gamification Plan

**Source:** owner request after an entrepreneur-module review found "no gamification field, toggle, or setting." **Correction (v1.1):** gamification *was* specified — in the standalone **`FSA_Entrepreneurial_Platform_TechSpec_v1.docx`** (v1.0, May 2026), which was never in this repo. It is absent from the repo only because the entrepreneur module was **re-architected** from the standalone Next.js platform that spec describes into a **module inside the Laravel monolith** (governed by the V2.4 monolith spec, which has no gamification) — so gamification (and its siblings `investor_score_on` + streaks) was dropped in that hand-off. This plan therefore **ports a previously-specified feature** into the Laravel build, aligned to the original spec's definition.
**Track scope:** add an **opt-in, advisor-controlled, plan-progress-driven** gamification layer to the **entrepreneur portal only**. It is a *display layer* over real, advisor-validated progress — it must not alter any assessment, score, valuation, readiness threshold, or AI output. No existing SME/DD/NPO/broker/coach/portal behaviour changes.
**Predecessors:** Phase 1–4 + NPO + entrepreneur module, green on `main`/`featureApp`. Builds on existing entrepreneur scaffolding: `EntrepreneurProfile`, `EntrepreneurStage`, `BusinessPlan` (phases/sections/status), `PlanAssessment` (rounds/grades), `AdvisoryReadinessSignal`, `EntrepreneurActionController`, the entrepreneur portal `Dashboard`, RLS helpers, `AuditWriter`, Spatie `Permission`, Wayfinder.
**Work orders:** **WO-G1 … WO-G5** (gamification namespace).
**Sequencing:** G1 (advisor toggle + schema: `entrepreneur_milestone_awards` + immutability trigger, `entrepreneur_streak_events`, profile columns) → G2 (progress service + milestone awarder + streak ledger service) → G3 (portal panel) → G4 (seen-state / "new" toasts) → G5 (advisor preview + polish). Each its own green commit on `featureApp`; `main` fast-forwards only when the suite is green (`-d memory_limit=2048M`).
**Plan version:** 1.8 — tech-spec anchor (v1.1) + seven code-grounded review passes (v1.2–v1.8).

> **v1.8 revision (review pass — assessment mutability, cascade vs append-only, real event).** Three fixes:
> (P1) **Finalised assessments aren't immutable in code** — `Assessment::adjustScore()` changes the blend and
> `finalise()` rewrites `finalised_at` every call (~lines 82/150). So the award now **snapshots**
> weighted-score/grade/`finalised_at` into `evidence_snapshot` and renders from that, keeping capstone/
> grade-up evidence truthful (§4.2, §5).
> (P2) **Cascade FK vs append-only trigger** was self-contradictory (a parent delete would cascade a DELETE
> the trigger refuses). Switched to **`restrictOnDelete`** on `entrepreneur_profile_id` (both tables) and a
> plain historical ref for `streak_events.plan_section_id` (§4.2, §4.3).
> (P2) **`entrepreneur.plan_section_saved` is only an audit string**, not a dispatched event — added a real
> **`EntrepreneurPlanSectionSaved` Laravel event** (from `PlanBuilder::saveSection`) driving streak + phase
> detection (§5).

> **v1.7 revision (review pass — command-specific RLS, TRUNCATE, distinct-day).** Five fixes:
> (P1) **Command-specific RLS** for awards + streak events — a single owner-scoped policy would let an
> entrepreneur INSERT their own award/streak row; now **SELECT** owner/advisor/system, **INSERT system-only**,
> award UPDATE only via the `seen_at` path, no user DELETE (§4.2, §4.3).
> (P2) **TRUNCATE blocked** — both triggers add `BEFORE TRUNCATE` (the `audit_events` pattern blocks it too;
> append-only was otherwise truncation-bypassable) (§4.2, §4.3).
> (P2) **Allowlist trigger** — the award trigger now allows only `seen_at`+`updated_at` to differ, so `id`
> (and any future column) is protected, not just a named blocklist (§4.2).
> (P2) **Streak over `DISTINCT active_day`** — two meaningful changes the same day count once; a row-based
> loop would inflate/break the run (§4.3, §5, §9 same-day test).
> (P3) **§2 enable-recompute** reworded to "from the ledger" (matching §5), not the cached `last_active_at`.

> **v1.6 revision (review pass — trigger/projection edge cases).** Four fixes:
> (P1) **Award trigger vs Eloquent timestamps** — a seen-write bumps `updated_at`, so the trigger protects a
> *specific column set* (`earned_at`/evidence/`milestone_key`/…) and **allows `seen_at` + `updated_at`** —
> not "only `seen_at`", which would reject every real seen-write (§4.2).
> (P1) **Streak ledger immutability** — `entrepreneur_streak_events` gets its **own append-only trigger**
> (reject UPDATE/DELETE), since `current_streak` projects from it and edits would rewrite history (§4.3).
> (P2) **Streak alive "today or yesterday"** — the run may end **yesterday** (not only today), so a
> still-valid streak isn't reset at midnight before the entrepreneur has acted; drops to 0 only after a full
> lapsed day (§4.3, §5, §9 morning-after test).
> (P2) **Reconcile picks earliest evidence** — for singletons with multiple qualifying rows, the awarder
> takes the **earliest** by source timestamp (not first/latest returned), so `earned_at` is the true first
> achievement (§4.2, §5).

> **v1.5 revision (review pass — award/streak durability hardening).** Five fixes:
> (P1) **Streak anti-gaming state** — added the `entrepreneur_streak_events` **per-section** ledger (§4.3);
> the prior "per-entrepreneur hash" was both un-stored and too coarse (saving an unchanged section B after a
> counted section A would falsely count). `current_streak` is now a recomputed projection of the ledger.
> (P1) **Award immutability** — RLS can't make `earned_at`/evidence immutable while leaving `seen_at`
> writable, so added a **DB trigger** mirroring `audit_events`' append-only trigger (§4.2).
> (P2a) **Capstone evidence** now cites the **finalised `plan_assessment`** (immutable), not the mutable
> `updateOrCreate`d readiness signal (§3.4, §4.2).
> (P2b) **Singleton uniqueness** — partial uniques so one-time milestones can't double-award when multiple
> evidence rows exist; only `grade_up` is repeatable (§4.2).
> (P2c) **Reconcile `earned_at`** comes from the **source timestamp** (`advisor_gate_passed_at`/
> `finalised_at`), not reconciliation time (§4.2, §5).

> **v1.4 revision (review pass — durable milestone evidence).** Four fixes:
> (P1) **`plan_submitted` was not durable** — `BusinessPlan.status` is overwritten (assessing → finalised →
> living-plan), so a status-derived badge would vanish. Shifted the model: milestones are **awarded once and
> persisted** (`entrepreneur_milestone_awards` with an immutable `earned_at`), the `plan_submitted` award
> written at the submit action (§3.4, §4.2, §5).
> (P2) **Phase badge evidence unstable** — `plan_phases` has no `completed_at` (only a churning `updated_at`
> re-saved by `refreshPhaseStatus`); the award captures `earned_at` when required sections first all complete
> (§3.4, §4.2).
> (P2) **Stale §2 auth wording** — removed the `Gate::authorize('view')`-only description; §2 now matches
> §6/§7 (`permission:entrepreneurs.assess` + policy).
> (P3) **Streak threshold defined** — a day counts only on a newly-completed required section *or* a
> normalised-body hash change with **net word-delta ≥ `config('gamification.streak_min_word_delta')`
> (default 5)** (§3.5).

> **v1.3 revision (review pass — streak/phase integrity).** Five fixes:
> (P1) **Streaks were gameable** — `PlanBuilder::upsertSection` re-saves with no meaningful-diff check, so
> trivial re-saves could inflate a streak. Reframed: streaks are a **lower tier** (engagement signal, not a
> validated badge), and only a **newly-completed required section or materially-changed edit** counts — a
> no-op/whitespace re-save does not (§1.3, §3.5, §5a).
> (P2) **Phase badge rule** — `PlanPhase.status`/`completion()` flag a phase complete on **any** one
> section; the badge now requires **all required sections** for that phase (portal requirement set) (§3.4).
> (P2) **Ack evidence** — added `plan_phase`/`plan_section`; phase badges cite `plan_phase` for a stable
> `earned_at`, not `business_plan.updated_at` (§4.2).
> (P2) **Streak resurrection** — the daily cron no-ops while disabled, so re-enable could revive a stale
> streak; added **recompute-on-enable** (reset to 0 if outside window) (§2, §5c).
> (P3) **Streak day boundary** — app tz is `UTC`; streak days now bucket in **`Pacific/Auckland`** (§3.5).

> **v1.2 revision (review pass — code-grounded correctness).** Seven fixes after checking the build:
> (P1) **capstone finalised-guard** — `AdvisoryReadiness::evaluate()` doesn't check `finalised_at`, so the
> advisory-ready badge must independently require `signal.planAssessment.finalised_at` (§3.4).
> (P1) **streak write contract** — added `EntrepreneurStreak` (substantive-activity hook + daily reset
> cron); the "only writes are toggle + acks" line was wrong (§5).
> (P2) **toggle auth** — `permission:entrepreneurs.assess` + policy `view`, matching the other mutating
> actions; `view` alone is too broad (§6, §7).
> (P2) **journey level from `BusinessPlan.current_phase`**, not the stage enum (which pegs every builder at
> phase 1) (§3.1).
> (P2) **grade trajectory recomputed** — `PlanAssessment` has no `weighted_score` column and stored
> `overall_grade` goes stale after advisor overrides; reuse the existing blended weighting helper (§3.3).
> (P2) **field-name consistency** — `gamification_enabled` → `gamification_on` throughout.
> (P3) **acks** use explicit `evidence_source_type`/`evidence_source_id` columns, not a JSON-expression
> unique (§4.2). Streak service folds into **WO-G2**.

> **v1.1 revision (the spec surfaced).** The owner provided `FSA_Entrepreneurial_Platform_TechSpec_v1.docx`,
> which **does** specify gamification — correcting v1.0's "never specified" framing. Gamification was lost
> when the module was re-architected from that **standalone Next.js platform** into the **Laravel
> monolith**. Re-anchored the plan to the spec's definition: advisor-controlled `gamification_on`, **streak
> tracking** (`current_streak`/`last_active_at` + daily cron), **section milestone celebrations**, and
> **progress badges** (§1, §2, §3.4–3.5, §4.1). Flagged the spec's **sibling `investor_score_on`** (also
> dropped) as a follow-up (§11.6). **Open architecture question — see the response: is the Laravel module
> the canonical target, or is the standalone platform still to be built? That gates everything below.**

> Entrepreneur-module work is Phase-3 territory; per CLAUDE.md "No feature outside the plan" / "Do not
> implement Phase 2+ features without explicit owner approval", **this plan is the approval artifact** —
> nothing here is built until the owner signs off the scope (§11 open decisions).

> **Build workflow.** This plan is built into the **Laravel app's test environment by Codex**, for the
> owner to test before pushing to live — it is the build spec, not the build. Targets the existing
> entrepreneur module (not a new standalone codebase). Proceed on the **§11 defaults** (gamification off
> by default; substantive-activity streaks; per-entrepreneur, profile-level toggle; `investor_score_on` as
> a separate follow-up) unless the owner overrides.

---

## 1. Why this exists (and the honest framing)

The reviewer is correct that there is no gamification in the build — but it **was** specified in the
original standalone entrepreneur tech spec (`FSA_Entrepreneurial_Platform_TechSpec_v1.docx`, §3.3 / §4 /
§10 step 2.5): an advisor-controlled `gamification_on` flag, **streak tracking** (`current_streak`,
`last_active_at`), **section milestone celebrations**, and **progress badges**, with a daily streak
cron. It was lost when the module was re-homed from that standalone Next.js platform into the Laravel
monolith. This plan ports it back, in the **plan-progress-driven** form the owner confirmed: rewards and
progression tie to the entrepreneur's *real* journey through the assessment pipeline, **not** vanity
activity. (Per spec, gamification has a **sibling toggle `investor_score_on`** — an investor-readiness
score — that was dropped in the same hand-off; see §11.6.)

This feature is governed by the **AI Integrity Principle** (CLAUDE.md §3) more tightly than most UI:

1. **Evidence-based.** Every level, badge, and milestone cites the concrete record that earned it (a
   finalised `PlanAssessment`, a passed `IdeaValidation`, the `AdvisoryReadinessSignal`, the current
   `EntrepreneurStage`). The panel *evidences* progress; it does not *assert* it.
2. **Honest.** A low grade is shown as a low grade. Gamification never masks a poor assessment behind
   cheerful UI, never invents progress, never fabricates a streak. Kindness in delivery, never in
   content.
3. **Not gameable — by tier.** **Badges, the grade trajectory, and the capstone** trigger only on
   **advisor-validated / finalised** events (`PlanAssessment.finalised_at`, the advisor's idea gate, the
   readiness signal) — never on self-reported activity. **Streaks are deliberately a lower tier:** an
   *engagement signal, not a validated badge* — they never affect scoring and are **guarded against the
   obvious gaming** (a no-op or trivial re-save does **not** count; only a *meaningful content change* or a
   *newly-completed required section* does — §3.5). So an entrepreneur can neither earn a **badge** by
   gaming the UI nor inflate a **streak** by re-saving unchanged content.
4. **Display-only.** It never changes the assessment, grade, weighted score, PV/valuation, or the
   advisory-readiness threshold. It reads those; it does not write them.
5. **Not a learning layer.** Thresholds (what earns what) are fixed, admin-configurable config — **not**
   adaptive or self-modifying. No silent learning updates (CLAUDE.md process rules).

---

## 2. The advisor control (the gap the reviewer flagged)

"Advisor choosing whether the client needs gamification" is the spec's advisor-controlled
`gamification_on` flag (TechSpec §3.3, toggled via `PATCH /api/advisor/portfolio/[pid]/settings`),
re-homed to the Laravel module as a **per-entrepreneur opt-in**:

- New column `entrepreneur_profiles.gamification_on` (boolean, **default `false`** — advisors opt a
  specific entrepreneur *in*). (Spec places it on `BusinessPlan`; profile-level matches how advisors
  actually control it per entrepreneur. Confirm placement, §11.7.)
- A new advisor action on `EntrepreneurActionController` (e.g. `setGamification`), gated by
  **`permission:entrepreneurs.assess` middleware *plus* `Gate::authorize('view', $entrepreneurProfile)`**
  (assigned advisor or super_admin) — the full auth specified in §6/§7, **not** `view` alone. Toggle UI on
  the `advisor.entrepreneurs.show` page. Audited (`gamification.enabled` / `gamification.disabled`).
- When `false`, the entrepreneur portal shows **no** gamification UI and the progress service returns a
  disabled result — zero behavioural change from today.
- **On enable, the action recomputes the streak from the ledger** (§4.3 / §5c) — *not* from a cached
  `last_active_at` — so a stale streak frozen during a disabled stretch cannot resurrect (a gap in
  `active_day`s yields 0). The enable/disable transition is audited.

---

## 3. Mechanics — derived from existing progress signals

All mechanics are **computed** from data the entrepreneur module already produces. Nothing here invents
a new scoring system.

### 3.1 Journey map / levels — `EntrepreneurStage` + `BusinessPlan.current_phase`
The progression path is the stage enum **except the five building phases, which the profile does not
track**: `PlanBuilder` only moves the profile to `building_phase1`, then advances
`BusinessPlan.current_phase` (1→5) as later-phase sections are saved (`PlanBuilder` ~line 102). So the
map must derive its building sub-level from **`BusinessPlan.current_phase`**, not the stage — reading the
stage alone would peg every building entrepreneur at "phase 1".
```
invited → onboarding → readiness → idea_validation
        → building (phase {current_phase} of 5, from BusinessPlan.current_phase)
        → submitted → assessment → revising → launched → advisory_ready
```
`advisory_ready` is the capstone. (`cancelled` is excluded.)

### 3.2 Plan completion — `BusinessPlan` phases & sections
A progress bar from `BusinessPlan.current_phase` (the real phase pointer) + completed `PlanSection`s
within the active phase, plus an overall "plan readiness" bar. Reads existing data only.

### 3.3 Grade trajectory — recomputed, never read from a stale field
Show the **real** grade movement across **finalised** rounds (`PlanAssessment.round`, `finalised_at`).
**The build forces a caveat:** `PlanAssessment` has **no `weighted_score` column** —
`Assessment::weightedScore()` computes it transiently and stores only `overall_grade`, set from **AI
scores at first pass**; after `Assessment::adjustScore()` applies advisor overrides, the stored
`overall_grade` is **stale**. So the trajectory must **recompute** each round's weighted score by
**reusing the existing weighting logic that blends advisor overrides over AI scores**
(`AdvisoryReadiness::score()` / `Assessment::weightedScore()` — extract to a shared helper, don't
duplicate or fork the formula), then grade it via `RatingFramework::gradeFor()`. Never display the stored
`overall_grade` for a round that has advisor adjustments. Only finalised rounds count.

### 3.4 Milestone badges — discrete, each tied to one validated event
| Milestone | Earned when (evidence) |
|---|---|
| Idea validated | advisor idea-gate passed (`IdeaValidation`, via `EntrepreneurActionController::gateIdea`) |
| Phase complete ×5 | **all required sections for that phase** first reach `STATUS_COMPLETE` — **awarded once**, `earned_at` captured then (phase-rule note) |
| Plan submitted | the plan is **submitted** — **awarded once at the submit action**, `earned_at` captured then (submitted-rule note) |
| First assessment | a `PlanAssessment` reaches `finalised_at` |
| Grade up | letter grade improves between two finalised rounds |
| **Advisory-ready** (capstone) | the readiness threshold is crossed **on a finalised assessment** — evidence is that **finalised `plan_assessment`** (immutable), not the mutable signal (see guard) |

Each badge stores/derives an **evidence reference** to the row that earned it, surfaced in the UI tooltip
("Earned when your Round-2 assessment was finalised on 12 Mar"). These are the spec's "section milestone
celebrations" + "progress badges" (TechSpec §10 step 2.5), re-expressed against the Laravel module's
real progress events.

> **Phase-rule note (P2).** A phase badge must mean **all required sections in that phase complete**, using
> the portal's per-phase requirement set (`EntrepreneurPlanController`). Do **not** key it on
> `PlanPhase.status` / `PlanBuilder::completion()` — those flag a phase "complete" when **any one** section
> in it is complete (`PlanBuilder` ~line 147), far too loose for a milestone. **There is no
> `plan_phases.completed_at`** (the table has only a churning `updated_at`, re-saved by `refreshPhaseStatus`
> on every section save), so the badge cannot read a stable phase timestamp: the awarder detects "all
> required sections complete" and **persists `earned_at` into the award** (§4.2), evidence
> `plan_phase` → that phase's id.

> **Submitted-rule note (P1).** `BusinessPlan.status = submitted` is **transient** — it is overwritten to
> `assessing` (`Assessment::firstPass`), `finalised` (`Assessment::finalise`), then living-plan states, so a
> badge derived from `status == submitted` would **vanish** after normal progression. Instead the
> `plan_submitted` badge is **awarded at the submit action** (`EntrepreneurPlanController` submit / its
> audit event), persisting `earned_at` once; thereafter it is durable regardless of later status. (There is
> no `business_plans.submitted_at`; the award row *is* the durable record.)

> **Capstone finalised-guard + immutable evidence (P1/P2a).** `AdvisoryReadiness::evaluate()` (a) builds the
> signal from the latest assessment **by round, without checking `finalised_at`**, and (b) **`updateOrCreate`s
> one signal per profile** (`AdvisoryReadiness` ~line 47) — so the signal is **mutable**: its
> `plan_assessment_id` / `score` / `surfaced_at` can change on a later re-eval. The capstone badge therefore
> (1) requires the crossing assessment's **`finalised_at IS NOT NULL`**, and (2) takes its evidence as that
> **finalised `plan_assessment`** (immutable), with `earned_at = finalised_at` — **not** the signal row. The
> award snapshots the assessment id so it can never drift. (Worth flagging that `evaluate()` itself should
> arguably require a finalised assessment; the badge enforces it regardless.)

### 3.5 Streaks — `current_streak` + `last_active_at` (spec-mandated)
The spec makes streaks a first-class mechanic (`BusinessPlan.current_streak`, `last_active_at`, a daily
streak-calculation cron, and `GET /api/plans/[id]/streak`). Port it: a **consecutive-active-day** streak,
shown only when `gamification_on`, that never affects scoring or assessment. Two honesty constraints the
build forces:
- **"Active" = a *meaningful* change, with a defined threshold (P3).** `PlanBuilder::upsertSection` does a
  plain `updateOrCreate` and marks a section `COMPLETE` on any non-empty body (`PlanBuilder` ~line 126) — so
  a no-op or one-character re-save would otherwise inflate a streak, violating §1. A day counts **only** if
  **either**:
  1. a **required section newly reaches `STATUS_COMPLETE`** (a real progress event); **or**
  2. a section's **normalised body** (trimmed, internal whitespace collapsed) hashes **differently** from
     **that same section's** last-counted body, **and** the net word-count change is **≥ a config threshold**
     — `config('gamification.streak_min_word_delta')`, **default `5`**.
  The compare is **per section, not per entrepreneur** — otherwise saving an unchanged section B right after
  a counted section A would compare B against A's hash and **falsely count**. So the last-counted hash +
  word count are kept **per section**, in the streak-events ledger (§4.3): an identical/whitespace-only
  re-save of a section yields its own unchanged hash (no count); a <5-word tweak is below threshold (no
  count). The threshold is a single named constant so tests and implementation agree.
- **Day boundary = `Pacific/Auckland`, not the app default.** `config/app.php` is `UTC`; computing
  "consecutive days" in UTC would roll the day over at ~12–1pm NZ and feel wrong to the user. Streak
  day-bucketing is done explicitly in **`Pacific/Auckland`** (the user-facing day), independent of the app
  timezone.

### 3.6 Explicitly NOT in v1 (see §10)
No leaderboards, no cross-entrepreneur comparison, no social/competitive mechanics — entrepreneurs are
RLS-isolated and cross-entity comparison would breach scope and privacy (and Phase 1 has no cross-client
intelligence). No points-store/redemption. No monetary/fee-linked rewards.

---

## 4. Data model

Mostly derived from existing signals — **but some milestones have no durable evidence.**
`business_plans.status` is overwritten as the plan progresses (submitted → assessing → finalised →
living-plan), and `plan_phases` / `plan_sections` have **no completion timestamp** (only a churning
`updated_at`, re-saved by `refreshPhaseStatus` on every section save). So milestones are **awarded once and
persisted with the moment they were earned**, not re-derived from mutable rows.

### 4.1 `entrepreneur_profiles` — new columns (spec §3.3)
| Column | Type | Notes |
|---|---|---|
| `gamification_on` | boolean default `false` | advisor-controlled opt-in (§2) |
| `current_streak` | unsignedInteger default `0` | **cached projection** — recomputed from the streak-events ledger (§4.3), not a free-standing counter |
| `last_active_at` | timestampTz nullable | last counted activity (cached from §4.3) |

### 4.2 `entrepreneur_milestone_awards` — durable awards (earned-once) + seen-state
A milestone is **awarded once, at the moment it is first achieved**, capturing `earned_at` then — so the
badge and its date survive later churn (`status` overwritten, `updated_at` re-saved). Detection reads real
evidence (§3.4); the award is the durable record. The earlier "purely re-derived" model is replaced
because `plan_submitted` and `phase_*` are **not** durably re-derivable.
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `entrepreneur_profile_id` | FK entrepreneur_profiles **restrictOnDelete** | **scope key** (entrepreneur RLS branch). Restrict, not cascade — see P2 note |
| `milestone_key` | string | `idea_validated`, `phase_1`…`phase_5`, `plan_submitted`, `first_assessment`, `grade_up`, `advisory_ready`. **All singleton except `grade_up`** (repeatable per round) |
| `evidence_source_type` | string | `idea_validation` \| `plan_phase` \| `business_plan` \| `plan_assessment`. **`first_assessment` / `grade_up` / `advisory_ready` cite the finalised `plan_assessment`** — never the mutable `advisory_readiness_signal` (P2a, §3.4) |
| `evidence_source_id` | uuid | the earning row's id |
| `evidence_snapshot` | jsonb nullable | **frozen scalar facts at award time** — for assessment-evidenced badges: the `weighted_score`, `grade`, and the source `finalised_at`. Because the source assessment is **not** immutable in code (P1 note), the badge reads this snapshot, so a later `adjustScore`/`finalise` can't rewrite what the badge claimed |
| `earned_at` | timestampTz | set on insert, **never updated**. = the **source row's own timestamp** where one exists (`idea_validated`→`advisor_gate_passed_at`; `first_assessment`/`grade_up`/`advisory_ready`→ the assessment's `finalised_at` **as snapshotted**); else the event moment (`plan_submitted`→submit time, `phase_N`→completion-detection time). **Never reconciliation time** (P2c). A reconcile picks the **earliest qualifying** evidence row (§5) |
| `seen_at` | timestampTz nullable | drives the "new" toast — the only business field the entrepreneur may change (`updated_at` bumps with it); everything else immutable (§4.2 trigger) |
| timestamps | | |
| | | **Singleton (one ever):** `UNIQUE (entrepreneur_profile_id, milestone_key) WHERE milestone_key <> 'grade_up'`. **Repeatable:** `UNIQUE (entrepreneur_profile_id, milestone_key, evidence_source_id) WHERE milestone_key = 'grade_up'`. So a second `idea_validated`/`first_assessment` (multiple evidence rows exist) can't double-award (P2b) |

> **Scoping/RLS — command-specific (P1).** `ENABLE` + `FORCE` RLS, but **not** one shared USING/WITH CHECK
> (a single owner-scoped policy would let an entrepreneur *INSERT their own award*). Split by command:
> - **SELECT** — owner / assigned advisor / super_admin / system (the `documents_scope` entrepreneur
>   branch: `EXISTS … entrepreneur_profiles … user_id / assigned_advisor_id = fsa_current_user_id()`).
> - **INSERT** — **`system` only** (`fsa_current_role() = 'system'`); the awarder (§5) runs in system
>   context. No entrepreneur/advisor INSERT.
> - **UPDATE** — owner only, and only via the `seen_at` path (the immutability trigger below is the real
>   guarantee); **no DELETE** policy.
>
> **Immutability trigger — allowlist (P2).** RLS is row-level and can't pin columns, so add a DB trigger
> mirroring `audit_events`' (`fsa_audit_events_block_mutation`). Use an **allowlist, not a named blocklist**:
> on `UPDATE`, RAISE unless the **only** columns that differ OLD→NEW are `seen_at` and `updated_at` — so
> `id` and any *future* column are protected automatically (a named list would have missed `id`). Add
> **`BEFORE DELETE` *and* `BEFORE TRUNCATE`** triggers that reject outright (the audit pattern blocks
> TRUNCATE too — without it append-only is bypassable by truncation). A normal Eloquent `seen_at` save
> (which bumps `updated_at`) passes; everything else is rejected at the DB layer.

> **FK = restrict, not cascade (P2).** `cascade` here is self-contradictory: deleting a parent profile would
> cascade a DELETE into award rows that the trigger *refuses* to delete (and `SET NULL` would hit the UPDATE
> trigger), so the parent delete would error anyway. Use **`restrictOnDelete`** — a profile with gamification
> history can't be hard-deleted until that history is explicitly handled, which is exactly right for durable
> evidence. (Note this overrides the implicit cascade-via-profile delete path; deleting such a profile
> becomes a deliberate admin action.)

> **P1 — the source assessment is *not* immutable in code.** `Assessment::adjustScore()` rewrites
> `advisor_scores` (changing the blended weighted score/grade), and `finalise()` `forceFill`s `finalised_at`
> = `now()` on **every** call (`Assessment` ~lines 82/150) — so it is neither idempotent nor frozen. The
> award therefore **snapshots** the weighted score / grade / `finalised_at` into `evidence_snapshot` at award
> time and renders from that, so capstone / grade-up evidence stays truthful regardless of later
> re-adjustment. (A complementary core-side fix — make `finalise()` idempotent and block `adjustScore` after
> finalise — is worth doing but is outside this plan; the snapshot makes the badge correct either way.)

### 4.3 `entrepreneur_streak_events` — per-section activity ledger (append-only)
The streak's anti-gaming state. One row per *meaningful* section change; `current_streak` / `last_active_at`
(§4.1) are recomputed from it.
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `entrepreneur_profile_id` | FK entrepreneur_profiles **restrictOnDelete** | **scope key** (entrepreneur RLS branch). Restrict, not cascade (same P2 reason as §4.2 — cascade fights the append-only trigger) |
| `plan_section_id` | uuid (plain ref, **no FK action**) | per-section state (§3.5) — a *historical* pointer (audit-style); a section hard-delete must not cascade/SET-NULL into this append-only ledger |
| `content_hash` | string | SHA-256 of the section's **normalised** body at this event |
| `word_count` | unsignedInteger | for the ≥`streak_min_word_delta` check vs the section's prior event |
| `active_day` | date | the `Pacific/Auckland` day this counted toward |
| `occurred_at` | timestampTz | |
| timestamps | | |
| | | `INDEX (entrepreneur_profile_id, plan_section_id, occurred_at)`; `INDEX (entrepreneur_profile_id, active_day)` |

> Detection compares a new save against **this section's latest ledger row**; appends only on a meaningful
> change (§3.5). `ENABLE` + `FORCE` RLS, **command-specific (P1)**: **SELECT** owner / assigned advisor /
> super_admin / system; **INSERT `system` only** (the streak service runs in system context); **no user
> INSERT / UPDATE / DELETE** — a single owner-scoped policy would let an entrepreneur forge their own streak
> rows.
>
> **Append-only trigger (P1/P2).** Because `current_streak` is a *projection* of this ledger, mutating a row
> would rewrite streak history. So the ledger gets the **same append-only triggers** as `audit_events`
> (`fsa_audit_events_block_mutation` pattern): **`BEFORE UPDATE`, `BEFORE DELETE`, *and* `BEFORE TRUNCATE`**
> all reject outright (TRUNCATE included — else append-only is bypassable by truncation). It's pure
> insert-only (no mutable field).
>
> **Projection — distinct days, alive "today or yesterday" (P2).** `current_streak` is computed over the
> **`DISTINCT active_day`** values (a day with *two* meaningful changes counts **once** — a row-based loop
> would otherwise inflate or break the run). It is the length of the consecutive run of distinct
> `active_day`s ending at the **latest** one, **only if that latest day is today *or* yesterday**
> (`Pacific/Auckland`); if it's **before yesterday**, `current_streak = 0`. So a streak isn't reset at
> midnight before the entrepreneur has acted — it survives until the day fully lapses.

---

## 5. Service

- **`EntrepreneurGamification`** (read) — given a profile loaded with `stage`, latest `BusinessPlan` (+
  `current_phase`, `sections`, `assessments.ratingFramework.criteria`), `advisoryReadinessSignals`, **and
  the persisted `entrepreneur_milestone_awards`**, returns a payload: current level (stage **+
  `current_phase`**, §3.1), plan-completion %, **recomputed** grade trajectory (§3.3), earned milestones
  (each with `evidence_source_type`/`evidence_source_id` + **`earned_at` read from the award row**, not a
  mutable source), the streak, and the next target. **Returns a disabled result when `gamification_on =
  false`.** Does **not** write assessments/grades/scores/signals. Unit-tested with fixtures.
- **`EntrepreneurMilestoneAwarder`** (write, **system context**) — detects newly-achieved milestones and
  **inserts an award once** (idempotent on the §4.2 singleton/repeatable uniques). For a **singleton**
  milestone with several qualifying evidence rows (e.g. multiple gated `IdeaValidation`s, or multiple
  finalised assessments), a reconcile **must pick the *earliest qualifying* row** — `idea_validated` =
  `min(advisor_gate_passed_at)`, `first_assessment` / `advisory_ready` = the **earliest** finalised
  assessment (that crossed threshold, for the capstone) — **never** "first/latest returned by the query"
  (P2-ordering), so `earned_at` reflects the *first* achievement. **`earned_at` = that row's own timestamp**
  (`advisor_gate_passed_at` / `finalised_at`), else the event moment (`plan_submitted`→submit time;
  `phase_N`→completion-detection time) — **never reconciliation time** (P2c). It **snapshots** the
  weighted-score/grade/`finalised_at` into `evidence_snapshot` (§4.2 P1). `phase_N` and `plan_submitted`
  detection run off the events below / the submit action; capstone & grade-up off the finalise flow. Never
  writes assessment/scoring tables.
- **Dispatch mechanism (P2).** `entrepreneur.plan_section_saved` is today only an **audit string**
  (`AuditWriter::record` in `Services\Entrepreneurs\PlanBuilder` ~line 105) — *not* a dispatched event, so
  there is nothing to "hook". This plan **adds a real `EntrepreneurPlanSectionSaved` Laravel event**
  dispatched from that save path (carrying the section + prior/new normalised body), with listeners that run
  streak detection **and** the awarder's `phase_N` detection. (Equivalently, call the streak/award services
  inline from `PlanBuilder::saveSection`.) Don't rely on the audit row as an event.
- **`EntrepreneurStreak`** (write, **system context**) — maintains the `entrepreneur_streak_events` ledger
  (§4.3) and the cached `current_streak`/`last_active_at`:
  - **(a) Meaningful activity → ledger** — on `EntrepreneurPlanSectionSaved` (above): if the save is a
    newly-completed required section **or** a materially-changed edit vs **that section's** latest ledger row
    (§3.5), append one `entrepreneur_streak_events` row (today's `Pacific/Auckland` `active_day`). A
    trivial/no-op/sub-threshold re-save appends nothing.
  - **(b) Recompute (distinct days; alive today *or* yesterday)** — `current_streak` = the consecutive run of
    **`DISTINCT active_day`** values ending at the latest `active_day` (multiple changes on one day count
    **once**), counted **only if that latest day is today or yesterday** (`Pacific/Auckland`); else 0 (§4.3).
    `last_active_at` = the latest event. A **daily cron** (mirroring `SendReengagementReminders`) recomputes,
    so the streak drops to 0 only once a **full day** lapses — **not** at the first midnight (the
    morning-after a yesterday-active day still shows the streak).
  - **(c) (Re-)enable** — the toggle's enable path recomputes from the ledger too. Because the streak is a
    **projection of the durable ledger** (not a free-standing counter), it **cannot resurrect** stale across
    a disabled stretch — a gap in `active_day`s yields 0 regardless of when it is recomputed.
  All paths are a no-op when `gamification_on = false`. Honest: only substantive per-section work counts,
  never a bare login, never a resurrected streak.

**Write contract.** The feature writes only its own rows: the advisor toggle (§2); milestone **awards**
(insert-only) + `seen_at` (§4.2); the `entrepreneur_streak_events` ledger + the cached
`current_streak`/`last_active_at` (§4.3). Award/streak writes run in a `system` context. It **never** writes
assessments, grades, scores, readiness signals, or any plan/phase/section row.

---

## 6. RBAC

No new global permission needed.
- **Advisor toggle:** gated by **`permission:entrepreneurs.assess`** route middleware — matching every
  other *mutating* entrepreneur action (`gateIdea` / `assess` / `finalise` / `convert` in
  `routes/advisor.php`) — **plus** the controller's `Gate::authorize('view', $entrepreneurProfile)` for
  the assigned-advisor / super_admin scoping. `view` alone is too broad for a settings change; **both**
  checks are required (the permission gates *who can change settings*, the policy gates *which
  entrepreneur*).
- **Entrepreneur view:** sees their **own** gamification on the portal (entrepreneur portal scope;
  `entrepreneur_profiles.user_id = auth id`). Acks write only their own rows (RLS, §4.2).
- **Advisor preview:** the assigned advisor sees what the entrepreneur sees on `advisor.entrepreneurs.show`.

---

## 7. Routes

Inside the existing `auth` + `verified` + `mfa` stack.

**Advisor — toggle** (existing advisor entrepreneur group; `middleware('permission:entrepreneurs.assess')`
+ controller `Gate::authorize('view', …)` — §6):
```
PATCH  advisor/entrepreneurs/{entrepreneurProfile}/gamification   advisor.entrepreneurs.gamification.update
```
**Entrepreneur portal — seen state** (entrepreneur portal group; not client-scoped):
```
POST   portal/entrepreneur/gamification/seen                      portal.entrepreneur.gamification.seen
```
The gamification payload itself rides on the existing `portal/entrepreneur` Dashboard render (§8) — no new
GET route needed. Run `php artisan wayfinder:generate --with-form` after route changes.

---

## 8. Frontend (Inertia + React 19 + shadcn/ui)

Reuse shared styling primitives (`page-header`, `section-card`, `empty-state`). No new design language.

- **`portal/entrepreneur/Dashboard`** — add a **Gamification panel** rendered only when enabled: the
  journey map (current stage highlighted, next stage shown), plan-completion bars, earned badges with an
  **evidence tooltip** on each (honouring "evidence, not assertion"), the grade trajectory, and the next
  milestone. **Honest framing:** a low grade renders as a low grade; copy is **static/templated**
  (no AI-generated praise) and encouraging about *next steps*, never sugar-coating the assessment.
- **`advisor/entrepreneurs/Show`** — a `Gamification` toggle control + a read-only preview of the
  entrepreneur's panel.

---

## 9. Testing (gates must stay green)

PHPUnit feature + unit; `FakeAiClient` + `NoopScanner` bound. Required:
- **Toggle auth**: a user **without** `entrepreneurs.assess` cannot toggle (403), even on an assigned
  entrepreneur; an advisor **with** the permission cannot toggle an entrepreneur they are not assigned to
  (policy 403); super_admin can; the toggle is audited.
- **Disabled = invisible**: with `gamification_on = false`, the Dashboard payload carries no gamification
  and the portal shows nothing; the streak service is a no-op.
- **Journey level from phase**: an entrepreneur whose profile stage is `building_phase1` but whose
  `BusinessPlan.current_phase = 3` shows **phase 3 of 5**, not phase 1.
- **Capstone finalised-guard**: an `AdvisoryReadinessSignal` whose `planAssessment.finalised_at` is null
  earns **no** advisory-ready badge; only a finalised one does.
- **Grade recompute**: after `Assessment::adjustScore()` overrides change the blend, the trajectory shows
  the **recomputed** grade (shared weighting helper), not the stale stored `overall_grade`.
- **Milestone durability**: the `plan_submitted` award **persists** after `BusinessPlan.status` moves to
  `assessing`/`finalised` (no longer `== submitted`); a `phase_N` award's `earned_at` is **unchanged** after
  later section saves churn `plan_phases.updated_at`; re-detection never inserts a duplicate or moves
  `earned_at`.
- **Snapshot stability**: after a grade-up/capstone award, an `Assessment::adjustScore()` (and a second
  `finalise()` that moves `finalised_at`) does **not** change the badge — it renders the snapshotted
  grade/`weighted_score`/`finalised_at` from `evidence_snapshot`, not the now-changed assessment.
- **Restrict-on-delete**: hard-deleting an `entrepreneur_profile` (or `plan_section`) that has awards /
  streak events is **blocked** by the restrict FK / plain historical ref — the append-only trigger is never
  asked to cascade-delete a row it would refuse.
- **Event dispatch**: a meaningful section save **dispatches `EntrepreneurPlanSectionSaved`**, and the
  listeners run streak detection + `phase_N` awarding (asserted via the recorder / the resulting rows) — a
  test confirms it's a real event, not just an audit row.
- **Streak — meaningful change + threshold**: a newly-completed required section, or an edit with net
  word-delta **≥ 5** (the config constant) vs **that section's** last ledger row, appends a streak event;
  an **identical/whitespace-only re-save** and a **<5-word tweak** do **not**; a bare login changes nothing.
- **Streak — per-section, not per-entrepreneur**: after a meaningful change to **section A** is counted,
  saving **section B unchanged** appends **no** event (B is compared to B's own history, not A's).
- **Streak — alive today *or* yesterday**: with the last `active_day` = **yesterday** and no activity yet
  today, `current_streak` is still **> 0** (the morning-after case); it drops to **0** only once the latest
  `active_day` is **before yesterday**.
- **Streak — no resurrection**: because `current_streak` is recomputed from the ledger, a gap in
  `active_day`s yields **0** regardless of disable/re-enable timing.
- **Write authority (RLS)**: an **entrepreneur cannot INSERT** their own award or streak-event row (INSERT
  is `system`-only); an advisor cannot either; both **can SELECT** their own. The awarder/streak service
  (system context) can insert.
- **Award immutability (allowlist trigger)**: a direct `UPDATE` changing `earned_at` / evidence /
  `milestone_key` / **`id`** is **rejected**; `DELETE` and **`TRUNCATE`** are rejected; but a **normal
  Eloquent `seen_at` update — which also bumps `updated_at` — succeeds** (only `seen_at`+`updated_at` may
  differ).
- **Streak ledger immutability**: a direct `UPDATE`, `DELETE`, **or `TRUNCATE`** on `entrepreneur_streak_events`
  is **rejected** by its append-only triggers (streak history can't be rewritten).
- **Streak — same-day duplicates**: two meaningful changes on the **same `active_day`** count as **one**
  day in `current_streak` (distinct-day computation), neither inflating nor breaking the run.
- **Singleton vs repeatable**: with two gated `IdeaValidation` rows, only **one** `idea_validated` award
  exists; `grade_up` may have multiple awards (one per improving finalised round).
- **Capstone evidence stable**: re-running `AdvisoryReadiness::evaluate()` (which `updateOrCreate`s the
  signal) does **not** change the existing advisory-ready award's evidence or `earned_at` (it cites the
  finalised assessment, not the signal).
- **Reconcile picks earliest**: with two gated ideas (or two finalised assessments), the singleton award's
  `earned_at` = the **earliest** qualifying source timestamp, **not** the latest/first-returned or the
  reconcile time.
- **Streak — timezone**: day boundaries bucket in `Pacific/Auckland`; an action at 23:00 NZ and the next at
  01:00 NZ count as **two** consecutive days even though both are the same UTC day.
- **Phase badge — all required sections**: a phase with one section complete but others outstanding earns
  **no** phase badge; it earns the badge only when *all required* sections are complete, and the **award**
  cites `plan_phase` with a persisted `earned_at`.
- **Not gameable**: a provisional (non-finalised) assessment earns no grade/capstone badge; no
  self-reported path awards anything.
- **Honesty**: a low/declining grade is reported truthfully (no masking) — assert the payload reflects
  the real grade.
- **RLS**: entrepreneur X cannot read entrepreneur Y's awards or streak events; an entrepreneur may write
  only `seen_at` on their own award rows.
- **Audit**: enable/disable writes an `audit_events` row.
- **No regressions**: existing entrepreneur/assessment/portal suites stay green.
- Static gates: `pint --test`, `tsc --noEmit`, ESLint, Prettier, `composer audit`, `npm audit`.

---

## 10. Out of scope (v1) / explicit deferrals

- Leaderboards / cross-entrepreneur comparison / any social or competitive mechanic.
- Points store, redemption, or any monetary / fee-linked reward.
- AI-generated dynamic praise copy (use static templates; revisit only with bias-detection in the loop).
- Gamification for **SME clients, NPO, broker, coach** portals — entrepreneur portal only this round.
- Adaptive/auto-tuned thresholds (that would be a governed learning layer — not v1).

---

## 11. Open decisions (confirm before build)

1. **Default state** — `gamification_on` defaults **off** (advisor opts in per entrepreneur), matching the
   spec's `@default(false)` and the "advisor chooses" framing. *(default: off)*
2. **Milestone set** — the §3.4 list is the proposed set; confirm additions/removals.
3. **Streak "active" definition** — spec mandates streaks; we define a day as active only on **substantive
   plan work** (saved edit/completion), not a bare login, to stay honest. Confirm. *(default: substantive)*
4. **Global default** — keep it strictly per-entrepreneur, or also give super_admin a practice-wide
   default for new entrepreneurs? *(default: per-entrepreneur only)*
5. **Phase placement** — confirm this ships as an entrepreneur-module addition now, versus parked until
   the broader entrepreneur module work is scheduled.
6. **Sibling `investor_score_on`** — the spec pairs gamification with an advisor-controlled
   **investor-readiness score** (`investor_score_on`, `Assessment.investor_score/grade/feedback`), also
   absent from the build. Out of scope for *this* plan, but flagged: do you want it covered (here, or its
   own plan)? *(default: separate follow-up)*
7. **Toggle placement** — spec puts `gamification_on` on `BusinessPlan`; this plan puts it on
   `EntrepreneurProfile` (advisor controls per entrepreneur). Confirm profile-level is acceptable.
   *(default: profile-level)*

---

*Aligned with Spec V2.4 §3 (AI Integrity — evidence-based, honest, unbiased, display-only) and §4
(security baseline — RLS, audit, invite-only/MFA unchanged). Entrepreneur-portal scoped; does not alter
SME, DD, or NPO behaviour.*
