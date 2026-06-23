# Future Shift Advisory — Entrepreneur AI Content-Isolation Plan

**Source:** the entrepreneur tech spec's **#1 HARD RULE** — the plan-building AI and the scoring AI must be
isolated so the coach never sees the rubric (no "teaching to the test") and the examiner never coaches
(`FSA_Entrepreneurial_Platform_TechSpec_v1.docx` §5, §6.3, §9; HARD RULE 6 = enforce it in CI). Surfaced
by the spec-vs-build gap analysis ([ENTREPRENEUR-SPEC-GAP-ANALYSIS.md](ENTREPRENEUR-SPEC-GAP-ANALYSIS.md) item 8).
**Decision (owner):** **Option B — enforce the content firewall + add the CI cross-contamination test, on
the existing single `AiClient` (one key).** Not the spec's two-key infrastructure split (rejected: the
monolith shares one `AiClient` across ~a dozen subsystems; a second key for the entrepreneur path adds
ops cost for marginal gain over a *tested* firewall, and B layers into A later without rework).
**Track scope:** an integrity guard on the entrepreneur **plan-building vs scoring** AI paths only. No
behaviour change to other `AiClient` consumers (analysis, DD, NPO, verification, red-flags). No new key.
**Predecessors:** entrepreneur module live in the Laravel monolith. Builds on `AiClient`, `PromptEnvelope`,
`Services\Entrepreneurs\Guidance`, `Services\Entrepreneurs\Assessment`, `RatingFramework`/`RatingCriterion`.
**Work orders:** **WO-AI1** (the guard + CI test + doc); **WO-AI2** (optional runtime hardening).
**Sequencing:** ships **before** gamification (owner wanted the integrity decision settled first). Its own
green commit on `featureApp`; `main` fast-forwards only when the suite is green.
**Plan version:** 1.4 — owner approved Option B; four review passes folded in (v1.4 = no blocking findings). *(Build target: Codex, into the test env, then push to live.)*

> **v1.4 revision (review pass — no blocking findings).** Folded in one implementation caveat: the
> method-discipline test must **reset the recorder after setup** (or build plans via factories) because
> `GuidanceTest` / `AssessmentTest` / `AssessmentReportTest` create plans through
> `IdeaValidationService::evaluate()` — a real setup-time `analyse()` call that would otherwise false-trip
> the assertion (§3.1). No behaviour change to the design.

> **v1.3 revision (review pass — wording precision).** Three tightenings (no behaviour change):
> (P3) the examiner coaching scan now reads **non-rubric *and non-founder*** fields — `input.sections_text`
> (`Assessment` ~line 178) is founder text and is redacted; the only system non-rubric key scanned is
> `business_plan_id` (§2).
> (P3) WO-AI2 names the **non-examiner** (`Guidance`, `IdeaValidationService`) vs **examiner**
> (`Assessment`) paths, instead of "plan-building / scoring" (§7).
> (P3) the registry guard prefers an explicit `EntrepreneurPromptRegistry` (or a scan of **all `app/`**, not
> just `app/Services/Entrepreneurs`) so the "classify any new `entrepreneur.*` prompt" promise actually
> holds (§3.1).

