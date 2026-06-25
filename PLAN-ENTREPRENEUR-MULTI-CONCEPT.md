# PLAN — Entrepreneur Multi-Concept (one founder, many ideas)

**Plan version:** 1.1 — owner decisions + code-grounded design pass + accuracy re-verification against the
actual code. *(Build target: Codex, into the test env, then push to live.)*

> **v1.1 revision (self-review against the code — five accuracy fixes).**
> (1) **Blast radius corrected** — ~3 of the grep's 22 hits are *panel-referral* `->stage`
> (`PartnerPanelController`, `ReferralConsentManager`, `ReferralLifecycle`'s `$referral->stage`), **not**
> entrepreneur stage; excluded. Real count ~16 (§7, C4).
> (2) **Stage-helper methods move too** — `EntrepreneurProfile::currentStage/currentStageValue/currentStageLabel/ensureStageIsValid/rawStageValue`
> ([:26-52,134-149](app/Models/EntrepreneurProfile.php:26)) relocate to `EntrepreneurConcept` (was unmentioned) (§7, C4).
> (3) **Three creation sites named** — advisor `EntrepreneurController::store` (primary),
> `EntrepreneurInviteReconciler`, `ReferralLifecycle`; concept #1 seeded via one centralised path (§8, C2).
> (4) **Conversion ambiguity** — `AdvisoryConversion` auto-resolves the profile's `latest('updated_at')` plan
> ([:27-30](app/Services/Entrepreneurs/AdvisoryConversion.php:27)); with many concepts it must take the
> *selected* concept's plan (§8, C7).
> (5) **Capacity `stage` index** — the `entrepreneur_profiles(['assigned_advisor_id','stage'])` index must be
> dropped + re-created on `entrepreneur_concepts` when `stage` moves (§5, §7, C4).

**One-line intent:** Let a single entrepreneur start, park, and resume **multiple business concepts** without
creating a second profile — and without weakening the existing single-concept behaviour, RLS, gamification, or
the in-flight Budget/Runway feature.

> **This is a spine change, not a bolt-on.** It moves "concept" out of the profile and makes it a first-class
> child. Sequence it carefully, behind its own branch/PRs, with a zero-data-loss backfill. Build it **after**
> the Budget/Runway plan lands or in coordination with it (they are forward-compatible — see §12).

---

## 1. Owner decisions (locked)

| # | Decision | Choice |
|---|---|---|
| D1 | Concurrency | **Sequential — park & start.** Exactly **one active concept at a time**; the founder parks/archives the current concept to start (or resume) another. No two concepts active at once. |
| D2 | Gamification scope | **Streak person-level, milestones per-concept.** One daily streak for the founder; each concept earns its **own** milestones (so `plan_submitted` / `budget_built` fire once *per concept*). |
| D3 | Idea-validation gate | **Per concept.** Each new concept passes its own advisor idea-validation gate before *its* plan builder unlocks. A strong concept #1 does not auto-bless concept #2. |

---

## 2. The problem (current binding — evidenced)

The model is singular at three layers today:

1. **One profile per person.** `profileFor()` → `EntrepreneurProfile::where('user_id', …)->firstOrFail()`
   ([EntrepreneurPlanController.php:442](app/Http/Controllers/Portal/EntrepreneurPlanController.php:442)).
2. **One entrepreneur plan per profile (hard lock).** `start()` → `createOrUpdateForEntrepreneur()` →
   `firstOrNew(['entrepreneur_profile_id' => …, 'source_type' => SOURCE_ENTREPRENEUR])`
   ([Plans/PlanBuilder.php:77](app/Services/Plans/PlanBuilder.php:77)). Pressing "start" again **updates the
   same row** — it can never create a second concept. The portal reads a single `latestPlan(profile)`
   ([EntrepreneurPlanController.php:451](app/Http/Controllers/Portal/EntrepreneurPlanController.php:451)).
3. **Concept-level state lives on the profile.** `stage` + `concept_summary` are columns on
   `entrepreneur_profiles`
   ([2026_05_21_200000_create_entrepreneur_profiles_table.php:22-23](database/migrations/2026_05_21_200000_create_entrepreneur_profiles_table.php)).
   A second concept has a different stage and summary, so these cannot stay profile-level.

**One seam is already plural:** `ideaValidations()` is `hasMany`
([EntrepreneurProfile.php:61](app/Models/EntrepreneurProfile.php:61)) — the funnel already floats several
ideas, but only the latest gate-passed one unlocks the single plan, so everything downstream collapses to one
concept.

**Answer:** No, the app does **not** currently make provision for multiple concepts. This plan adds it.

---

## 3. The model

```
entrepreneur_profiles  ── the PERSON ──────────────────────────────────────────────
  identity, email, assigned_advisor_id, invite_token_id
  gamification_on, current_streak, last_active_at          (person-level — UNCHANGED)
  │
  └── entrepreneur_concepts  ── the CONCEPT (NEW) ── hasMany, ≤1 active ────────────
        label, summary (was concept_summary), stage (was profile.stage),
        status: active | parked | archived | converted, sequence, started_at, parked_at
        │
        ├── idea_validations            (re-pointed: + entrepreneur_concept_id)  — gate is PER CONCEPT (D3)
        ├── readiness_assessments       (re-pointed: + entrepreneur_concept_id)
        ├── business_plans              (+ entrepreneur_concept_id; firstOrNew now keys on CONCEPT)
        │     ├── plan_phases / plan_sections          (unchanged — hang off the plan)
        │     ├── entrepreneur_budgets                 (unchanged — keys on business_plan_id ⇒ per-concept free)
        │     └── assessments                          (unchanged — hang off the plan)
        └── entrepreneur_milestone_awards (+ nullable entrepreneur_concept_id) — milestones PER CONCEPT (D2)

entrepreneur_streak_events  ── stays PERSON-level (no concept_id) — streak is the founder's habit (D2)
```

**Why profile = person, plan/concept = the testable unit:** the `BusinessPlan` is *already* the de-facto
concept anchor — budget and assessments hang off the plan, not the profile. The only things forcing
singularity are the `firstOrNew` singleton and the profile-level `stage`/`concept_summary`. So we promote the
concept to its own row and re-point the per-concept children; identity, advisor assignment, and the daily
streak stay on the person.

---

## 4. Schema

### 4.1 New table `entrepreneur_concepts`
```
id                       uuid pk default gen_random_uuid()
entrepreneur_profile_id  uuid  FK → entrepreneur_profiles  cascadeOnDelete
label                    string         -- founder-facing concept name ("Mobile coffee cart")
summary                  text nullable  -- migrated from entrepreneur_profiles.concept_summary
stage                    string(40)     -- migrated from entrepreneur_profiles.stage (EntrepreneurStage)
status                   string(20) default 'active'   -- active | parked | archived | converted
sequence                 unsignedSmallInteger default 1 -- display order / "Concept 1, 2, 3…"
started_at               timestampTz
parked_at                timestampTz nullable
archived_at              timestampTz nullable
timestampsTz
index (entrepreneur_profile_id, status)
index (entrepreneur_profile_id, sequence)
```

**One-active invariant (DB-enforced, drift-free):**
```sql
CREATE UNIQUE INDEX entrepreneur_concepts_one_active_per_profile
    ON entrepreneur_concepts (entrepreneur_profile_id)
    WHERE status = 'active';
```
This makes "≤1 active concept per founder" (D1) a database guarantee, not an app convention — there is **no
`active_concept_id` pointer on the profile** to drift. "The active concept" is *derived*: the row where
`status = 'active'`. (Same anti-drift stance used in the Budget plan's RLS.)

### 4.2 Re-point the per-concept children (additive columns + backfill, then constrain)
Each gains `entrepreneur_concept_id`:

| Table | Column | Final state |
|---|---|---|
| `idea_validations` | `entrepreneur_concept_id uuid FK` | NOT NULL after backfill (every validation belongs to a concept) |
| `readiness_assessments` | `entrepreneur_concept_id uuid FK` | NOT NULL after backfill |
| `business_plans` | `entrepreneur_concept_id uuid FK nullable` | nullable (client/DD plans have no concept); **`firstOrNew` now keys on `entrepreneur_concept_id`** |
| `entrepreneur_milestone_awards` | `entrepreneur_concept_id uuid FK nullable` | **nullable** — concept milestones set it; any future person-level milestone leaves it null |

> **`business_plans` keeps `entrepreneur_profile_id`.** It predates concepts and is the *existing RLS anchor*
> shared with the client/DD lanes (`client_id`, `dd_engagement_id`). Removing it to force a concept hop would
> be gratuitous churn on a security-critical, multi-lane policy. So we **keep** it as the anchor (existing
> `business_plans` / `plan_phases` / `plan_sections` / `entrepreneur_budgets` RLS stays byte-for-byte
> unchanged) and add `entrepreneur_concept_id` purely for grouping + the singleton key. This is **not** the
> denormalisation the Budget plan rejected: there the row had *no* independent need for a profile id; here the
> anchor already exists and is load-bearing. The drift risk is closed by a consistency guard (§4.4).

### 4.3 Gamification award uniqueness — make per-concept
The award uniqueness indexes
([2026_06_23_010000_add_entrepreneur_gamification.php:166-173](database/migrations/2026_06_23_010000_add_entrepreneur_gamification.php))
must include the concept so the *same* milestone can be earned once **per concept**:
```sql
DROP INDEX entrepreneur_milestone_awards_singleton_unique;
-- COALESCE so person-level (null concept) milestones still de-dupe per profile:
CREATE UNIQUE INDEX entrepreneur_milestone_awards_singleton_unique
    ON entrepreneur_milestone_awards
       (entrepreneur_profile_id, COALESCE(entrepreneur_concept_id, '00000000-0000-0000-0000-000000000000'::uuid), milestone_key)
    WHERE milestone_key <> 'grade_up';
-- grade_up already de-dupes on evidence_source_id (the assessment), which is concept-scoped — leave as-is,
-- but ADD entrepreneur_concept_id to its index for clarity/safety.
```
Add `entrepreneur_concept_id` to the **immutability trigger's** forbidden-change list
(`fsa_entrepreneur_milestone_awards_guard`, lines 184-196) so a concept-stamp can't be re-pointed after award.

### 4.4 Consistency guard (closes the §4.2 denormalisation)
`BEFORE INSERT OR UPDATE` on `business_plans`: if `entrepreneur_concept_id IS NOT NULL`, assert the concept's
`entrepreneur_profile_id` equals the row's `entrepreneur_profile_id`. Reject mismatch (`ERRCODE P0001`). App
code sets `entrepreneur_profile_id = concept.entrepreneur_profile_id` automatically in
`createOrUpdateForEntrepreneur`, so the guard only ever fires on a bug.

---

## 5. Backfill (zero data loss — every existing founder gets exactly one concept)

In the same migration, **after** creating `entrepreneur_concepts` and adding the nullable columns, **before**
the NOT NULL / FK tightening:

1. For each existing `entrepreneur_profiles` row, insert **one** concept:
   `label = 'Concept 1'`, `summary = profile.concept_summary`, `stage = profile.stage`,
   `status = 'active'`, `sequence = 1`, `started_at = profile.created_at`.
2. Stamp the founder's existing children with that concept id:
   `idea_validations`, `readiness_assessments`, `business_plans` (the entrepreneur-source row),
   `entrepreneur_milestone_awards` — all `WHERE entrepreneur_profile_id = <profile>` get
   `entrepreneur_concept_id = <new concept>`.
3. Tighten: `idea_validations.entrepreneur_concept_id` and `readiness_assessments.entrepreneur_concept_id`
   → NOT NULL; add all FKs.
4. **Drop `entrepreneur_profiles.stage` + `concept_summary`** (they now live on the concept). **First drop the
   `['assigned_advisor_id','stage']` index** that covers `stage`
   ([profiles migration :27](database/migrations/2026_05_21_200000_create_entrepreneur_profiles_table.php:27)),
   and add the equivalent index on `entrepreneur_concepts` (e.g. `(entrepreneur_profile_id, status, stage)`) for
   the capacity query (§7). Do the column drop only once the readers (§7) are switched — a **two-migration
   sequence**: add+backfill first, drop after the code WOs.

Run on Postgres only (guard with the existing `getDriverName() === 'pgsql'` pattern). **Never** `migrate:fresh`
against the dev DB `futureshiftadvisory_db`.

---

## 6. RLS

- **`entrepreneur_concepts`** — new policy, mirror the `idea_validations` shape
  ([2026_05_23_081000_create_idea_validations_table.php:54-78](database/migrations/2026_05_23_081000_create_idea_validations_table.php)):
  `super_admin/system` OR the concept's profile has `assigned_advisor_id = current_user` OR
  `user_id = current_user`. `ENABLE` + `FORCE` RLS.
- **`business_plans` / `plan_phases` / `plan_sections` / `entrepreneur_budgets`** — **unchanged**, because
  `business_plans` keeps `entrepreneur_profile_id` as the anchor (§4.2).
- **`idea_validations` / `readiness_assessments` / `entrepreneur_milestone_awards`** — **unchanged** (still
  anchored on `entrepreneur_profile_id`; the added `entrepreneur_concept_id` is grouping only).

Net RLS work: **one new policy** (the concept table). Everything else is preserved — the deliberate payoff of
keeping the profile anchor.

---

## 7. The blast radius — `stage` & `concept_summary` become per-concept

The grep for `EntrepreneurStage|concept_summary|->stage` returns **22 files**, **but ~3 of those hits are the
*panel-referral* `->stage`** — a **different** column, **not** in this blast radius:
`Advisor\PartnerPanelController:612` (`$referral->stage`), `Panels\ReferralConsentManager:98`
(`$referral->stage`), and the `$referral->stage` lines in `Panels\ReferralLifecycle`. **Exclude them.** The
genuine entrepreneur `stage`/`concept_summary` sites (~16) must read the **active concept** (or, in advisor
list views, the relevant concept) after the move.

**Model methods move too — not just column reads.** `EntrepreneurProfile` carries five `stage` helpers:
`currentStage()`, `currentStageValue()`, `currentStageLabel()`, `ensureStageIsValid()`, and the private
`rawStageValue()` ([EntrepreneurProfile.php:26-52,134-149](app/Models/EntrepreneurProfile.php:26)). These
**move to `EntrepreneurConcept`**; call sites such as `$profile->currentStage()` /
`$profile->ensureStageIsValid()` (e.g. `Advisor\EntrepreneurController:281`, `AdvisoryConversion:43`) resolve
through the active/selected concept.

**Rule for Codex:** add `EntrepreneurProfile::activeConcept()` (a `hasOne` filtered to `status='active'`) and
`concepts()` (`hasMany`); move the five stage helpers to the concept; replace
`profile->stage`/`profile->concept_summary` reads with the concept. Where a reader needs a *specific* concept
(plan workspace, an assessment), pass it explicitly rather than assuming the active one.

**Verified entrepreneur stage/concept_summary sites** (grep again before finishing — `DashboardController` /
`CalendarController` matched the file-level grep but their exact usage is unconfirmed): `EntrepreneurPlanController`
(stage write on submit :382; concept_summary :475), `EntrepreneurDashboardController` (:72),
`Advisor\EntrepreneurController` (creates + reads + `currentStage`/`profileStageLabel`),
`Advisor\EntrepreneurActionController`, `Services\Entrepreneurs\Guidance`, `AdvisoryConversion`,
`AdvisoryReadiness`, `IdeaValidationService`, `Readiness`, `Benchmarking`, `AdvisorEntrepreneurCapacity`,
`EntrepreneurGamification`, `EntrepreneurInviteReconciler` (creates), `Learning\Layers\PlanQualityBenchmarks`,
`Auth\InviteAcceptController`, `Panels\ReferralLifecycle` (creates — reverse referral).

> **Capacity index moves (see §5).** `AdvisorEntrepreneurCapacity` runs `whereIn('stage', activeCapacityValues())`
> ([:26](app/Services/Entrepreneurs/AdvisorEntrepreneurCapacity.php:26)), backed by the
> `entrepreneur_profiles(['assigned_advisor_id','stage'])` index. After the move it counts by **active-concept
> stage** and the index moves to `entrepreneur_concepts` — confirm the aggregate semantics with the owner before
> changing how "stage" counts appear on the advisor dashboard.

---

## 8. Concept lifecycle (the new service)

New `EntrepreneurConcepts` service (sibling of `PlanBuilder`), all transitions audited:

- **`start(profile, label)`** — in a transaction: if an active concept exists, **park it** (`status=parked`,
  `parked_at=now`), then insert the new concept `status=active`, `sequence = max+1`. The one-active partial
  unique index (§4.1) backstops it. Audit `entrepreneur.concept_started`.
- **`resume(profile, concept)`** — park the current active (if any), set the target `status=active`,
  clear/keep `parked_at`. Audit `entrepreneur.concept_resumed`.
- **`park(profile, concept)`** — `status=parked`, `parked_at=now`. Audit `entrepreneur.concept_parked`.
- **`archive(profile, concept)`** — `status=archived`, `archived_at=now` (terminal; data retained, hidden by
  default). Audit `entrepreneur.concept_archived`.
- **No-active state is valid** — all concepts parked/archived ⇒ portal prompts "resume a concept or start a
  new one" rather than erroring.

**Profile creation seeds concept #1 — at all three creation sites.** EntrepreneurProfiles are created in
**three** places, each setting `stage` + `concept_summary` today: `Advisor\EntrepreneurController::store`
(the **primary** advisor path, [:112](app/Http/Controllers/Advisor/EntrepreneurController.php:112)),
`EntrepreneurInviteReconciler` ([:124](app/Services/Entrepreneurs/EntrepreneurInviteReconciler.php:124)), and
`Panels\ReferralLifecycle` (reverse referral, [:218](app/Services/Panels/ReferralLifecycle.php:218)).
**Centralise** profile+concept creation so concept #1 (active, stage/summary seeded) is created by one path all
three call — otherwise a profile created via any site has no concept and the portal renders empty.

**Plan builder unlock is now per concept (D3):** `IdeaValidationService::planBuilderUnlocked` and
`PlanBuilder::start` check the **active concept's** gate-passed idea-validation, not the profile's. The
singleton `firstOrNew` keys on `entrepreneur_concept_id` (§4.2).

**Conversion is per concept — and must stop auto-picking "latest plan" (C7).** `AdvisoryConversion` today
resolves the source plan as the profile's `latest('updated_at')` BusinessPlan
([:27-30](app/Services/Entrepreneurs/AdvisoryConversion.php:27)) and stamps the Client's `registry_sources`
with `concept_summary` / `stage_at_conversion` read from the profile
([:42-43](app/Services/Entrepreneurs/AdvisoryConversion.php:42)). With multiple concepts that "latest plan" is
**ambiguous** (it could belong to a different concept), so conversion must take the **selected concept** and
convert *its* plan, recording `entrepreneur_concept_id` + the concept's summary/stage. Converting one concept
sets it `status=converted` and leaves the founder's others intact.

---

## 9. Gamification (per D2)

- **Streak — person-level, unchanged.** `entrepreneur_streak_events` keeps no concept id; a writing day on
  *any* concept advances the founder's one streak. `current_streak`/`last_active_at` stay on the profile.
  (The separate daily streak-reset cron gap from the gap analysis is still open — out of scope here.)
- **Milestones — per concept.** Awards carry `entrepreneur_concept_id`; the unique index + immutability
  trigger updated (§4.3). `EntrepreneurMilestones` stamps the concept when awarding `plan_started`,
  `plan_submitted`, `budget_built`, `grade_up`, etc., so each concept earns its own. Person-level milestones
  (if any are ever added) leave the concept id null and de-dupe per profile via the `COALESCE` index.

---

## 10. Portal & advisor UX

- **Founder portal:** a concept context (active concept name + a small "Concepts" menu) with **Park & start
  another** and **Resume** actions. The plan/readiness/budget workspaces all render the **active** concept.
  Parked/archived concepts are listed read-only with a Resume control.
- **Advisor portfolio:** concepts are **nested under the one founder**, never separate "people." The advisor
  sees the founder's active concept by default and can expand to parked ones. The idea-validation gate queue
  shows **per-concept** gate items.

---

## 11. Testing

- **Backfill:** every pre-existing profile yields exactly one active concept; all its children carry that
  concept id; no orphans; `stage`/`summary` values preserved 1:1.
- **One-active invariant:** inserting/activating a second active concept for the same profile is rejected by
  the partial unique index; `start()` parks the previous one atomically.
- **Per-concept gate (D3):** concept B's plan builder stays locked until B's *own* idea-validation gate passes,
  even when concept A is fully gated/submitted.
- **Per-concept milestones (D2):** `budget_built`/`plan_submitted` can be earned once for A **and** once for B;
  a second award for the *same* concept+key is rejected; the immutability trigger blocks re-pointing
  `entrepreneur_concept_id`.
- **Person-level streak (D2):** a writing day on concept B advances the same `current_streak` as concept A —
  one founder, one streak.
- **RLS / auth:** owner reads/writes own concepts; assigned advisor reads them; **founder B cannot see/write
  founder A's** concepts; an SME client / client-scope user gets **no** access through the entrepreneur routes;
  existing `business_plans`/budget RLS still green (anchor unchanged).
- **Consistency guard:** a `business_plan` whose `entrepreneur_concept_id` belongs to a different profile is
  rejected.
- **Concept #1 at every creation site:** a profile created via the advisor `EntrepreneurController::store`,
  `EntrepreneurInviteReconciler`, **and** `ReferralLifecycle` (reverse referral) each ends up with exactly one
  active concept — the portal never renders an empty profile.
- **Conversion picks the right concept:** with two concepts each owning a plan, `AdvisoryConversion` converts
  the **selected** concept's plan (not the profile's most-recently-updated plan) and stamps the Client with
  that concept's id/summary/stage.
- **No regression:** entrepreneur plan/readiness/assessment/gamification/budget suites stay green. Static
  gates: `pint --test`, `tsc --noEmit`, ESLint, Prettier.

---

## 12. Interaction with the in-flight Budget/Runway plan

**Forward-compatible — no rework.** `entrepreneur_budgets` keys on `business_plan_id` (unique), so once a
profile owns multiple plans, **budget is per-concept for free** — `expected_runway_months`, `status`,
flags, and the scoring tie-in all stay per-plan = per-concept. The only touch-point: the `budget_built`
milestone gains a concept id (§9), exactly per D2. Add a one-line cross-reference in
[PLAN-ENTREPRENEUR-BUDGET-RUNWAY.md](PLAN-ENTREPRENEUR-BUDGET-RUNWAY.md) noting budget is per-concept.

---

## 13. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **C1** | `entrepreneur_concepts` table + backfill (add phase) | Migration: create table (+ one-active partial unique + RLS), add nullable `entrepreneur_concept_id` to the four children, backfill one concept per profile and stamp children. **Does not** drop profile columns yet. |
| **C2** | Model + active-concept resolution | `EntrepreneurConcept` model; `EntrepreneurProfile::concepts()` + `activeConcept()`; `EntrepreneurConcepts` lifecycle service (`start`/`resume`/`park`/`archive`, all audited); **one centralised profile+concept creation path** called by all three creation sites (advisor `EntrepreneurController::store`, `EntrepreneurInviteReconciler`, `ReferralLifecycle`) so concept #1 is always seeded. |
| **C3** | Re-point the singleton + gate | `createOrUpdateForEntrepreneur` keys on `entrepreneur_concept_id`; `PlanBuilder::start` + `IdeaValidationService::planBuilderUnlocked` use the active concept's gate; consistency guard trigger (§4.4). |
| **C4** | Reader migration (~16 entrepreneur-stage sites) | Move the five `EntrepreneurProfile` stage helpers (`currentStage`/`currentStageValue`/`currentStageLabel`/`ensureStageIsValid`/`rawStageValue`) to `EntrepreneurConcept`; replace `profile->stage`/`concept_summary` reads with the active/selected concept (**exclude** the panel-referral `->stage` files — `PartnerPanelController`, `ReferralConsentManager`, `ReferralLifecycle`'s `$referral->stage`); advisor capacity semantics confirmed with owner; then the **second migration** drops the `['assigned_advisor_id','stage']` index + `entrepreneur_profiles.stage`/`concept_summary` and adds the equivalent index on `entrepreneur_concepts`. |
| **C5** | Gamification per-concept | Award `entrepreneur_concept_id` + updated unique indexes + immutability-trigger column; `EntrepreneurMilestones` stamps the concept; streak untouched. |
| **C6** | Portal + advisor UX | Concept context + Park/Start/Resume in the founder portal; nested concepts + per-concept gate queue in advisor views. |
| **C7** | Conversion per concept | `AdvisoryConversion` takes the **selected concept** (stop auto-resolving the profile's `latest('updated_at')` plan, :27-30); convert *its* plan; record `entrepreneur_concept_id` + concept summary/stage in the Client `registry_sources` (:42-43); set the concept `status=converted`; founder retains other concepts. |

Sequence: C1 → C2 → C3 → C5 (parallel-ish with C6) → C4 (ends with the column-drop migration) → C7. One WO
per branch/PR per the repo rule.

---

## 14. Risks / honesty

- **Spine refactor.** This touches identity-adjacent state and the plan singleton. The risk is the reader
  migration (C4) missing a `profile->stage` site — grep again at the end; the column drop will surface any
  missed reader as a hard error (intended).
- **Two-migration column drop.** `stage`/`concept_summary` can only be dropped *after* C4. If C4 slips, the
  add+backfill (C1) is still safe to ship alone (columns merely duplicated onto the concept).
- **Advisor "stage" aggregates** change meaning (active-concept stage). Confirm intent before altering
  dashboard counts (§7).
- **No-active-concept state** must be handled everywhere the portal assumes a concept exists (today it always
  does). Treat "no active concept" as a first-class empty state.
- **Scope guard:** this is net-new capability beyond `PLAN.md`. Per the repo's "no feature outside the plan"
  rule, this doc *is* that plan — raise it in the PR description and link this file.

---

## 15. CLAUDE.md block (add on build)

> **Entrepreneur concepts.** A founder is one `EntrepreneurProfile` (identity, advisor, streak) with many
> `entrepreneur_concepts`; **at most one is `active`** (DB partial unique index — no `active_concept_id`
> pointer). `stage` and `summary` live on the **concept**, not the profile. The entrepreneur `BusinessPlan`
> singleton keys on `entrepreneur_concept_id` (one plan per concept); `business_plans` keeps
> `entrepreneur_profile_id` as its RLS anchor. The idea-validation **gate is per concept**; the daily
> **streak is person-level**; **milestones are per concept** (awards carry `entrepreneur_concept_id`).
> Concept lifecycle (start/resume/park/archive/convert) goes through `EntrepreneurConcepts` and is audited.

---

## 16. Out of scope

- Parallel/simultaneously-active concepts (D1 chose sequential).
- Per-concept streaks (D2 chose person-level).
- The daily streak-reset cron (separate gap-analysis item #0).
- Cross-concept analytics / "compare my concepts" dashboards.
- Any change to the client/DD plan lanes.

---

*Companion to [PLAN-ENTREPRENEUR-BUDGET-RUNWAY.md](PLAN-ENTREPRENEUR-BUDGET-RUNWAY.md),
[PLAN-ENTREPRENEUR-GAMIFICATION.md](PLAN-ENTREPRENEUR-GAMIFICATION.md), and
[PLAN-ENTREPRENEUR-AI-ISOLATION.md](PLAN-ENTREPRENEUR-AI-ISOLATION.md). Produced at owner request — "the app
should allow founders to start another idea/concept."*
