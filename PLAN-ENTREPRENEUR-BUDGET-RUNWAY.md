# Future Shift Advisory — Budget & Runway Builder Plan

**Source:** `FSA_Budget_Runway_Builder_Spec_v1.0.docx` (Functional Spec v1.0, June 2026) — a new **structured
financial component** for the entrepreneur Business Plan Builder: launch + operating costs, a 12-month
revenue/cash-flow forecast, funding reconciliation, runway & break-even, a runway cross-check against the
self-reported readiness answer, a presentation-only Advisor Cost Impact Line, scoring integration, and three
charts. Read the spec for exhaustive field/flag/copy detail — this plan maps it onto the **existing Laravel
entrepreneur module** and is the build brief for **Codex**.
**Track scope:** additive financial component inside the existing plan flow. It must not weaken existing
entrepreneur / plan / assessment / gamification behaviour, and it is governed by the **AI Integrity
Principle** (CLAUDE.md §3) — it exists to replace self-reported runway with *checked arithmetic*.
**Predecessors:** entrepreneur module (incl. the just-shipped gamification + AI content-isolation guard,
`7586ba37`). Builds on `PlanRequirements`, `BusinessPlan`/`PlanSection`, `Assessment` +
`RatingFramework`/`RatingFrameworkManager`, `Guidance`, `ReadinessAssessment`, `FeeCalculator`,
`EntrepreneurPromptRegistry`, Recharts + Meridian Warm tokens.
**Multi-concept forward-compat:** `entrepreneur_budgets` keys on `business_plan_id` (unique), so when
[PLAN-ENTREPRENEUR-MULTI-CONCEPT.md](PLAN-ENTREPRENEUR-MULTI-CONCEPT.md) makes a founder own multiple plans,
**budget is per-concept for free** — no rework. The only touch-point is the `budget_built` milestone gaining
an `entrepreneur_concept_id` (per-concept milestones).
**Work orders:** **WO-B1 … WO-B7** (budget namespace).
**Sequencing:** B1 (schema + calc engine) → B2 (builder UI + autosave) → B3 (flags + runway cross-check) →
B4 (advisor cost line) → B5 (charts) → B6 (scoring + framework recalibration) → B7 (AI Assist nudge +
gamification tie-in). Each its own green commit on `featureApp`; `main` fast-forwards only when green
(`-d memory_limit=2048M`).
**Plan version:** 1.23 — admin-maintained rubric + owner budget-rubric weights/descriptors supplied + twenty code-grounded review passes. *(Build target: Codex, into the test env, then push to live.)*

> **v1.23 revision (owner direction — rubric must be admin-maintainable over time).** Add a dedicated Admin
> rubric management surface for the entrepreneur `RatingFramework`: Admin can view the current published rubric,
> inspect prior versions, draft changes to criteria/weights/descriptors/grade bands, validate weights = 100, and
> publish a new version with audit/history. Published frameworks are never edited in place; old assessments keep
> their original `rating_framework_id`, while new assessments use the latest published version. Layer 18 learning
> updates may propose changes, but Admin approval/publish remains the business control (§6, §11, B6).

> **v1.22 revision (owner rubric update — Budget descriptors approved).** The owner approved the standard
> descriptor pattern for criterion #12 **Budget**, so B6 now has concrete production seeder values: name
> `Budget`, weight `12%`, the 12-weight set from v1.21, and the standard four grade-band descriptors.

> **v1.21 revision (owner rubric update — budget criterion weights supplied).** The owner supplied the official
> 12-criterion weight set from the rubric image: #12 is **Budget** at **12%**; the existing criteria are reweighted
> to `8 / 7 / 8 / 8 / 9 / 8 / 8 / 7 / 9 / 8 / 8 / 12`, totaling 100. B6's seeder is no longer blocked on the
> criterion name or weights. The only remaining rubric confirmation is the **Budget grade-band descriptor text**:
> either use the same standard descriptor pattern as the current 11 criteria, or provide bespoke
> `exceptional` / `strong` / `developing` / `needs_work` wording (§6, §13.2, B6).

> **v1.20 revision (review pass — whole-report refusal + advisor gap-resolution).** Two fixes:
> (P1) **ReportComposer refuses the whole report on gaps** — the report is built around a *required* `PvCalculation`
> ([:1159](app/Services/Reports/ReportComposer.php:1159)), so the gate sits at the top ([:312-314](app/Services/Reports/ReportComposer.php:312))
> and creates **no** Report/PV/sections/PDF/audit while `dataGaps` exist — not just "skip the PV" (§6, B6, §11).
> (P2) **Advisor scoring resolves a gap** — an advisor row (`criterion_number` + numeric `score`) clears a
> missing-score gap; metadata defaults to `complete`/`1.0`/null when the AI row has none, but **keeps explicit
> AI excluded/partial metadata** when present (§6, B6, §11).