> **v1.2 revision (review pass — scan scope & classification enforcement).** Three fixes:
> (P2) **Examiner coaching scan exempts the rubric block `input.criterion.*`** (`Assessment` ~line 173) —
> anchor descriptors may read "follow-up"/"gaps"/"improve" and must not be flagged; coaching is defined as
> guidance/NZ-resource/gap-fix content *outside* the rubric (§2, §3.1).
> (P2) **Prompt-id registry guard** — a static test enumerates `entrepreneur.*` prompt ids under
> `app/Services/Entrepreneurs` and fails on any unclassified one, so a *new* prompt can't ship un-sided
> (the path-driven test alone only covered today's three) (§3.1, §7).
> (P3) **`composer audit` overstated** — `lint.yml` runs Pint/Prettier/ESLint/TypeScript only (verified);
> dropped the `composer audit` claim (not in CI) (§6).

> **v1.1 revision (review pass — testability & scan correctness).** Four fixes + the scope question
> resolved: (P1) scan **system-controlled fields only** — both prompts embed founder plan text
> (`section.body`/`sections_text`/`concept_summary`/`idea`), which is redacted so a founder typing
> "improve"/rubric-like words can't trip the firewall (§2). (P2) dropped the **non-existent `max_points`**
> field; the test uses a **sentinel framework + structural assertion** instead of substring-matching generic
> bands ("Strong"/60/90) (§2, §3.1). (P2) require a **`RecordingAiClient` double** (FakeAiClient doesn't
> record) driving the **public** paths, since `Assessment::scoreCriterion` is private (§3.1). (P3) the gate
> is the **PHPUnit job** (`tests.yml` runs `phpunit` only) — dropped the inaccurate Pint/tsc/ESLint claim
> (§6). (Open Q) scope is defined by **explicit prompt ids** — `plan_score_criterion` (examiner) vs
> `plan_guidance` + `idea_validation` (non-examiner) (§2).

> Why this matters: it is a direct expression of the **AI Integrity Principle** (CLAUDE.md §3 — honest,
> evidence-based, not gamed). The risk it closes is *regression*: the separation holds today by good
> design, but rests on convention with no test.

---

## 1. The integrity property (what we are protecting)

> The AI that **helps build** the plan must not know **how it is scored** (weights, anchor descriptors,
> grade bands) — otherwise it coaches the entrepreneur to game the score and the assessment stops being
> honest. The AI that **scores** must not **coach** — it evaluates a completed plan objectively.

This is the spec's two-AI isolation intent. We enforce the *intent* (content separation) and make it
*regression-proof* (a CI test), without the *mechanism* (two keys).

### Current state (verified — the firewall holds today, unguarded)
- `AiClient` already separates by **method**: plan-building uses `summarise()`, scoring uses
  `scoreCriterion()`.
- **Plan-building (`Guidance::guide`)** sends section text, heuristic gap tags, NZ resources, and
  past-plan patterns — **no** `RatingFramework` criteria, weights, descriptors, or grade bands. Its
  predictive score is a *local heuristic*, not an LLM call fed the rubric.
- **Scoring (`Assessment::scoreCriterion`)** sends one criterion's descriptors + the plan sections — a
  separate path; it does **not** carry coaching/next-steps content.
- **Gap:** one shared `AiClient`/key, and the separation is **convention with no test** — nothing stops a
  future change from leaking the rubric into a guidance prompt (or coaching into a scoring prompt).

---

## 2. The invariant — by prompt ID, on system-controlled fields only

The boundary is defined by **explicit prompt IDs** (not a vague notion of "plan-building AI" — that drifts):

| Side | Prompt IDs (service · `AiClient` method) | Rule |
|---|---|---|
| **Examiner** (scoring) | `entrepreneur.plan_score_criterion` (`Assessment` · `scoreCriterion`) | must **NOT contain coaching** |
| **Non-examiner** (coach + pre-plan) | `entrepreneur.plan_guidance` (`Guidance` · `summarise`); `entrepreneur.idea_validation` (`IdeaValidationService` · `analyse`) | must **NOT contain the scoring rubric** |

`idea_validation` is a **pre-plan concept gate** — it carries no rubric today and sits on the "no-rubric"
side; naming it explicitly keeps it from drifting into either role. Any *new* entrepreneur prompt id must
be classified here before merge.

**A — Non-examiner prompts never contain scoring mechanics.** Forbidden: the framework's
`RatingCriterion.weight` values, `RatingCriterion.descriptors` (anchor text), and `RatingFramework.grade_bands`
(band labels / cut-scores). *Allowed:* criterion/section **names** and educational guidance — the
entrepreneur is meant to have those; the secret is **how points are awarded**. **Note:** there is **no
`max_points`** field on `RatingCriterion` (it carries `number` / `weight` / `descriptors`) — do not assert
on a non-existent field.

**B — Examiner prompts never contain coaching.** "Coaching" = guidance / NZ-resource / gap-fix content
**outside the rubric block** — improvement / "next-steps" language, resource recommendations, or the
`Guidance` educational `body`. *Allowed:* the criterion descriptors (the examiner needs the rubric) + the
plan content. **The coaching scan therefore exempts `input.criterion.*`** (`Assessment` ~line 173): rubric
anchor descriptors may legitimately read "follow-up", "gaps", "test", or future "improve"-style wording,
and must not be flagged. It also exempts the founder's own text — the scorer's `input.sections_text`
(`Assessment` ~line 178) is the plan content and is redacted. So the coaching scan targets the examiner
envelope's **non-rubric, non-founder** fields only: `task`, `body`, the integrity preamble, and the
system-injected non-rubric `input` keys (today just **`business_plan_id`**) — not `criterion.*`, not
`sections_text`.

**Scan system-controlled fields only — never the founder's own words.** Both real prompts embed
user-authored text — `input.section.body` / `input.sections_text` (Guidance / Assessment) and
`input.concept_summary` / `input.idea` (idea validation). A founder who types "improve" or rubric-like
phrasing must **not** trip the firewall. So each scan runs on a **whitelist of system-controlled fields**
— `task`, `body`, the integrity preamble, and the **system-injected** `input` sub-keys — and
**excludes/redacts the provenance-bearing fields** (`section.body`, `sections_text`, `concept_summary`,
`idea`). Two further scope rules per direction:
- **Rubric-absence scan (non-examiner):** the framework's criterion descriptors / weights / grade-bands
  must be absent. (`criterion.*` doesn't appear in those envelopes anyway.)
- **Coaching-absence scan (examiner):** `input.criterion.*` is **exempt** — it is system-controlled but is
  the rubric the examiner is *supposed* to hold, not coaching.
(Longer term `PromptEnvelope` could tag system-vs-user-vs-rubric fields; v1 uses an explicit per-prompt-id
allowlist.) The shared `IntegrityPreamble` is scanned but is neither rubric nor coaching, so it passes.

---

## 3. Enforcement — test-first (HARD RULE 6), optional runtime guard

### 3.1 CI cross-contamination test (the core deliverable) — `tests/Feature/Entrepreneurs/AiContentIsolationTest.php`
Mirrors the spec's CI isolation test, against the real prompt builders **via their public entry points**:

- **Recording AiClient double — required.** `FakeAiClient` does **not** capture prompts, and
  `Assessment::scoreCriterion` is **private**. So bind a **`RecordingAiClient`** test double that records the
  `PromptEnvelope` handed to each method (`summarise` / `scoreCriterion` / `analyse`), and drive the
  **public** paths: `Guidance::guide()`, `Assessment::firstPass()` (calls `scoreCriterion` internally), and
  `IdeaValidationService::evaluate()`. Never call the private method directly.
- **Sentinel framework + structural assertion (not generic substrings).** Seed a published `RatingFramework`
  whose criterion **descriptors**, **grade-band labels**, and **weights** are **unique sentinels**
  (e.g. descriptor `RUBRIC_SENTINEL_DESC_A`, band `RUBRIC_SENTINEL_BAND`, weight `37.13`) — the real
  labels/mins are generic ("Strong"/"Developing"/`60`/`90`) and would make substring tests noisy. Extract
  the **system-controlled fields** (§2) of each non-examiner envelope (`plan_guidance`, `idea_validation`)
  and assert they contain **none** of those sentinels / framework descriptor·weight·grade-band values.
- **Examiner must not coach.** Extract the `plan_score_criterion` envelope's system-controlled fields
  **except the rubric block `input.criterion.*`** (which legitimately holds anchor wording like
  "follow-up"/"gaps"/"improve") and assert no coaching markers (the `Guidance` `body`, NZ-resource titles,
  gap-fix / next-steps phrasing) appear in those **non-rubric** fields. Include a positive control: a
  rubric descriptor containing "improve" must **not** trip the scan.
- **Founder-text safety.** Add a founder whose `section.body` / `concept_summary` deliberately contains
  "improve", "next steps", and a rubric-like phrase — assert the firewall **does not** flag it (those
  fields are redacted from the scan, §2).
- **Prompt-id registry guard (enforces §2's "classify before merge").** Prefer a small explicit
  **`EntrepreneurPromptRegistry`** (every `entrepreneur.*` prompt id mapped to `examiner`/`non-examiner`,
  and the services build their `PromptEnvelope`s from it). The static test asserts **every** such id is
  classified. Where a source scan is used instead, scan **all of `app/`** for `entrepreneur.*`
  `PromptEnvelope` ids — **not** just `app/Services/Entrepreneurs` (that matches today's layout but not the
  promise that *any* new `entrepreneur.*` prompt anywhere must be classified). Either way the test **fails
  on any unclassified id**, so a new prompt can't ship un-sided. (Closes the gap that the path-driven test
  only covers *today's* three prompts.)
- **Method discipline.** From the recorder, assert plan-building only invoked `summarise`, scoring only
  `scoreCriterion`, idea-validation only `analyse` — they never converge on one builder/method.
- **Recorder hygiene (implementation note).** Existing helpers in `GuidanceTest` / `AssessmentTest` /
  `AssessmentReportTest` build a plan by running `IdeaValidationService::evaluate()` (a real `analyse()`
  call) **during setup** — so a naive method-discipline assertion would see setup-time `analyse()` before
  the path under test. Either **`$recorder->reset()` after setup**, build the plan records directly via
  factories (no AI call), or scope the assertion to calls captured **during the act phase only**. Without
  this the discipline check false-positives.
- Wire into the **PHPUnit job** (`tests.yml`, §6) so a leak **fails the build**.

> **Expected result on first run: PASS** — §1 shows the firewall already holds. The test's value is
> locking it against regression. *If it fails on first write, that is a real present-day leak to fix —
> report it, don't weaken the test.*

### 3.2 Optional runtime guard (WO-AI2, hardening) — `PromptFirewall`
A small helper the entrepreneur paths call before dispatching: `PromptFirewall::assertNoRubric($envelope)`
(non-examiner) / `assertNoCoaching($envelope)` (examiner), throwing if violated. It operates on the **same
system-controlled-field allowlist (§2)** so founder text never trips it. Belt-and-suspenders over the test;
keep out of the hot path elsewhere. Optional because the test already enforces the boundary at build time.

### 3.3 Document the rule
Add the invariant to the entrepreneur architecture doc (and a one-line pointer in CLAUDE.md, where the
spec also placed its hard rule), so future contributors know plan-building prompts and scoring prompts
must never cross-contaminate, and that the CI test enforces it.

---

## 4. Explicitly NOT in scope

- **Two API keys / two `AiClient` instances** (the spec's infra mechanism) — deliberately not done; B
  achieves the integrity intent at far lower ops cost and is forward-compatible with A.
- **Other `AiClient` consumers** — analysis, DD, NPO, document verification, red-flags. They are not a
  coach-vs-examiner pair; the invariant is entrepreneur-specific.
- **Changing the scoring model.** Today's scoring is heuristic + LLM-rationale; this plan does not alter
  that. (If scoring later becomes a pure high-stakes LLM judgment, revisit two-key isolation — §5.)

---

## 5. When to escalate to full two-key isolation (Option A)

Not now. Reconsider if **either**: (a) entrepreneur scoring becomes a *pure* LLM judgment at high stakes
(not the current heuristic+rationale blend), or (b) a security/compliance review wants API-key
blast-radius isolation for its own sake. A layers cleanly on top of B: bind two `AiClient`s and route
`summarise`(guidance) vs `scoreCriterion`(scoring) to separate keys — the §2 invariant and §3 test carry
over unchanged.

---

## 6. Testing & gates

- The §3.1 isolation test (the deliverable) + a regression fixture.
- **No regressions:** existing entrepreneur guidance/assessment suites stay green.
- **Where it runs:** the isolation test lives in the **PHPUnit job** — `.github/workflows/tests.yml`, which
  runs `./vendor/bin/phpunit` (line ~57). That is the gate it must be in. The **separate** `lint.yml`
  workflow runs **Pint, Prettier, ESLint, and TypeScript only** (verified) — not this test, and **not
  `composer audit`** (which is not in CI today). So this plan claims **only** the PHPUnit gate; it's a pure
  PHP test, so TS/ESLint/Prettier/`composer audit` do not apply here.
- The isolation test must be in the **required** PHPUnit gate, not optional.

---

## 7. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **WO-AI1** | Content-isolation CI test + documented invariant | A `RecordingAiClient` double + `AiContentIsolationTest`: drives the public paths (`Guidance::guide`, `Assessment::firstPass`, `IdeaValidationService::evaluate`), scans **system-controlled fields only** (founder text redacted; the examiner coaching scan **exempts `input.criterion.*`**), uses a **sentinel framework** for a structural rubric-absence assertion, plus coaching-absence, founder-text-safety, method-discipline, and a **prompt-id registry guard** (fails on any unclassified `entrepreneur.*` prompt id) — scoped by prompt id (§2). Wired into the required **PHPUnit** gate (`tests.yml`); invariant documented in the entrepreneur architecture doc + CLAUDE.md pointer. |
| **WO-AI2** *(optional)* | `PromptFirewall` runtime guard | `assertNoRubric` (called by the **non-examiner** paths — `Guidance::guide`, `IdeaValidationService::evaluate`) / `assertNoCoaching` (called by the **examiner** path — `Assessment::firstPass`→`scoreCriterion`); throw + audit on violation, on the same system-controlled-field allowlist (§2). Belt-and-suspenders over WO-AI1. |

Branch `wo/AI1-entrepreneur-ai-isolation`. PR title leads with the WO ID. Ships before the gamification WOs.

---

*Aligned with Spec V2.4 §3 (AI Integrity) and the entrepreneur tech spec's two-AI isolation intent.
Implements the intent (content separation, CI-enforced) without the two-key mechanism — Option B.*