> **v1.19 revision (review pass — the sentinel's transition + payload override).** Two fixes:
> (P1) **`finalise()` must do the `incomplete`→band transition** — today it only timestamps + awards milestones
> ([Assessment.php:151-164](app/Services/Entrepreneurs/Assessment.php:151)); it must recompute a gap-free
> `ScoringResult`, assert no `dataGaps`, and persist the real band **before** the milestone snapshot
> ([EntrepreneurMilestones:329](app/Services/Entrepreneurs/EntrepreneurMilestones.php:329)) (§6, B6, §11).
> (P2) **Payload must not override the sentinel** — `BuildsEntrepreneurAssessmentPayload` recomputes
> `gradeFor(sum(contribution))` ([:20-27](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:20)),
> shown on dashboard/advisor cards; it must return `overall_grade='incomplete'` + `dataGaps` when gaps exist so
> cards match the raw column (§6, B6, §11).
> **Plus — `overall_grade` write/read map** added to §6 (code-derived): the three column writers, the gated
> report-content embeddings, and the payload/raw readers — every grade site enumerated and covered, so "missed a
> grade site" is answered from a grep.

> **v1.18 revision (review pass — the gap state's last two leak paths).** Two fixes:
> (P1) **Incomplete grade can't leak as authoritative** — `firstPass()` persists `overall_grade = 'incomplete'`
> (sentinel, not a band) while `dataGaps` exist ([Assessment.php:66](app/Services/Entrepreneurs/Assessment.php:66)),
> so raw-grade displays (`EntrepreneurDashboardController:85`, `EntrepreneurPlanController:559`) show *Incomplete*
> rather than a grade computed over dropped criteria (§6, B6, §11).
> (P2) **ReportComposer reclassified as a write path** — it creates the Concept PV ([:316-320](app/Services/Reports/ReportComposer.php:316))
> and overwrites `overall_grade`/`concept_pv_calculation_id` ([:322-326](app/Services/Reports/ReportComposer.php:322));
> it now **skips PV-creation + grade persistence on gaps**, not treated as display (§6, B6, §11).

> **v1.17 revision (review pass — gap gate reaches all consumers; legacy advisor shapes).** Two fixes:
> (P1) **Data-gap gate generalised beyond `finalise()`** — `LivingPlan::reassess()` runs `AdvisoryReadiness::evaluate()`
> on a fresh pre-finalise assessment ([LivingPlan.php:89](app/Services/Entrepreneurs/LivingPlan.php:89),
> [AdvisoryReadiness.php:32](app/Services/Entrepreneurs/AdvisoryReadiness.php:32)), so **every decision consumer**
> (finalise, readiness evaluate, living-plan reassess, grade-up, benchmarking) skips/blocks on `dataGaps`, not
> just finalisation (§6, B6, §11).
> (P2) **Legacy `advisor_scores` migrated too** — the two fixtures also store summary advisor shapes
> (`{overall, note}`, `TestingSeedDataSeeder:1358` / `GamificationTest:154`); B6 migrates/drops them, and the
> helper's `criterion_number`-keyed advisor lookup ignores a stray summary blob (§6, B6, §11).
> **Plus — exhaustive consumer map** added to §6 (code-derived): every weighted-score computer, every decision
> consumer that gates on `dataGaps` (incl. the newly-spotted `Revision` deltas), every display consumer, **and
> the explicit exclusions** (`NpoHealthScorer`, `DataQuality*`, unrelated `evaluate()`s) — so "you missed a call
> site" is now answered from a grep, not reactively.

> **v1.16 revision (review pass — concrete helper contract).** Three fixes:
> (P1) **Data-gap behaviour made concrete** — the helper returns `ScoringResult { overall, rows[], dataGaps[] }`;
> a gap criterion is dropped from `overall` (not zeroed) and listed in `dataGaps`; **`Assessment::finalise()`
> blocks** while gaps remain; readers read `->overall` (drop-in for the float). No more vague "surfaced" (§6, B6, §11).
> (P2) **Legacy fixtures resolved** — the two **non-canonical** shapes (`TestingSeedDataSeeder:1352` name-keyed,
> `GamificationTest:153` bare list) are **migrated to canonical rows** in B6; the `complete`/`1.0`/null default
> covers only canonical-but-metadata-less rows (§6, B6, §11).
> (P2) **`contribution` formula corrected** — `score × (effective_weight / 100)` (percentages sum to 100),
> matching `BuildsEntrepreneurAssessmentPayload:81`; not `score × effective_weight` (100× too large) (§6, B6, §11).

> **v1.15 revision (review pass — the weighting contract's output shape + gap/default boundary).** Two fixes:
> (P1) **Default vs data-gap tightened** — the `complete`/`1.0`/null default fires only on a **valid** score
> row; a missing/malformed score identity or value stays a **reported data gap** (not a silent full-weight
> default), resolving the clash with the data-gap rule (§6, B6, §11).
> (P2) **Helper returns normalised rows, not just an overall score** — payload/report render per-criterion
> rows from raw `$criterion->weight` ([BuildsEntrepreneurAssessmentPayload:74](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:74)),
> so the helper must expose `rows[]` (`effective_weight`/`contribution`/`score_source`/`excluded_reason`) that
> the UI consumes — else an excluded/partial budget criterion shows stale full-weight rows (§6, B6, §11).

> **v1.14 revision (review pass — the weighting contract meets advisor overrides + legacy rows).** Two fixes,
> both consequences of the v1.13 persisted-metadata contract:
> (P1) **Advisor overrides keep the AI row's weighting** — `adjustScore()` writes `advisor_scores` with no
> metadata ([Assessment.php:83-103](app/Services/Entrepreneurs/Assessment.php:83)) and readers prefer it
> ([AdvisoryReadiness.php:86](app/Services/Entrepreneurs/AdvisoryReadiness.php:86)); the helper merges the
> advisor **score** with the AI row's **frozen metadata** (single-source, no duplication) (§6, B6, §11).
> (P2) **Legacy/fixture default** — metadata-less rows (e.g. `TestingSeedDataSeeder`
> [:1352](database/seeders/TestingSeedDataSeeder.php:1352)) default to `complete`/`1.0`/null, so existing
> assessments/non-budget criteria are unaffected (no backfill; only budget-behaviour fixtures updated) (§6, B6, §11).

> **v1.13 revision (review pass — persisted weighting contract + seeder identity guard).** Two fixes:
> (P1) **Persisted score-row contract** — downstream readers recompute from `plan_assessments.ai_scores` (no
> live budget; [migration:19](database/migrations/2026_05_23_086000_create_plan_assessments_table.php:19),
> `AdvisoryReadiness::score` [:79-93](app/Services/Entrepreneurs/AdvisoryReadiness.php:79)), so each score row
> must persist `weight_status`/`effective_weight_multiplier`/`excluded_reason`; the helper renormalises from the
> snapshot — making excluded≠failed and old rounds replayable (§6, B6, §11).
> (P2) **Seeder idempotency on identity, not count** — `rating_criteria` has no stable key
> ([migration:33](database/migrations/2026_05_23_085000_create_rating_framework_tables.php:33)); guard on the
> budget criterion's `number=12`+name/marker and **never downgrade** a newer (13-criterion) framework (§6, B6, §11).

> **v1.12 revision (review pass — learning-layer readers, seed audit, the one real blocker).** Three fixes:
> (P1) **Three learning-layer readers added to the shared-helper scope** — `RatingPredictiveValidity::planScore`
> ([:150](app/Services/Learning/Layers/RatingPredictiveValidity.php:150)), `ConversionOutcomeLearning::planScore`
> ([:127](app/Services/Learning/Layers/ConversionOutcomeLearning.php:127)), `PlanQualityBenchmarks::score`
> ([:161](app/Services/Learning/Layers/PlanQualityBenchmarks.php:161)) each do `criteria->sum(score×weight/100)`;
> reader count is now **ten** (§6, B6, §11).
> (P2) **Seeder audit** — the direct-create baseline seeder records a **seed-time `AuditWriter` event (system/
> null actor)**, satisfying the "framework revised → audited" rule (line 207) without a `User` (§6, B6).
> (P2) **⛔ Real blocker surfaced** — the seeder's criterion #12 descriptors + recalibrated 12-weight set are
> owner-set rubric values (governed, not inventable). B6's seeder is **marked blocked on §13.2**; its mechanics
> stay buildable (§6, §13.2, B6).

> **v1.11 revision (review pass — three edge-case seams).** Three fixes:
> (P2) **`expected_runway_months = 0` is valid** — `nullable integer min:0`; "present" means `!== null`, never
> truthiness, so a truthful zero reaches the `complete` gate and Flag 1 (§3, §11).
> (P2) **Missing budget row = `not_started`** — legacy/un-opened plans have no row; treated as `not_started`
> (incomplete, criterion excluded, scorer null-safe); **no eager backfill**, row created lazily (§3, §11).
> (P2) **Seeder actor/idempotency** — `BudgetCriterionRevisionSeeder` mirrors `FoundingRatingFrameworkValuesSeeder`:
> **direct create, no actor**, idempotent early-return, wired into `DatabaseSeeder` + test seed — not the
> actor-requiring `revise()`/`publish()` (§6, B6).

> **v1.10 revision (review pass — three integration seams traced to code).** Three fixes:
> (P1) **Scorer must receive the budget data** — `Assessment::scoreCriterion()` + `heuristicScore()` see only
> `sections_text` today ([:166-218](app/Services/Entrepreneurs/Assessment.php:166)); B6 passes a structured
> `budget_evidence` block (computed/status/`expected_runway_months`/flags) for the budget criterion, else it
> scores blind (§6, B6, §11).
> (P2) **Seeder governance disambiguated** — `BudgetCriterionRevisionSeeder` is an **owner-approved baseline**
> (direct `revise()`→`publish()` + audit, like `FoundingRatingFrameworkValuesSeeder`), **not**
> `queueGovernedChange()` (which only *detects*); "nothing self-applies" governs the runtime admin loop (§6, B6).
> (P2) **Advisor "unresolved" predicate made exact** — `raised === true && resolved !== true`; resolved flags
> drop off, re-raised reappear unacknowledged; tests added (§5.4, §9, §11, B3).

> **v1.9 revision (review pass — completion path, 12th-criterion rollout, flag re-raise).** Three fixes,
> each verified against the code:
> (P1) **Portal completion now covers the controller's own path** — `requirementsPayload()` /
> `requirementsCompletion()` ([EntrepreneurPlanController.php:623](app/Http/Controllers/Portal/EntrepreneurPlanController.php:623))
> are PlanSection-only, so budget-runway (no `PlanSection`) would never read complete; B1 must make them (or a
> consolidated source) budget-`status`-aware, else the checkmark **and submit-gate** stick (§2, §3, B1).
> (P2) **12th-criterion rollout made install-safe** — `FOUNDING_CRITERIA` stays **11** (budget criterion is
> governed-revision data); `revise()` must be **extended to add** a criterion (it only updates today); a new
> `BudgetCriterionRevisionSeeder` + tests give fresh installs/CI the published 12-criterion version while the
> founding seeds/tests stay 11 (§6, B6).
> (P3) **Flag re-raise lifecycle specified** — state gains `raised_at`/`resolved_at`; a re-raised resolved
> flag **nulls `acknowledged_at`** so a stale ack can't hide it; transitions audited; test added (§5.4, §11).

> **v1.8 revision (review pass — propagate v1.7 fully across sections).** Three fixes (all stale carry-overs
> of v1.7, found by grepping the whole doc):
> (P2) **§13 weight-formula recap** updated from `/4` to the five-component `/5` gate (was contradicting §6).
> (P2) **§5 runway capture** re-attributed from B3 to **B2** (matches the WO table; B3 only compares).
> (P3) **RLS policy syntax** corrected — `FOR INSERT` takes `WITH CHECK` only, `FOR UPDATE` takes
> `USING` + `WITH CHECK` (per the referenced `entrepreneur_milestone_awards` policies).

> **v1.7 revision (review pass — weight gate + RLS write split + WO ownership).** Four fixes:
> (P1) **Partial-weight formula now gates on runway** — denominator is the **five** completion components
> (A–D **+ `expected_runway_months`**), so A–D complete with no runway = 4/5 = 80%, never full; same gate as §3,
> stated once (§6).
> (P1) **RLS split read vs write** — separate `FOR SELECT` (owner **+ assigned advisor read**) and
> `FOR INSERT/UPDATE` (owner/system **only**; advisor excluded) policies, mirroring the award split; test
> asserts advisor read-but-not-write (§3, §11).
> (P2) **Runway input moved to B2** — the builder captures/persists `expected_runway_months` (so B2 can reach
> `complete`); B3 keeps only the comparison (WO table).
> (P2) **Advisor visibility now an owned deliverable** — explicit `Advisor\EntrepreneurController@show` payload
> block + read-only render in B3/§9 (was prose-only "advisor-visible").

> **v1.6 revision (review pass — consistency after the v1.5 relocation).** Three fixes:
> (P2) **CLAUDE.md block runway source corrected** to `entrepreneur_budgets.expected_runway_months` (was
> still `ReadinessAssessment.responses`) — it's load-bearing for the build (§10).
> (P2) **`complete` now requires `expected_runway_months`** — a data-complete budget with no runway value
> can't run the core cross-check, so it stays `partial` (+ named rec), never full weight (§3, §11).
> (P2) **RLS/auth tests added** — owner access, advisor visibility, cross-entrepreneur denial, no
> client-scope access, and the through-`business_plans` derivation (§11).

> **v1.5 revision (review pass — RLS drift + runway-storage relocation).** Four fixes:
> (P1) **RLS derives the owner *through* `business_plans`** (the existing `plan_sections_scope` pattern) —
> dropped the drift-prone denormalised `entrepreneur_profile_id` key; a bad row can't protect the wrong owner
> (§3).
> (P2) **`expected_runway_months` moved to the `entrepreneur_budgets` row** (from `responses.budget_meta`) —
> **supersedes v1.3/v1.4** — resolving *both* the `Readiness::score()` poisoning and the historical-readiness-row
> ambiguity; no readiness-controller change needed (§1, §3, §5, §13, B3).
> (P2) **Consolidation must carry `description`** — `PlanRequirements` has only key/title; the unified source
> must add `description` or the UI loses it (§1).
> (P3) Fixed stale **`§16` → `§13`** references.

> **v1.4 revision (review pass — cap bug + Flag-1 consistency).** Three fixes:
> (P1) **`min(self,12)` cap masked real gaps** (self 18 vs calc 11 → 1). The cap now applies **only to the
> calc side, only when open-ended**; a finite calc compares uncapped self-reported (§5).
> (P2) **"always computable" tightened** to "when budget data *and* a canonical `expected_runway_months`
> exist", with a prompt for missing values (§2 ↔ §5).
> (P2) **Canonical storage resolved** — always `responses.budget_meta.expected_runway_months`; only the
> capture *UI* is open; B3 must extend the readiness controller (which drops non-flat fields) (§13, B3).

> **v1.3 revision (review pass — readiness side-effect + formula intent).** Four fixes:
> (P1) **`expected_runway_months` must not pollute readiness scoring** — `Readiness::score()` averages every
> *flat numeric* response clamped to 0–5, so store it in **non-scored nested metadata**
> (`responses.budget_meta.…`) and have `score()` ignore that key (§1, §5).
> (P1) **Flag 1 is bidirectional per spec §6.1** (`abs(...) > threshold`) — removed the contradictory
> "safe-direction / optimistic-only" language (§5).
> (P2) **"5 input groups" → "four input groups (A–D) + computed outputs"** in §8/B2, matching the §6/§13
> denominator.
> (P2) **§4.1 tightened** — runway/break-even strings are **displayed**, never stored; `computed` holds
> numeric + bool only.

> **v1.2 revision (review pass — wider scope + open-ended math).** Four fixes:
> (P1) **Normalisation helper must be universal** — there are **more than four** score readers; named
> `Revision`/`Benchmarking`/`EntrepreneurMilestones` too, and require a codebase grep so *no* service computes
> its own weighted score after B6 (§6, B6).
> (P1) **Runway "12+" comparison** — store `runway_months` (int) + `runway_open_ended` (bool), never the
> string; Flag 1: no mismatch when both ≥ 12, else compare capped-at-12 (§3, §5).
> (P2) **`partial` denominator = 4** (persisted input groups A–D), not 5 — the spec's "five" miscounts the
> calculated output group (§6, §13).
> (P2) **§2 "All four flags" → "the three budget flags"** (advisor-line dismissal is a separate nudge, never a
> scored flag).

> **v1.1 revision (review pass — repo realities).** Four fixes: (P1) **two requirement sources** — update
> `PlanRequirements` *and* `EntrepreneurPlanController::PLAN_REQUIREMENTS` (or consolidate) + a **structured
> UI branch** in `Plan.tsx` (it renders every requirement as a prose textarea) (§1, B1/B2). (P1) **runway is a
> 0–5 score, not months** — add a canonical `expected_runway_months` capture before Flag 1 (§1, §5, §13).
> (P1) **"excluded, not zero" needs a shared normalisation helper** routed through all four scoring consumers
> (`Assessment`/payload/`AdvisoryReadiness`/`ReportComposer`), which today default missing scores to 0 (§6,
> B6). (P2) **3 budget flags + 1 advisor-line nudge state**, not "four flags" (§5, B3). Route shape aligned to
> the singleton `entrepreneur/plan/…` pattern (§9).

### Locked decisions (owner, this session)

| Decision | Choice | Consequence |
|---|---|---|
| **Storage** | **Dedicated `entrepreneur_budgets` table** | One row per `BusinessPlan`; jsonb per input group + a computed snapshot + flag state. Surfaced as a new structured `budget-runway` requirement in the `financial` phase. |
| **Scoring** | **New 12th `RatingCriterion`, framework recalibrates** | Budget & Runway becomes a criterion in the versioned `RatingFramework`; owner weights are the *starting point* and the system recalibrates (governed, Layer 18) as criteria change — not a one-off hardcode. |
| **Advisor rate** | **`FeeCalculator` mechanism** | The Advisor Cost Impact Line derives its illustrative figure from the platform's fee mechanism — **presentation-layer only**, never in the budget model or scoring. |
| **Gamification** | **Optional tie-in behind `gamification_on`** | A `budget_built` milestone + the budget criterion feeding the grade trajectory surface **only when** the advisor has enabled gamification for that entrepreneur. |

---

## 1. Where it plugs in (grounded)

- **Plan structure — TWO requirement sources + a UI branch (P1).** The `financial` phase has two prose
  requirements (`revenue-model`, `launch-funding`) defined in **both** `PlanRequirements::DEFINITIONS`
  *(drives completion/scoring)* **and** the duplicate `EntrepreneurPlanController::PLAN_REQUIREMENTS`
  *(drives the portal UI/template, with `description`)*. Adding `budget-runway` to only one would desync the
  UI from completion. So: **consolidate to a single source** (preferred — have the controller read
  `PlanRequirements`) **or** update both, adding `budget-runway` to each. **If consolidating (P2):
  `PlanRequirements` requirements currently carry only `key`/`title`, while the controller's
  `PLAN_REQUIREMENTS` also carries `description` — the unified source must add `description` per requirement,
  or the UI loses them.** And because `resources/js/pages/portal/entrepreneur/Plan.tsx` renders **every**
  requirement as a prose `<textarea>` section, add a **structured UI branch**: `budget-runway` renders the
  Budget Builder panel (§8), not a text area. **Both completion paths** must count `budget-runway` complete
  when the budget row's `status = complete` (it has no prose `PlanSection`): the service
  `PlanRequirements::completion`/`requirementComplete` **and** the controller's own PlanSection-only
  `EntrepreneurPlanController::requirementsPayload()` / `requirementsCompletion()`
  ([EntrepreneurPlanController.php:623](app/Http/Controllers/Portal/EntrepreneurPlanController.php:623)) — or
  consolidate the controller onto the service. Miss the controller path and the portal checkmark **and the
  submit-gate** stay stuck on budget-runway forever.
- **Self-reported runway — a canonical *months* value on the budget row (P1).** The portal readiness flow
  stores `financial_runway` as a **0–5 *score*** (validated `numeric min:0 max:5`), **not months**; the
  seeder question is long-text. So there is **no clean self-reported runway-in-months today.** The budget
  captures a structured **`expected_runway_months`** that Flag 1 reads. **It lives on the
  `entrepreneur_budgets` row (§3), not in `ReadinessAssessment.responses`** — that avoids both poisoning
  `Readiness::score()` (which averages every flat numeric response) **and** the historical-readiness-row
  ambiguity (readiness writes a new row per submission). The readiness 0–5 score is untouched; we don't
  duplicate it — `expected_runway_months` is a genuinely new value.
- **Scoring** — `Assessment::firstPass()` iterates `RatingFramework->criteria` and scores each via
  `scoreCriterion` (AI) weighted by `criterion.weight`. The budget criterion joins that loop (§6).
- **AI Assist** — `Services\Entrepreneurs\Guidance` (the coach) gets the extra nudge prompt (§7). **Content
  firewall:** the coach prompt must not carry the budget scoring rubric; the realism scorer (examiner) must
  not coach — both classified in `EntrepreneurPromptRegistry` (the v1.x AI-isolation guard).
- **Advisor rate** — `Services\Fees\FeeCalculator::calculate(Client, FeeMethod, …)`. **Wrinkle:** it's keyed
  on a `Client`, but an entrepreneur is a pre-conversion `EntrepreneurProfile` with no `Client` — §4 / §13.
- **Charts** — repo uses **Recharts** (`Dashboard.tsx`, `dd/BusinessPlan.tsx`, `components/pv/WaterfallChart.tsx`)
  + Meridian Warm tokens. No new charting library (spec §7).

---

## 2. Non-negotiables (AI Integrity & spec rules)

- **The runway cross-check is the point.** Flag 1 (self-reported vs calculated runway) is the integrity
  feature — self-reported claims checked against arithmetic. It is computable when the budget has data **and**
  a canonical self-reported months value (`expected_runway_months`) exists; where that value is missing
  (legacy entrepreneurs), the builder **prompts for it** so the check can run — Flag 1 silently not-firing is
  a last resort, not the design (§5).
- **Flag-and-acknowledge, never hard-block.** The **three budget flags** (§5) let the entrepreneur continue;
  acknowledgements are logged with a **timestamp**, visible to the advisor, and feed scoring (§6) —
  *revising* scores better than *acknowledging*, but acknowledging is never a penalty distinct from not
  engaging. The **advisor-line dismissal is a separate one-time nudge state, not a budget flag** — it is
  never scored or "acknowledged" as a flag (§4).
- **Advisor Cost Impact Line is structurally absent from the model.** It is **presentation-layer only**:
  `displayed = net_profit_loss − advisor_rate`, computed at render, **never written to the budget object,
  never in any cost total / runway / break-even, never reaching the scoring engine.** No filtering rule —
  there is no pathway to filter (spec §4/§5). Never reference its value/visibility/dismissal in any
  AI-generated *scored* feedback text.
- **Calculations are server-side and deterministic.** The runway/break-even/cash-flow are computed in PHP
  (the source of truth for scoring, flags, advisor visibility); the UI may mirror them for instant feedback,
  but the persisted snapshot + scoring read the server result.
- **Audit every state change** (budget submitted/recalculated, flag raised/acknowledged, framework revised)
  via `AuditWriter`; **no PII in logs**; **RLS** (entrepreneur branch) on the budget table.
- **Governed scoring change.** Adding/reweighting the framework criterion goes through the **governed
  learning queue (Layer 18, `RatingFrameworkManager::queueGovernedChange`)** + owner approval — never a
  silent self-modification (CLAUDE.md "No silent learning updates").

---

## 3. Data model — `entrepreneur_budgets`

One row per `BusinessPlan` (entrepreneur-owned). UUID PK, `timestampsTz`.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `business_plan_id` | FK business_plans cascade, **unique** | one budget per plan; **the only owner link** — RLS derives the owner *through* this (no denormalised profile key, see RLS note) |
| `expected_runway_months` | unsignedInteger nullable | **canonical self-reported runway in months** — read by Flag 1 (§5). Lives **here**, not in `ReadinessAssessment.responses`, to avoid (a) poisoning `Readiness::score()` and (b) the historical-readiness-row ambiguity (P2); the builder captures it. The readiness 0–5 `financial_runway` score is untouched. **`0` is a valid, truthful answer** (a founder can report zero runway). Validate `nullable integer min:0`; everywhere "has the runway value" is tested — the status resolver, the `complete` gate, and Flag 1 — **"present" means `!== null`, never `filled()`/truthiness**, so `0` is present and reaches the cross-check (a `0` self-report vs a finite/open-ended calc fires the mismatch normally, §5). |
| `launch_costs` | jsonb | Group A — fixed rows + unlimited `other` rows + `contingency_pct` (spec §3.1) |
| `monthly_costs` | jsonb | Group B — fixed rows + `other` rows + `include_own_drawings` + `cogs` `{mode: percent\|per_unit, value}` + `ongoing_marketing` (spec §3.2) |
| `revenue_forecast` | jsonb | Group C — `price_per_unit`, `volume[12]`, `seasonality_note` (spec §3.3) |
| `funding_sources` | jsonb | Group D — rows `{source, amount, status: committed\|seeking\|conditional}` (spec §3.4) |
| `computed` | jsonb | **server-computed snapshot** — total launch cost, total monthly fixed, per-month {revenue, variable, gross, net_cash_flow, cumulative}, total funding available, available-after-launch; **`runway_months` (int) + `runway_open_ended` (bool)** and **`break_even_month` (int nullable) + `break_even_reached` (bool)** — store numeric + flag, **never the strings "12+"/"not reached"** (those are display only), so Flag 1 / scoring can compare numerically (§5). Recomputed every save; source of truth |
| `status` | string | `not_started` \| `partial` \| `complete` (spec §6.5; drives scoring weight). **`complete` requires all four input groups *and* `expected_runway_months`** — without the runway value the core cross-check (Flag 1) can't run, so the budget caps at **`partial`** with a named improvement rec ("add your expected runway so we can cross-check it"). Full weight never applies while the integrity check is impossible (P2). |
| `flags` | jsonb | per-flag state: `{runway_mismatch, funding_shortfall, no_break_even}` → `{raised:bool, raised_at, acknowledged_at, resolved:bool, resolved_at}`. **Re-raise rule (§5.4):** a resolved flag whose condition returns re-raises as **fresh** — `raised_at` updates, `resolved`/`resolved_at` clear, **and `acknowledged_at` resets to null** so a stale ack can't hide the new raise. Every transition audited. (spec §6) |
| `advisor_line_nudge_seen_at` | timestampTz nullable | the one-time advisor-impact nudge (spec §6.4) — lifetime once |
| timestamps | | |

- **No budget row = `not_started` (no eager backfill).** Legacy and not-yet-opened plans have **no**
  `entrepreneur_budgets` row. **B1 does not backfill** one per existing plan — the row is created lazily on
  first open/save. A **missing row is treated as `status = not_started`** everywhere: completion (budget-runway
  incomplete), scoring (criterion **excluded** and renormalised, never zero — §6), and the scorer/heuristic
  (a null budget → `not_started` `budget_evidence`, no fabricated numbers). Every budget reader must be
  null-safe on the row, not assume it exists.
- **`PlanRequirements`** — add `['key' => 'budget-runway', 'title' => 'Budget & runway']` to the `financial`
  phase, and teach `requirementComplete` to treat `budget-runway` as complete when `status = complete`
  (it has no prose `PlanSection`; resolve it against the budget row). **Also update the controller's
  PlanSection-only completion path** — `EntrepreneurPlanController::requirementsPayload()` +
  `requirementsCompletion()`
  ([EntrepreneurPlanController.php:623](app/Http/Controllers/Portal/EntrepreneurPlanController.php:623)) — to
  the same budget-aware rule (or consolidate it onto the service); otherwise the portal checkmark and the
  submit-gate never see budget-runway complete. Keep revenue-model + launch-funding unchanged.
- **RLS — derive the owner *through* `business_plans`, never a denormalised key; split read vs write (P1).**
  A denormalised `entrepreneur_profile_id` could drift (Alice's profile paired with Bob's plan), so every
  policy joins `business_plans` → `entrepreneur_profiles` on `business_plan_id` — the existing
  `plan_sections_scope` join shape: `EXISTS (SELECT 1 FROM business_plans bp LEFT JOIN entrepreneur_profiles
  ep ON ep.id = bp.entrepreneur_profile_id WHERE bp.id = entrepreneur_budgets.business_plan_id AND <subject>)`.
  After `ENABLE` + `FORCE`, install **separate read/write policies** (mirroring the
  `entrepreneur_milestone_awards` SELECT/INSERT/UPDATE split):
  - **`FOR SELECT`** — `<subject>` = `ep.user_id = current` **OR `ep.assigned_advisor_id = current`**
    (+ super_admin/system). The assigned advisor **reads**.
  - **`FOR INSERT`** (`WITH CHECK` only — INSERT policies take no `USING`) and **`FOR UPDATE`** (`USING` +
    `WITH CHECK`) — `<subject>` = `ep.user_id = current` **only** (+ super_admin/system), exactly the
    `entrepreneur_milestone_awards_insert_system` (WITH CHECK only) vs `_update_seen` (USING + WITH CHECK)
    split. The assigned advisor is **deliberately excluded from writes** — the budget is the entrepreneur's
    and the advisor never edits it. Service writes by `EntrepreneurBudget` run in `system` context.

  The owner is always the plan's owner — no drift possible. (If a denormalised profile column is ever added
  for query convenience, pin it with a composite FK `(business_plan_id, entrepreneur_profile_id)` → a unique
  on `business_plans(id, entrepreneur_profile_id)` + policy tests.) Admin-config (flag threshold) lives
  elsewhere.
- **No `advisor_cost_*` column anywhere** — it is not in the model (spec §4, hard rule).

---

## 4. Calculations & the Advisor Cost Impact Line

### 4.1 Calc engine — `BudgetCalculator` (PHP, deterministic)
Implements spec §3.1–§3.5 exactly: Total Launch Cost (incl. contingency), Total Monthly Fixed (excl. COGS),
per-month Revenue/Variable/Gross/Net-Cash-Flow, Total Funding Available (**Committed + Conditional only** —
Seeking excluded), Available Funding After Launch, **Cumulative Cash Position[12]**, **runway** (first month
cumulative ≤ 0, else open-ended) and **break-even** (first month net positive *and* stays positive). It
**stores numeric + bool** — `runway_months`/`runway_open_ended`, `break_even_month`/`break_even_reached`
(§3) — and the UI **displays** "12+ months" / "Not reached within 12-month forecast"; the strings are never
persisted. Pure, unit-tested against worked examples.

### 4.2 Advisor Cost Impact Line — presentation only
- **Source:** `FeeCalculator` (decision 3). **Wrinkle to resolve (§13):** `FeeCalculator::calculate()`
  needs a `Client`; an entrepreneur has none. Extract a **client-less "entry-level / pre-revenue rate"**
  path (a method on `FeeCalculator`/`ServiceRateManager` that returns the suggested entry-level monthly rate
  without a `Client`), or pass a lightweight context — do **not** fabricate a `Client`.
- **Render:** below the Net Profit/Loss display: *"If you budgeted $Y/month for advisory support, your net
  position would be $(X−Y)."* Editable (what-if), dismissible. **Computed in the controller/Inertia payload's
  presentation layer, never persisted to `entrepreneur_budgets`, never in `computed`, never sent to
  scoring.** Dismiss → one-time nudge (`advisor_line_nudge_seen_at`, lifetime once, spec §6.4).
- Framing copy verbatim from spec §4.1 / §6.4.

---

## 5. Flags & the runway cross-check (spec §6)

**Three budget flags** (flag-and-acknowledge, advisor-visible, scored per §6) **plus one advisor-line
nudge state** (not a scored budget flag):
1. **Runway Mismatch (core).** Trigger compares `expected_runway_months` (self-reported) with the calculated
   runway, default threshold **1 month** (admin-config). Two rules the data forces:
   - **Bidirectional, per spec (P1).** The spec (§6.1) is explicit: the trigger is the **absolute
     difference** `abs(self_reported_months − calculated_months) > threshold` — a gap in *either* direction is
     "worth a closer look", and the entrepreneur-facing wording is neutral. So the flag is **bidirectional**;
     do **not** make it optimistic-only.
   - **Open-ended runway (P1).** The calculator returns a month number **or "12+"** (never drops below 0 in
     the forecast) — so store calculated runway as an **integer `runway_months` + a `runway_open_ended`
     bool**, never the literal string "12+". The cap applies **only on the calc side, and only when it is
     open-ended** — never a blanket `min(self,12)` (that would mask a real gap, e.g. self 18 vs calc 11):
     - calc **open-ended** (`runway_open_ended = true`): no mismatch when `self_reported ≥ 12` (both
       open-ended agree); else `abs(self_reported − 12) > threshold` (calc lower-bounded at 12).
     - calc **finite** (`runway_open_ended = false`): `abs(self_reported − runway_months) > threshold` with
       **no cap on `self_reported`** — so self 18 vs calc 11 → gap 7 → fires.
     Display still shows "12+ months (beyond current forecast)".
   - **Canonical self-reported months — on the budget row (P1).** The readiness `financial_runway` field is
     a **0–5 score, not months**, so **B2 captures/persists** a structured **`expected_runway_months`** **on
     the `entrepreneur_budgets` row** (§3) — **not** in `ReadinessAssessment.responses` (B3 only *compares* it
     to the calc). This deliberately avoids
     both (a) `Readiness::score()` poisoning (it averages every flat numeric response → a months value would
     inflate the readiness score) and (b) the historical-readiness-row ambiguity (readiness writes a *new
     row* each submission, so "which row holds it / carry it forward?" is undefined). The budget row is the
     single stable home, read directly by Flag 1. Do **not** map the 0–5 score to months or parse the
     long-text. If absent, the **builder prompts for it**; only if still absent does Flag 1 not fire (no
     fabricated months). **Never auto-correct** the self-reported value — the entrepreneur owns it.
2. **Funding Shortfall** — `Total Funding Available (Committed+Conditional) < Total Launch Cost`.
3. **No Break-Even Within Forecast** — softer tone; break-even "not reached".

Plus: **Advisor-line dismissed** — a one-time *nudge state*, **not** one of the three budget flags, no
scoring effect (§4); tracked by `advisor_line_nudge_seen_at`.

Flag state + acknowledgement timestamps live in `entrepreneur_budgets.flags`; raising/acknowledging is
audited. Exact entrepreneur-facing wording + buttons verbatim from spec §6.1–§6.4. Admin threshold stored in
the existing project/reference settings pattern.

**§5.4 Flag lifecycle (re-raise — B3).** Each flag is a small state machine recomputed on every budget save:
- **raised** — condition true and not previously raised: set `raised = true`, `raised_at = now`; advisor-visible.
- **acknowledged** — entrepreneur acknowledges: set `acknowledged_at` (the *raise* stays raised; ack ≠ resolve).
- **resolved** — condition clears: set `resolved = true`, `resolved_at = now`; keep `raised_at`/`acknowledged_at`
  as history.
- **re-raised** — a **resolved** flag's condition returns: treat as a **fresh raise** — bump `raised_at`,
  clear `resolved`/`resolved_at`, **and null `acknowledged_at`**. This is the load-bearing rule: a stale
  acknowledgement must **never** suppress a newly re-raised flag (else a fixed-then-broken-again runway looks
  "handled" to the advisor). `raised_at`/`resolved_at` are **stored on the flag** (not audit-only) so the
  advisor sees the current timeline at a glance; every transition is **also** audit-logged. Acknowledged is
  never a distinct scoring penalty; revised > acknowledged (§6).
- **"Unresolved" — the exact advisor predicate.** The advisor "unresolved flags" panel (§8, B3) filters on
  **`raised === true && resolved !== true`**. So a resolved flag drops off the advisor panel, and a re-raised
  flag reappears — **unacknowledged** (per the re-raise rule). Tests assert both directions.

---

## 6. Scoring integration (decision 2 — governed framework recalibration)

- **New criterion.** Budget & Runway becomes a **12th `RatingCriterion`** in the `RatingFramework`. Today
  `RatingFrameworkManager::assertCompleteFoundingValues()` hard-codes 11 criteria summing to 100 and
  `revise()` only *updates* existing criteria — so the build must:
  - extend the manager to **add** a criterion in a new framework version (not just update), and relax the
    "exactly the 11 founding numbers" assertion to "all current criteria present + weights sum to 100";
  - keep the **owner-set weights as the starting point** and support **recalibration** — adding the budget
    criterion re-distributes weight to still total 100, proposed via
    `RatingFrameworkManager::queueGovernedChange()` (**Layer 18, governed, owner-approved**), then
    `revise()` → `publish()` a new version. The owner confirms the recalibrated weights; nothing
    self-applies (CLAUDE.md).
- **Owner-supplied 12-criterion baseline values (v1.21).** The seeder's mechanics below are buildable, and the
  owner has now supplied the criterion name + recalibrated weights from the rubric image. Criterion **#12** is
  **Budget** at **12%**; all 12 weights sum to 100:
  `1 Type of business 8`, `2 Location 7`, `3 Means of doing business 8`, `4 Discuss the industry 8`,
  `5 What sets the business apart 9`, `6 Describe unique success factors 8`,
  `7 Mission and Vision statement 8`, `8 Intellectual property 7`, `9 Goals and objectives 9`, `10 Culture 8`,
  `11 Legal Environment 8`, `12 Budget 12`. The owner has also approved the **standard descriptor pattern** for
  `Budget`, so the production seeder has concrete descriptor text:
  - `exceptional`: Budget is specific, evidence-backed, internally consistent, and ready for advisor-supported execution.
  - `strong`: Budget is clear and mostly evidenced, with only minor advisor follow-up required.
  - `developing`: Budget is directionally useful but has material gaps or assumptions to test before launch.
  - `needs_work`: Budget is too vague, unsupported, or inconsistent to rely on for launch decisions.
- **Admin-maintained rubric over time (v1.23).** The budget seeder is only the baseline. Because the rubric is a
  business instrument, Admin must be able to **view and maintain** it as the business changes:
  - Add an Admin rubric page for the entrepreneur `RatingFramework` (e.g. `Admin\RatingFrameworkController` /
    `admin.rating-frameworks.*`) showing the latest published framework, criteria, weights, descriptors, grade
    bands, production-ready status, published dates, and prior versions.
  - Admin can create a **draft revision from the latest published framework**, edit criterion names, weights,
    descriptors, grade bands, add a new criterion, or mark a criterion inactive/deprecated for future versions.
    **Never edit a published framework in place.** Publishing creates a new `rating_frameworks.version` with
    `supersedes_framework_id`; existing assessments keep their original `rating_framework_id` and replay under
    their historical rubric.
  - Validation before publish: criterion numbers unique; every active criterion has all grade-band descriptors;
    active weights sum to exactly 100; grade bands are ordered and cover 0+; a change reason is required; no
    future published version is downgraded by an older draft.
  - Governance: reuse/extend `RatingFrameworkManager::revise()` + `publish()` for the Admin path, with audit
    events for draft creation, publication, and rollback/deprecation. Layer 18 (`Entrepreneur rating framework
    review`) and `queueGovernedChange()` may propose changes, but they prefill/stage drafts only; Admin approval
    remains required before the rubric affects new assessments.
  - Permissions: Admin users with learning/rubric view permission can read current + historical rubrics; publish
    requires the approval/manage permission and MFA, matching the existing learning-update admin posture.
- **Fresh-install + test rollout — don't leave the criterion runtime-only (P2).** `RatingFramework::FOUNDING_CRITERIA`
  (the 11-entry constant) is the **immutable founding baseline** and **stays 11** — the budget criterion is
  **data in a governed revision, not a 12th founding constant**. So the **founding** seeds/tests are correct
  to remain 11 and must **not** be edited to 12: `RatingFrameworkSeeder` (v1, 11 placeholders),
  `FoundingRatingFrameworkValuesSeeder` (v2, 11 confirmed values), and
  `RatingFrameworkTest::test_founding_framework_seeds_eleven...`. A fresh install / CI env must still **end up
  with the budget criterion**, so add a **new `BudgetCriterionRevisionSeeder`** that publishes a 12-criterion
  version (recalibrated weights = 100), plus **new tests** asserting 12 criteria, weights = 100, and the budget
  criterion scores. Without it, fresh installs bootstrap 11 and budget scoring is inert until someone adds the
  criterion by hand.
  - **Seeder governance + mechanics (resolves the clash with "nothing self-applies", and the actor/idempotency
    question, P2):** the seeder is an **owner-approved baseline seed** — committed seed data *is* the owner's
    approval. **Mirror `FoundingRatingFrameworkValuesSeeder` exactly** ([:13-37](database/seeders/FoundingRatingFrameworkValuesSeeder.php:13)):
    inside a `DB::transaction`, build the next published version with a **direct `RatingFramework::create([... STATUS_PUBLISHED, production_ready ...])`** + criteria inserts — **not** the manager's `revise()`/`publish()`
    (those require a `User $actor`; the baseline seeder takes none, exactly as the founding seeder does). So
    **no actor is needed** and it does **not** call `queueGovernedChange()` (that writes only a
    `STATUS_DETECTED` candidate and would leave fresh installs on 11).
    - **Idempotent — guard on the budget-criterion *identity*, never the count (P2).** `rating_criteria` has
      **no stable key** — only `number` + `name`
      ([migration:33-46](database/migrations/2026_05_23_085000_create_rating_framework_tables.php:33)) — so a
      "12 criteria" count guard is wrong: it would skip a *bad* 12-criterion version, and after a future 13th
      criterion it would re-fire and republish a stale 12-only baseline. Instead: **return early iff the latest
      published framework already contains the budget criterion** — identity = `number === 12` **and** the
      expected budget name (and, more robustly, a marker the seeder stamps in the criterion's
      `industry_variants`/`descriptors`, e.g. `'budget_criterion' => true`, since there's no schema key). And
      **never downgrade**: the seeder must not publish a version with **fewer** criteria than the latest
      published — so once a 13th criterion exists (budget already present), it simply skips. Safe to run
      repeatedly and forward-compatible.
    - **Wired in:** registered in `DatabaseSeeder` **after** `RatingFrameworkSeeder` + `FoundingRatingFrameworkValuesSeeder`, and in the **test** seed path that today seeds the founding framework, so normal installs and CI both reach 12.
    - **Audited (closes the gap with the "framework revised → AuditWriter" rule, §10/line 207):** the seeder
      records a **seed-time `AuditWriter` event with the system/null actor** (`AuditWriter` accepts a null
      actor) for the published 12-criterion baseline — so the framework-version change is traceable in
      `audit_events` even though no `User` is involved in the create. (The founding seeder predates this; the
      budget seeder sets the better precedent rather than copying that omission.)
    - **"Nothing self-applies" governs the *runtime* loop:** the manager's add-criterion `revise()`→`publish()`
      (with the owner as `$actor`) + `queueGovernedChange()` is the **admin-UI** path; the seeder is baseline
      data. Baseline seed ≠ runtime self-application.
- **What the criterion scores (spec §5.1):** internal consistency (prose vs structured — Revenue Model vs
  Group C, Launch Funding vs Group D), completeness, **realism of the revenue ramp** (AI-assessed, examiner
  side), contingency/buffer presence, and **flag resolution** (revised > acknowledged; acknowledged never a
  distinct penalty).
- **The scorer must actually *receive* the budget data (P1).** Today `Assessment::scoreCriterion()` feeds the
  prompt **only `sections_text`** (prose) and `heuristicScore()` keyword-matches that same prose
  ([Assessment.php:166-218](app/Services/Entrepreneurs/Assessment.php:166)) — so **none** of the structured
  budget signal reaches scoring; the §5.1 checks above would be impossible. For the budget criterion B6 must
  **load the `entrepreneur_budgets` row for the plan and pass a structured `budget_evidence` block**
  (`computed` per-month figures + `runway_months`/`break_even_month`, `status`, `expected_runway_months`,
  `flags`) into **both** `scoreCriterion()`'s prompt `input` **and** `heuristicScore()` (which must score the
  budget criterion from completeness/flags, not prose keyword overlap). Examiner-side only — no coaching.
- **Data-quality weighting (spec §6.5) — needs ONE shared normalisation helper, used everywhere (P1).**
  `not_started` → criterion **excluded** from the score (**not** zero), named as an improvement rec;
  `partial` → criterion weight reduced proportionally; `complete` → full weight. **But every weighted-score
  reader today computes `Σ score × weight/100` with a *missing criterion defaulting to 0*** — and there are
  **ten** (confirmed by grep): `Assessment::weightedScore` ([:224](app/Services/Entrepreneurs/Assessment.php:224)),
  `BuildsEntrepreneurAssessmentPayload`, `AdvisoryReadiness::score`, `ReportComposer`, `Revision::weightedScore`
  (revision deltas/grades), `Benchmarking` (percentiles), `EntrepreneurMilestones` (grade-up snapshots), **plus
  three learning-layer readers** each doing `criteria->sum(score × weight/100)`:
  `Learning\Layers\RatingPredictiveValidity::planScore` ([:150](app/Services/Learning/Layers/RatingPredictiveValidity.php:150)),
  `Learning\Layers\ConversionOutcomeLearning::planScore` ([:127](app/Services/Learning/Layers/ConversionOutcomeLearning.php:127)),
  and `Learning\Layers\PlanQualityBenchmarks::score` ([:161](app/Services/Learning/Layers/PlanQualityBenchmarks.php:161)).
  If "excluded" is just an omitted score, *any* of them scoring it `0 × weight` **depresses the grade /
  skews the learning + benchmark outputs** — the opposite of "not zero". So B6 must introduce a
  **single shared `effective_weight` / re-normalisation helper** that drops excluded criteria and
  **renormalises the remaining weights to sum to 100**, then **route *every* reader through it and delete the
  per-service weighted-score math.** B6 must **grep the codebase** for weighted-score computations
  (`weightedScore` / criterion-weight sums / `gradeFor` callers) and confirm **none** compute their own after
  this — a regression test asserts excluded-criterion handling is identical across all readers.
  - **Concrete return contract — a `ScoringResult` value object, not a bare float (P1).** Today readers return
    a float immediately (e.g. `AdvisoryReadiness::score` returns `round(...)`,
    [:79-93](app/Services/Entrepreneurs/AdvisoryReadiness.php:79)), so "surfaced data gap" needs a real shape.
    The helper returns **`ScoringResult { overall: float, rows: [...], dataGaps: [{criterion_number, reason}] }`**:
    - **`overall`** renormalises over criteria that have a **usable score** *or* an explicit **exclusion**;
      a **data-gap** criterion (no valid score, no `excluded_reason`) is **dropped from the weighted sum and
      listed in `dataGaps`** — never silently zeroed, never silently excluded.
    - **Readers** that only need the number read `->overall` (drop-in for today's float); UI/learning readers
      may also surface `dataGaps` (a "N criteria unscored" indicator). The helper **never throws** on a gap.
    - **Enforcement — every *decision* consumer gates on `dataGaps`, not just `finalise()` (P1).** Blocking only
      finalisation is insufficient: a fresh first-pass assessment is consumed **before** finalisation —
      `LivingPlan::reassess()` calls `AdvisoryReadiness::evaluate()` on a just-created assessment
      ([LivingPlan.php:89](app/Services/Entrepreneurs/LivingPlan.php:89)), and `evaluate()` simply takes the
      latest assessment and scores it against the readiness threshold
      ([AdvisoryReadiness.php:32-43](app/Services/Entrepreneurs/AdvisoryReadiness.php:32)). So **a partial score
      could emit a readiness signal**. Rule: any consumer that uses `overall` for a **decision** must
      **skip/defer while `dataGaps` is non-empty** — concretely `Assessment::finalise()`
      ([:151](app/Services/Entrepreneurs/Assessment.php:151)) **blocks**, and `AdvisoryReadiness::evaluate()`,
      `LivingPlan::reassess()`, the grade-up milestone award (`EntrepreneurMilestones`), and `Benchmarking`
      contribution all **return early / no-op** on gaps. **Pure *display* reads** may render `overall` but must
      show the "N unscored" indicator — **a report's PV-creation/score-persistence is _not_ display** (see
      ReportComposer in the consumer map). **And the persisted grade must not look authoritative while gaps
      exist (P1):** `firstPass()` persists **`overall_grade = 'incomplete'`** (a sentinel, **not** a band) when
      `dataGaps` is non-empty ([Assessment.php:66](app/Services/Entrepreneurs/Assessment.php:66)), recomputing a
      real band only once **every** criterion is scored. So screens reading the **raw** column
      (`EntrepreneurDashboardController:85`, `EntrepreneurPlanController:559`, gamification, survey label) show
      *Incomplete*, not a misleading grade — no need to rewire each display to recompute.
      `GRADE_RANK['incomplete'] ?? 0` already keeps it non-promotable for grade-up. Mirrors spec §9
      (discrepancies pause downstream until resolved).
      - **`finalise()` performs the sentinel→band transition (P1).** Today `finalise()` only timestamps + awards
        milestones ([Assessment.php:151-164](app/Services/Entrepreneurs/Assessment.php:151)) — it never recomputes
        the grade, so a gap resolved on the same assessment would finalise (and `EntrepreneurMilestones` snapshot,
        [:329](app/Services/Entrepreneurs/EntrepreneurMilestones.php:329)) the stale `incomplete` sentinel. So
        `finalise()` must **first recompute a gap-free `ScoringResult` from the merged ai+advisor scores, assert
        `dataGaps` is empty (the gate), and persist the real `overall_grade` band — then** award milestones and
        permit the report. No path may finalise/milestone/report off the sentinel.
      - **The payload must not re-derive a band over the sentinel (P2).** `BuildsEntrepreneurAssessmentPayload`
        today does `gradeFor(round(sum(contribution)))` ([:20-27](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:20)),
        exposed on the dashboard ([EntrepreneurDashboardController:90](app/Http/Controllers/Portal/EntrepreneurDashboardController.php:90))
        and advisor detail ([Advisor\EntrepreneurController:354](app/Http/Controllers/Advisor/EntrepreneurController.php:354))
        — so a card could show a **band** while the raw column shows *Incomplete*. The builder must consume the
        helper's `ScoringResult` and return **`overall_grade = 'incomplete'` + the `dataGaps` count/rows** when
        gaps exist, so cards/details match the raw column and carry the "N unscored" indicator.
      - **`overall_grade` write/read map (code-derived — the complete set, so no grade site is missed).**
        **Writers** of the column: `Assessment:66` (firstPass → `incomplete` on gaps), `finalise()` (recompute to
        real band), `ReportComposer:324` (gated on gaps) — the only three. **Report-content grade embeddings**
        (`ReportComposer:342/366/1723/1831`) are all **downstream of the ReportComposer gap-gate**, so no report
        renders an incomplete grade. **Payload-derived readers** (`Advisor\EntrepreneurController:354`,
        `EntrepreneurDashboardController:90`) are correct once the payload returns `incomplete`. **Raw-column
        readers** (`EntrepreneurDashboardController:85`, `EntrepreneurPlanController:559`, `EntrepreneurMilestones:329`
        — which only fires post-finalise) see the sentinel or the recomputed band. Every site is covered.
    - **Consumer map (code-derived, exhaustive — closes the "missed a call site" loop).** From a grep of
      score/grade consumers:
      - **Weighted-score *computers* → route through the helper:** `Assessment::weightedScore` (:54/:224),
        `Revision::weightedScore` (:218), `EntrepreneurMilestones::weightedScore` (:336), `AdvisoryReadiness::score`,
        the three learning layers, `BuildsEntrepreneurAssessmentPayload` (:20 sums `contribution`, :27 `gradeFor`),
        `ReportComposer`, `Benchmarking`.
      - **Decision / write consumers → gate on `dataGaps` (skip/block):** `Assessment::finalise`,
        `AdvisoryReadiness::evaluate` (:42), `LivingPlan::reassess` (:89), `EntrepreneurMilestones` grade-up (:329),
        `Revision` deltas (:110-121), the three learning layers (don't train on a gap-containing assessment),
        **and `ReportComposer` — a *write* path, not display:** it computes the score, **creates the Concept PV
        artifact** ([:316-320](app/Services/Reports/ReportComposer.php:316)), **overwrites `overall_grade` +
        `concept_pv_calculation_id`** ([:322-326](app/Services/Reports/ReportComposer.php:322)), and builds the
        `Report` + sections around a **required** `PvCalculation` ([:1159](app/Services/Reports/ReportComposer.php:1159)).
        So the gate is **refuse the whole report**, not just the PV: **gate at the top** (right after the
        plan/profile/framework validation, [:312-314](app/Services/Reports/ReportComposer.php:312)), **before any
        write** — throw a clear domain error so the transaction creates **no** `PvCalculation`, **no** `Report`,
        **no** sections, **no** PDF, and **no** audit event while `dataGaps` is non-empty.
      - **Display consumers → render `overall`/grade (the `incomplete` sentinel when gaps exist) + the "N
        unscored" indicator, never block:** `BuildsEntrepreneurAssessmentPayload`, `EntrepreneurDashboardController`
        (:85/:233), `EntrepreneurPlanController` (:559), `EntrepreneurGamification` (:136), `SurveyActivationService`
        (:210 grade label).
      - **Explicitly OUT of scope (different subsystems — do *not* reroute):** `NpoHealthScorer::weightedScore`
        (NPO dimensions — separate lane per CLAUDE.md), `DataQuality{Signal,Scorer}::weightedScore` (data-quality,
        not the rating framework), and the unrelated `evaluate()` methods (`EnforceSessionSecurity` step-up,
        `WellbeingCheckinService`, `SurveyResponseRecorder`).
  - **The helper returns normalised *rows*, not just an overall score (P2).** An overall-only
    `weightedScore()` leaves per-criterion **UI/report** surfaces computing from **raw** weights —
    `BuildsEntrepreneurAssessmentPayload` builds each row's `weight`/`contribution` from `$criterion->weight`
    ([:74-81](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:74)), and
    `ReportComposer` likewise — so an excluded/partial budget criterion would still render at **full raw
    weight**, contradicting the renormalised total. So the helper exposes **both**: an `overall` score **and**
    `rows[]`, each row = `{number, name, raw_weight, effective_weight (post-renormalisation, a percentage of
    100), score, score_source (ai|advisor), contribution, weight_status, excluded_reason}`. **`contribution =
    score × (effective_weight / 100)`** — weights are percentages summing to 100, matching the existing formula
    ([BuildsEntrepreneurAssessmentPayload:81](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:81)
    `score * (weight / 100)`); **not** `score × effective_weight` (100× too large). The payload + report
    **consume these rows** instead of `$criterion->weight`, so the displayed weights and contributions match the
    score. Test: an excluded budget criterion renders `effective_weight = 0` / excluded, and the remaining rows'
    effective weights sum to 100.
  - **Persisted score-row contract — the weighting must be replayable, not recomputed from the live budget (P1).**
    Downstream readers recompute from the **persisted** `plan_assessments.ai_scores`/`advisor_scores` jsonb +
    the framework criteria ([plan_assessments migration:19](database/migrations/2026_05_23_086000_create_plan_assessments_table.php:19);
    e.g. `AdvisoryReadiness::score` does `score ?? 0` over `criteria` at
    [:79-93](app/Services/Entrepreneurs/AdvisoryReadiness.php:79)) — they do **not** hold the live
    `entrepreneur_budgets` row. So at assessment time (when the budget row *is* loaded) each per-criterion entry
    in `ai_scores` must **persist the weighting decision**: **`weight_status`**
    (`not_started`\|`partial`\|`complete`), **`effective_weight_multiplier`** (frozen at score time — `1.0`
    complete, `populated/5` partial, `0` excluded), and **`excluded_reason`** (e.g. `budget_not_started`, else
    null). `EntrepreneurScoring::weightedScore(framework, scores)` renormalises **from these persisted fields**,
    so (a) "missing because **excluded**" (`excluded_reason` set → drop + renormalise) is distinguishable from
    "missing because **scoring failed**" (no entry, no reason → a real data gap, surfaced not silently zeroed),
    and (b) a **historical round replays its own frozen multipliers** even after the live budget later changes.
    No new column — `ai_scores` is `jsonb`; extend the row shape. Test: replay an old round, and the
    excluded-vs-failed distinction.
    - **Advisor overrides keep the AI row's weighting (P1).** `adjustScore()` writes a separate `advisor_scores`
      row carrying **only** `criterion_number`/`score`/`note`/audit fields
      ([Assessment.php:83-103](app/Services/Entrepreneurs/Assessment.php:83)) — **no** weighting metadata — and
      readers prefer the advisor score over the AI score ([AdvisoryReadiness.php:86-91](app/Services/Entrepreneurs/AdvisoryReadiness.php:86)).
      So the helper must, per criterion, take the **score value** from `advisor_scores` (when present) but the
      **frozen weighting metadata from the AI row** (`weight_status`/`effective_weight_multiplier`/`excluded_reason`).
      Metadata stays **single-source in `ai_scores`** (don't duplicate it into `advisor_scores` — that would
      drift); an advisor-reviewed budget criterion thus keeps its excluded/partial weighting. **The advisor
      lookup is keyed by `criterion_number` and ignores any entry lacking it** — so a legacy *summary*
      `advisor_scores` (`{overall, note}`, see below) can't leak in as a score; the criterion falls back to its
      AI score. Test: override a `not_started`/`partial` budget criterion and assert the multiplier is unchanged.
      - **Advisor scoring *resolves a gap* (P2 — the gap criterion has no AI metadata).** A **data-gap**
        criterion has no usable AI score and no metadata. An `advisor_scores` row with a `criterion_number` +
        numeric `score` **clears that gap** (the criterion now has a usable value). Metadata resolution:
        - if the AI row carries **explicit** metadata (`excluded`/`partial`, e.g. the budget criterion) → **keep
          the AI metadata** (single-source; the override changes the value, not the weighting);
        - if there is **no** AI metadata (a genuine gap) → **default to `complete`/`1.0`/null** (the legacy
          default) — the advisor-scored criterion now carries full weight.
        Test: an advisor score on a gap criterion empties `dataGaps` and the criterion scores at full weight;
        an advisor score on a `partial` budget criterion keeps the `partial` multiplier.
    - **Legacy / fixture fallback (P2) — two cases, because some legacy `ai_scores` aren't even canonical rows.**
      - **(a) Canonical row, no metadata** — a `{criterion_number, score, …}` row missing the new fields
        **defaults to `complete` / `1.0` / null** (full weight, exactly as today; no backfill). The
        `RatingPredictiveValidityTest` fixture is this case ([:149](tests/Feature/Learning/RatingPredictiveValidityTest.php:149))
        — already canonical, just defaults.
      - **(b) Non-canonical legacy shape → B6 migrates the fixture.** Some existing `ai_scores` are **not**
        per-criterion rows at all: `TestingSeedDataSeeder` is **name-keyed** (`{problem: 8.6, market: 8.1, …}`,
        [:1352](database/seeders/TestingSeedDataSeeder.php:1352)) and `GamificationTest` is a **bare list**
        (`[80.0, 70.0]`, [:153](tests/Feature/Entrepreneurs/GamificationTest.php:153)) — both lacking
        `criterion_number`/`score`. These would otherwise read as all-data-gaps. **B6 rewrites these two
        fixtures to the canonical row shape** (they're test/seed data) rather than teaching the helper legacy
        shapes — so the helper understands exactly **one** row shape. **Migrate their `advisor_scores` too:** the
        same fixtures store **summary** advisor shapes (`{overall: 8.2, note: …}` at
        [TestingSeedDataSeeder:1358](database/seeders/TestingSeedDataSeeder.php:1358) and
        [GamificationTest:154](tests/Feature/Entrepreneurs/GamificationTest.php:154)) — migrate them to canonical
        per-criterion advisor rows **or drop them** where the test doesn't need an override (the helper's
        `criterion_number`-keyed lookup already ignores a stray summary blob, but the fixtures shouldn't carry
        dead non-canonical data). Budget-behaviour fixtures additionally carry explicit metadata.
      - **Default applies to missing *metadata*, not a missing *score* (P1 — resolves the clash with the
        data-gap rule).** The `complete`/`1.0`/null default fires **only on an otherwise-valid score row** — one
        with a present `criterion_number` **and** a numeric `score`. A **missing/malformed score identity or
        value** (no `criterion_number`, or a non-numeric/absent `score`, with no `excluded_reason`) stays a
        **scoring data gap** — surfaced, **not** silently defaulted to full weight — **except** explicitly
        recognised legacy fixture shapes (normalised in the fixture-update step). So "valid score, no metadata →
        full weight" and "no/garbage score → data gap" never collide; the helper is null-safe (no throw) but a
        gap is **reported**, not masked.
  - **`partial` proportion (P1 — denominator = the **five completion components**: input groups A–D **plus
    `expected_runway_months`**):** effective weight = full × (populated components / **5**). So all five
    present → `status = complete` → **full** weight; A–D complete but **runway missing** → 4/5 = **80%**, never
    full. This is the *same* gate as §3 (`complete` requires the runway value), expressed once — the formula
    can't reach full weight without the runway value, closing the "4/4 = 100%" hole. The *calculated* output
    group (§6.5's apparent "fifth input") is **not** a component — it has no entrepreneur input — so it never
    enters the denominator; the runway capture does. One formula, used by the helper, the status resolver, and
    the tests.
- **AI realism** uses an **examiner** prompt (e.g. `entrepreneur.budget_realism_score`) — added to
  `EntrepreneurPromptRegistry` classified **examiner**, so the AI-isolation CI guard covers it; it must not
  contain coaching, and the coach (§7) must not receive the budget rubric.
- **Advisor line excluded structurally** — there is no pathway (§2/§4); no filtering rule needed.

---

## 7. AI Assist nudge + gamification tie-in

- **AI Assist nudge (coach, spec §2.1).** `Guidance` adds **one** extra prompt, shown **once per field per
  entrepreneur**, in the Revenue Model and Launch Funding fields: *"…add the actual numbers to your Budget &
  Runway Builder and we'll calculate your runway and break-even automatically."* Soft nudge, not a gate. This
  is a **non-examiner** prompt (no rubric) — already on the no-rubric side of the registry.
- **Gamification tie-in — behind `gamification_on` (decision 4).** Only when the advisor has enabled
  gamification for that entrepreneur:
  - a new **`budget_built` milestone** (awarded when `status` first reaches `complete`) via the existing
    `EntrepreneurMilestones` awarder (event-driven on budget save; durable award row);
  - the budget criterion naturally feeds the **grade trajectory** (it's a framework criterion).
  When `gamification_on = false`, the budget feature works fully but surfaces no milestone/streak effect.

---

## 8. Frontend (Inertia + React 19 + Recharts + Meridian Warm)

- **`portal/entrepreneur` plan workspace** — a **Budget & Runway** panel in the financial phase: the **four
  input groups (A–D)** (repeatable rows for Other/Funding), autosave, the **computed outputs**, the three
  charts, the flag dialogs (flag-and-acknowledge), and the dismissible Advisor Cost Impact Line below Net
  Profit/Loss.
- **Three charts (spec §7),** Recharts + Meridian Warm tokens (mirror `WaterfallChart.tsx`): Monthly Cash
  Flow (combo bar+line, break-even marker, $0 line), Runway Comparison (self-reported vs calculated,
  colour-coded by gap), Launch Cost vs Funding (colour-coded by shortfall).
- **Advisor view** — the entrepreneur's budget summary + **unresolved flags** surfaced on the advisor's
  entrepreneur detail page (advisor-visible flags, spec §6).

---

## 9. Routes / controllers / services

Follow the existing **singleton** entrepreneur-portal shape (`entrepreneur/plan/…`, matching
`entrepreneur.plan.show` / `entrepreneur.plan.sections.store`) — **not** a `{businessPlan}` route-model
binding. The active `BusinessPlan` is resolved from the **authenticated entrepreneur's profile**
(`entrepreneurUser()` → `profileFor()`), so there is no foreign-plan IDOR surface and auth matches the rest
of the portal:
```
GET    portal/entrepreneur/plan/budget                            portal.entrepreneur.plan.budget.show
PUT    portal/entrepreneur/plan/budget                            portal.entrepreneur.plan.budget.update   (autosave; recompute; flags)
POST   portal/entrepreneur/plan/budget/flags/{flag}/acknowledge   portal.entrepreneur.plan.budget.flags.ack
POST   portal/entrepreneur/plan/budget/advisor-line/dismiss       portal.entrepreneur.plan.budget.advisor-line.dismiss
```
(The budget panel + charts may instead ride the existing `entrepreneur.plan.show` payload rather than a
separate GET — pick one and be consistent.) Services: `BudgetCalculator` (pure calc), `EntrepreneurBudget`
(persist + status + flags + audit, system context where it touches RLS), advisor-line resolver
(FeeCalculator), and the `Assessment` criterion hook. Entrepreneur portal group (entrepreneur RLS, not
`EnforceClientScope`-client-scoped). Run `php artisan wayfinder:generate --with-form` after route changes.

**Advisor read path (B3, explicit).** No new advisor *write* endpoint — advisors read only (SELECT-only RLS,
§3). The advisor entrepreneur-detail controller (`Advisor\EntrepreneurController@show`) payload gains a
`budget` block (summary + computed runway/break-even + **unresolved flags** — the exact filter is
**`raised === true && resolved !== true`**, §5.4), rendered read-only on the existing advisor detail page. The
budget summary reaches the advisor through this payload, not a separate advisor budget route.

---

## 10. CLAUDE.md rules block

Add the spec's **§9 "Budget & Runway Builder Rules"** block to `CLAUDE.md` (adapted to repo names:
`entrepreneur_budgets`; **runway source = `entrepreneur_budgets.expected_runway_months`** (the budget row,
**not** `ReadinessAssessment.responses` — no readiness-controller change; §3/§5); no `advisor_cost_*`
column; governed framework recalibration; examiner/coach prompt classification). The advisor-line integrity rule and
flag-and-acknowledge rule are the load-bearing ones.

---

## 11. Testing (gates stay green)

- **Calc engine** — worked-example unit tests: runway (incl. "12+"), break-even (incl. "not reached"),
  Committed+Conditional funding (Seeking excluded), contingency, COGS percent-vs-per-unit.
- **Flags** — runway-mismatch threshold (admin-config), funding shortfall, no-break-even; acknowledge logs a
  timestamp + is advisor-visible; never hard-blocks.
- **Flag re-raise lifecycle (§5.4)** — acknowledged → resolved → re-raised **clears `acknowledged_at`** so the
  flag shows as a fresh, unacknowledged raise to the advisor (a stale ack must not hide it); `raised_at` bumps
  and `resolved_at` clears; each transition is audited.
- **Advisor "unresolved" predicate (§5.4)** — the advisor `budget` payload includes a flag iff
  `raised === true && resolved !== true`: assert a resolved flag **disappears** from the panel and a re-raised
  flag **reappears unacknowledged**.
- **Budget evidence reaches the scorer (P1)** — assert the budget criterion's `scoreCriterion()` input **and**
  `heuristicScore()` receive the `budget_evidence` block (computed/status/`expected_runway_months`/flags), not
  just `sections_text` — a budget with a funding shortfall scores differently from a clean one.
- **Advisor line integrity** — assert the advisor figure is **never** persisted to `entrepreneur_budgets`,
  **never** in `computed`, **never** in the scoring payload; dismiss fires the one-time nudge once ever.
- **Scoring** — budget criterion contributes; `not_started` excluded (not zero), `partial` reduced weight;
  revised > acknowledged; framework recalibration is **governed** (queued, owner-approved, new published
  version) — no silent change.
- **Admin rubric management (v1.23)** — Admin can view the latest published entrepreneur rubric and prior
  versions; draft edits validate unique criterion numbers, complete descriptors, ordered grade bands, and active
  weights = 100; publishing creates a new framework version with audit events and leaves prior assessment
  `rating_framework_id` snapshots unchanged; new assessments use the newly published version. A Layer 18
  learning update can stage/prefill a draft but cannot change the live rubric until Admin publishes it.
- **Shared helper across all ten readers** — one regression test asserts an excluded/partial criterion is
  handled **identically** by every weighted-score reader, **including the three learning layers**
  (`RatingPredictiveValidity`, `ConversionOutcomeLearning`, `PlanQualityBenchmarks`) — none compute their own
  `Σ score × weight/100`, so learning + benchmark outputs don't skew when the budget criterion is excluded.
- **Replayable weighting + excluded-vs-failed (P1)** — a persisted round renormalises from its **own**
  `weight_status`/`effective_weight_multiplier`/`excluded_reason` (not the live budget), so an old round's grade
  is stable after the budget changes; assert an **excluded** criterion (`excluded_reason` set) renormalises
  while a **failed/absent** score surfaces as a data gap, not a silent zero.
- **Advisor override keeps weighting (P1)** — adjusting a `not_started`/`partial` budget criterion via
  `adjustScore()` changes the score value but the helper still applies the **AI row's** frozen multiplier
  (advisor row carries no metadata).
- **Legacy default vs data gap (P1/P2)** — a **canonical** score row (criterion_number + numeric score) with no
  metadata defaults to `complete`/`1.0`/null (existing assessments unchanged); a row with a **missing/malformed
  score** lands in `ScoringResult.dataGaps`. The two non-canonical legacy fixtures (`TestingSeedDataSeeder:1352`,
  `GamificationTest:153`) are migrated to canonical rows and still pass.
- **Data-gap contract + all consumers (P1)** — an unscored criterion is **dropped from `overall`** (not zeroed)
  and listed in `dataGaps`; **`finalise()`, `AdvisoryReadiness::evaluate()`, and `LivingPlan::reassess()` each
  skip/block** on a gap (assert a fresh gap-containing assessment emits **no** readiness signal); pure display
  reads return `overall` without throwing.
- **Incomplete grade not leaked (P1)** — a gap-containing first-pass persists `overall_grade = 'incomplete'`
  (not a band); assert the dashboard/plan raw-grade reads **and the payload/advisor-detail card** all show
  *Incomplete* (the payload returns `incomplete`, not a recomputed band), and grade-up does not fire on it.
- **Finalise transition (P1)** — resolve the gap (advisor scores the criterion), `finalise()` **recomputes and
  persists the real band before milestones**; assert the finalised assessment's grade is a real band (not
  `incomplete`) and the milestone snapshot captures the real grade.
- **ReportComposer refuses the whole report on gaps (P1)** — assert a gap-containing assessment produces **no**
  `Report`, **no** sections, **no** PDF, **no** `PvCalculation`, and **no** audit event (the transaction persists
  nothing); once gaps resolve, the PV + real grade + report are written.
- **Advisor scoring resolves a gap (P2)** — an `advisor_scores` row (`criterion_number` + numeric `score`) on a
  gap criterion empties `dataGaps` and the criterion scores at **full weight** (no AI metadata → `complete`/`1.0`);
  on a `partial` budget criterion it keeps the AI `partial` multiplier; finalise then persists a real band.
- **Legacy advisor shape ignored (P2)** — a summary `advisor_scores` (`{overall, note}`, no `criterion_number`)
  is **not** read as a score (criterion falls back to its AI score); the two migrated fixtures carry canonical
  `ai_scores` **and** `advisor_scores` (or none) and still pass.
- **Normalised rows + contribution math (P2)** — with the budget criterion excluded/partial, the per-criterion
  **payload + report rows** show the **effective** weight + `excluded_reason`, not raw `$criterion->weight`;
  **`row.contribution === score × (effective_weight / 100)`**; the remaining rows' effective weights sum to 100.
- **Seeder identity guard (P2)** — re-running `BudgetCriterionRevisionSeeder` when the budget criterion is
  already present is a no-op (identity, not count); against a future 13-criterion framework it **does not**
  republish a 12-only baseline (never downgrade).
- **AI isolation** — the realism (examiner) prompt and the Assist (coach) prompt are classified in
  `EntrepreneurPromptRegistry`; the content-firewall test still passes (rubric not in coach, coaching not in
  examiner).
- **Gamification gate** — `budget_built` milestone awarded only when `gamification_on`; invisible otherwise.
- **RLS / auth (the §3 owner-derivation; read/write split)** — the owning entrepreneur can **read and write**
  their own budget; the **assigned advisor can read but NOT write** it (an advisor INSERT/UPDATE is rejected by
  the write policy); **entrepreneur B cannot see or write entrepreneur A's** budget (cross-entity denial); an
  **SME client / client-scope user gets no access** through the entrepreneur routes; and a row whose
  `business_plan_id` points at another owner's plan is unreachable (the through-`business_plans` derivation,
  not a denormalised key).
- **`complete` requires the runway value** — a data-complete budget with **no `expected_runway_months`** stays
  `partial` (not `complete`/full weight) and emits the named improvement rec.
- **`expected_runway_months = 0` is present, not missing** — a `0` self-report reaches `complete` (when A–D are
  also done) and fires Flag 1 against the calc; assert `0` is **not** treated as absent (the resolver uses
  `!== null`, not truthiness).
- **No budget row = `not_started`** — a plan with no `entrepreneur_budgets` row reads `not_started`: budget-runway
  incomplete, criterion **excluded** from scoring (renormalised, not zero), scorer receives `not_started`
  evidence; no null-deref. (No eager backfill — the row is created on first save.)
- **No regressions** — entrepreneur plan/assessment/gamification suites stay green. Static gates:
  `pint --test`, `tsc --noEmit`, ESLint, Prettier.

---

## 12. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **B1** | Schema + calc engine + requirement source | `entrepreneur_budgets` migration (+ RLS); `budget-runway` added to **`PlanRequirements` *and* `EntrepreneurPlanController::PLAN_REQUIREMENTS`** — or consolidate to one source (preferred); `completion`/`requirementComplete` **and the controller's `requirementsPayload()`/`requirementsCompletion()`** count it via budget `status` (or consolidate the controller onto the service — the controller path is PlanSection-only today, [EntrepreneurPlanController.php:623](app/Http/Controllers/Portal/EntrepreneurPlanController.php:623)); `BudgetCalculator` (pure, fully unit-tested); `EntrepreneurBudget` persist/status service. |
| **B2** | Builder UI (structured branch) + autosave | `Plan.tsx` **structured branch** so `budget-runway` renders the Budget panel (**four input groups A–D** + the **`expected_runway_months` input** — required to reach `complete` per §3 — + computed outputs, repeatable rows, autosave → recompute), **not** a prose `<textarea>`; status indicator. **B2 captures + persists the runway value** on the budget row (PUT autosave); B3 owns only the *comparison* against the calc. |
| **B3** | Flags + runway cross-check + advisor visibility | **3 budget flags** (flag-and-acknowledge) + advisor-line nudge state; **runway cross-check** of the calc against `entrepreneur_budgets.expected_runway_months` (persisted by B2 — no readiness-controller change); open-ended runway comparison (§5); admin threshold; acknowledgement timestamps. **Advisor visibility (explicit deliverable):** extend the advisor entrepreneur-detail controller (`Advisor\EntrepreneurController`) payload with the budget summary + **unresolved flags** (filter `raised === true && resolved !== true`, §5.4), rendered read-only on the advisor detail page (§8). No advisor write route — advisor RLS is SELECT-only (§3). |
| **B4** | Advisor Cost Impact Line | FeeCalculator-derived (client-less entry-level rate); presentation-only; editable/dismissible; one-time nudge; integrity tests. |
| **B5** | Charts | The three Recharts/Meridian-Warm visuals. |
| **B6** | Scoring + framework recalibration + normalisation | **Owner rubric supplied in v1.22** (`Budget` = criterion #12 at 12%; 12 weights = `8/7/8/8/9/8/8/7/9/8/8/12`, total 100; Budget uses the standard four grade-band descriptors). **Admin rubric management UI (v1.23):** Admin can view current + historical entrepreneur rating-framework versions, create draft revisions, edit criteria/weights/descriptors/grade bands, validate weights = 100, and publish a new audited version; never edit published frameworks in place, and existing assessments keep their historical `rating_framework_id`. **Add-criterion** governed path on `RatingFrameworkManager` (today `revise()` only *updates* existing criteria — extend it to **add**; relax `assertCompleteFoundingValues` to "all current criteria present + weights = 100"); **`FOUNDING_CRITERIA` stays 11** (budget criterion is revision data, not a founding constant) so founding seeds/tests (`RatingFrameworkSeeder`, `FoundingRatingFrameworkValuesSeeder`, `test_founding_framework_seeds_eleven...`) stay 11; **new `BudgetCriterionRevisionSeeder`** — an **owner-approved baseline seed** that **directly** creates the published **12-criterion** version (weights = 100) mirroring `FoundingRatingFrameworkValuesSeeder` (**no `User` actor** for the create, in a `DB::transaction`), **records a seed-time `AuditWriter` event (system/null actor)** for the version, **idempotent on the budget-criterion *identity*** (`number === 12` + name/marker, **never the count** — `rating_criteria` has no stable key; never downgrade a newer published framework), **wired into `DatabaseSeeder` + the test seed flow**; **not** the manager's `revise()`/`publish()` (those need a `User $actor`) and **not** `queueGovernedChange()` (which only *detects*); the governed queue is the *runtime* admin path (§6); **+ tests** (12 criteria, weights = 100, budget criterion scores; re-running the seeder is a no-op; it does **not** downgrade a 13-criterion framework); **persist the score-row weighting contract** (`weight_status`/`effective_weight_multiplier`/`excluded_reason` in `ai_scores`) so the helper renormalises from the snapshot and old rounds replay — the helper merges the **advisor `score`** with the **AI row's frozen metadata** (advisor overrides keep their weighting; metadata single-source in `ai_scores`), and **defaults missing metadata to `complete`/`1.0`/null** so legacy + non-budget rows are unaffected (no backfill; update only budget-behaviour fixtures); **feed the scorer the structured budget** — `Assessment::scoreCriterion()` **and** `heuristicScore()` get a `budget_evidence` block (computed/status/`expected_runway_months`/flags) for the budget criterion, since today both see only `sections_text` ([Assessment.php:166-218](app/Services/Entrepreneurs/Assessment.php:166)); recalibration via Layer 18 (owner-approved); **one shared `effective_weight`/normalisation helper routed through *every* score reader — ten, confirmed** (`Assessment::weightedScore` [:224](app/Services/Entrepreneurs/Assessment.php:224), payload, `AdvisoryReadiness`, `ReportComposer`, `Revision`, `Benchmarking`, `EntrepreneurMilestones`, **plus the three learning layers** `Learning\Layers\RatingPredictiveValidity::planScore`, `ConversionOutcomeLearning::planScore`, `PlanQualityBenchmarks::score` — grep to confirm none compute their own) so excluded/partial criteria renormalise, not zero (else learning + benchmark outputs skew); the helper returns a **`ScoringResult { overall, rows[], dataGaps[] }`** — `rows[]` carry `effective_weight` + **`contribution = score × (effective_weight / 100)`** (consumed by **payload + report** instead of raw `$criterion->weight`, [BuildsEntrepreneurAssessmentPayload:74-81](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:74)), and **`dataGaps[]`** lists unscored criteria; **every *decision/write* consumer skips/blocks while `dataGaps` is non-empty** — `Assessment::finalise()`, `AdvisoryReadiness::evaluate()` ([:32](app/Services/Entrepreneurs/AdvisoryReadiness.php:32)), `LivingPlan::reassess()` ([:89](app/Services/Entrepreneurs/LivingPlan.php:89)), grade-up, `Revision` deltas, learning layers, **and `ReportComposer` — refuse the *whole* report (gate at the top [:312-314](app/Services/Reports/ReportComposer.php:312); create **no** PV/Report/sections/PDF/audit while gaps exist)** — not just `finalise()`; **`firstPass()` persists `overall_grade = 'incomplete'` (sentinel, not a band) while gaps exist** ([:66](app/Services/Entrepreneurs/Assessment.php:66)); **`finalise()` recomputes a gap-free `ScoringResult`, asserts no `dataGaps`, and persists the real band *before* milestones/report** ([:151-164](app/Services/Entrepreneurs/Assessment.php:151)); **the payload returns `overall_grade='incomplete'` + `dataGaps` when gaps exist** (not `gradeFor(sum(contribution))`, [BuildsEntrepreneurAssessmentPayload:20-27](app/Http/Controllers/Portal/Concerns/BuildsEntrepreneurAssessmentPayload.php:20)) so cards/advisor detail match the raw column. The legacy default applies only to a **canonical** row (`criterion_number` + numeric `score`); a missing/malformed score is a reported data gap; **migrate the two non-canonical legacy fixtures — both `ai_scores` and the summary `advisor_scores`** (`TestingSeedDataSeeder:1352/1358`, `GamificationTest:153/154`) — to canonical rows (or drop the override); examiner realism prompt in registry. |
| **B7** | AI Assist nudge + gamification tie-in | Coach nudge in revenue/funding fields (once per field); `budget_built` milestone behind `gamification_on`. |

Branch `wo/B1-budget-schema`, etc. PR title leads with the WO ID.

---

## 13. Open decisions / verify before/while building

1. **FeeCalculator without a `Client`** — confirm the cleanest client-less "entry-level pre-revenue rate"
   path (a `FeeCalculator`/`ServiceRateManager` method, vs. a small dedicated resolver). Don't fabricate a
   `Client`. *(Affects B4.)*
2. **Resolved — Budget rubric baseline.** The owner supplied the committed criterion name + weights from the
   rubric image: **#12 Budget = 12%**, with the 12 weights
   `8 / 7 / 8 / 8 / 9 / 8 / 8 / 7 / 9 / 8 / 8 / 12` totaling 100, and approved the standard descriptor pattern
   for `Budget`. `BudgetCriterionRevisionSeeder` therefore has concrete production rubric values. The mechanics
   are unblocked: `assertCompleteFoundingValues` relaxes to "all current criteria present + weights = 100";
   `revise()` extends to add a criterion; `FOUNDING_CRITERIA` stays 11; the runtime admin path reuses the
   existing rating-framework revise/confirm flow + governed queue (Layer 18).
3. **Canonical self-reported runway (P1) — resolved: on the budget row.** `expected_runway_months` is a
   column on **`entrepreneur_budgets`** (§3), captured by the Budget Builder. This was moved off
   `ReadinessAssessment.responses` because readiness rows are **append-only history** (new row per
   submission → "which row / carry forward?" is undefined) and a flat numeric there would poison
   `Readiness::score()`. **No readiness-controller change is needed.** Only open: the capture UI copy/placement
   in the builder.
4. **`partial` weight formula — fixed:** effective weight = full × (populated components / **5**) over the
   **five completion components** — input groups A–D **+ `expected_runway_months`** — so A–D complete with no
   runway = 4/5, **never full** (the spec's "five" miscounts the *calculated output* group, which is **not** a
   component — the runway capture is). Single formula, used by the §6 helper, the status resolver, and the tests.

---

*Aligned with Spec V2.4 §3 (AI Integrity — the runway cross-check replaces self-report with checked
arithmetic; advisor line structurally absent from scoring) and §4 (RLS, audit). Entrepreneur-module scoped;
does not alter SME/DD/NPO behaviour. Integrates with the shipped gamification + AI-isolation guard.*
