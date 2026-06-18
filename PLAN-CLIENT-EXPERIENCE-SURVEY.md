# Future Shift Advisory — Client Experience Survey Plan (Engagement Validation)

**Source:** owner request (this session) — survey the people we serve (SME **clients** + **entrepreneurs**) on whether the reviews/reports we set out to deliver were *received*, *accessible*, and *met expectations*; admin-activated per individual portal.
**Track scope:** add an **additive** engagement-validation survey lane (a dedicated module) on the client + entrepreneur portals, feeding a governed learning layer. No existing SME/DD/entrepreneur/NPO/broker/coach/portal behaviour is modified.
**Predecessors:** Phases 1–4 + NPO + admin-data/credentials tracks, green on `main`/`featureApp`. Builds on existing infra: questionnaire + wellbeing patterns, RLS helpers (`fsa_current_role/client_ids/user_id`), `documents_scope` dual-branch RLS, `AuditWriter`, `SecureFileWriter`, `LayerCadenceRegistry`, Spatie `Permission`, Wayfinder.
**Work orders:** **WO-S1 … WO-S7** (survey namespace).
**Sequencing:** S1 (schema/RLS/RBAC) → S2 (authoring) → S3 (activation + snapshot) → S4 (portal response) → S5 (results) → S6 (learning layer 38) → S7 (notifications/offboarding). Each its own green commit on `featureApp`; `main` fast-forwards only when the suite is green (`-d memory_limit=2048M`).
**Plan version:** 1.6 — initial draft + seven review passes folded into the body.

> **v1.6 revision (review pass 7 — post-completion appends, context restore, submit/cancel race).**
> Three fixes, all in the body:
> (P1) **`survey_answers` INSERT also requires the assignment to be open** — the policy now joins
> through the parent response to `survey_assignments.status IN ('pending','in_progress')`, so the
> original submitter can't append late answers after completion (recorder flips to `completed` last) —
> §4.6, §12.
> (P2) **System context must be restored** — the recorder wraps *only* the completion update in the
> save/restore `withSystemContext` helper that already exists on `ProjectSettings` (promote it to
> `RequestContext`); a bare `apply('system')` (à la `WellbeingCheckinService`) leaves the request running
> as `system` — §4.6, §8.
> (P2) **Submit/cancel race closed** — `SurveyResponseRecorder` takes a `SELECT … FOR UPDATE` lock on
> the assignment and completes via a conditional active-status update; cancel uses the same lock, so they
> can't interleave — §8, §12.

> **v1.5 revision (review pass 6 — answer attribution, closed-assignment guard, anchor content CHECK).**
> Three fixes, all in the body:
> (P1) **`survey_answers` INSERT is tied to the parent response's submitter** (explicit policy:
> `EXISTS … survey_responses … submitted_by_user_id = fsa_current_user_id()`), so another `client_team`
> user can't append answers to a response attributed to someone else (§4.6, §12).
> (P1) **Response INSERT now requires the assignment to be active** — a non-system insert must satisfy
> `EXISTS … survey_assignments … status IN ('pending','in_progress')`, blocking submits against a
> cancelled/expired assignment (§4.6, §12).
> (P2) **Answer-shape CHECK validates anchor contents** — requires `source_type` ∈
> {report, document, plan_assessment} and a non-empty `source_id`, so malformed JSON like `{}` (whose
> extracted fields are NULL) can no longer bypass the extracted-field unique index (§4.6, §12).

> **v1.4 revision (review pass 5 — submitter spoofing, anchor symmetry, anchor canonicalisation).**
> Three fixes, all in the body:
> (P1) **No spoofed submitter** — the `survey_responses` INSERT policy now requires
> `submitted_by_user_id = fsa_current_user_id()` for non-system inserts, and the recorder stamps the
> submitter from `auth()->id()`, ignoring any payload value (§4.6, §12).
> (P2) **Anchor validation made symmetric** — the recorder rejects anchored answers aimed at a
> flat/rating/NPS question, and enforces `source_type` = the question's `anchor.deliverable_scope` when
> set (§4.6, §12).
> (P2) **Anchor uniqueness canonicalised** — the anchored unique index keys on extracted
> `anchor_ref->>'source_type'` / `source_id` (not raw `::text`), and the recorder canonicalises
> `anchor_ref` to exactly `{source_type, source_id}`, so extra/reordered JSON keys can't bypass the
> dedupe (§4.6).

> **v1.3 revision (review pass 4 — answer integrity & RLS enablement).** Three fixes, all in the body:
> (P1) **Answer ↔ survey & anchor binding** — denormalised `survey_id` on `survey_responses`/
> `survey_answers` + composite FKs pin every answer to the assignment's survey *and* the question to
> that survey (a foreign `survey_question_id` now violates the FK), and the recorder validates
> `anchor_ref` against the assignment's `deliverable_snapshot` (no invented deliverables) — §4.4/§4.5/§4.6.
> (P2) **Answer-shape CHECK** rejects invalid hybrids (`anchor_ref`/`answer_key` half-set, or an
> unsupported `answer_key`) that slipped past *both* partial uniques (§4.6).
> (P2) **RLS enablement made explicit** — `ENABLE` **and** `FORCE ROW LEVEL SECURITY` on all three
> scoped tables (repo convention), and the stale §2 "one policy per table" line now matches the
> action-specific SELECT/INSERT/write policies (§2, §4.6, S1).

> **v1.2 revision (review pass 3 — write-side RLS, uniqueness NULLs, template freeze).** Three fixes +
> one open decision, all in the body:
> (P1) **Action-specific RLS** — reads stay broad (admin / scoped advisor / subject), but **INSERT on
> `survey_responses` / `survey_answers` is subject-only** (advisors excluded; entrepreneur branch uses
> `ep.user_id`, not `assigned_advisor_id`); assignment writes are super_admin/system (§4.6). Verified
> `fsa_current_role()` carries the user_type via `RequestContext::resolveRole`.
> (P1) **Answer uniqueness split into two partial indexes** — flat `WHERE anchor_ref IS NULL AND
> answer_key IS NULL` and anchored `WHERE … IS NOT NULL` — because Postgres NULLs don't dedupe, so the
> single combined unique let duplicate flat answers through (§4.6).
> (P2) **Published surveys/questions are immutable** — `surveys.update` is draft-only (422 on
> published), edits clone a new draft version (`version` + `supersedes_survey_id`), mirroring
> `QuestionnaireController`; active assignments therefore never drift (§3, §4.1, §8).
> (Open decision) **Activation authority** stays super_admin-only by default, RLS matched; enabling
> advisor activation is a documented RBAC+RLS change (§15.6).

> **v1.1 revision (review pass 2 — submission integrity & snapshot consistency).** Four fixes + one
> open question resolved, all in the body:
> (P1) **`UNIQUE (survey_assignment_id)`** on `survey_responses` — one response per assignment, with
> duplicate/race submit tests (§4.6, §12); two rapid submits can no longer create multiple completed
> responses.
> (P1) `deliverable_rating` is now fully modeled — new **`answer_key`** column (`received` / `accessible`
> / `met_objective`) threaded through the schema, answer-uniqueness, aggregation, validation, and
> learning evidence (§4.5, §5, §8, §9).
> (P2) §7 entrepreneur-route wording corrected: `EnforceClientScope` **still runs** (globally appended;
> it populates `fsa_current_user_id()` for the entrepreneur RLS branch) — entrepreneurs are *not
> client-id-scoped*, but the middleware is not skipped.
> (P2) Snapshot consistency resolved — **v1 anchors only released/visible deliverables**; "not
> received/not accessible" is a respondent-reported discrepancy; produced-but-unreleased capture is
> deferred (§2, §4.7, §14). The generic "tied to `Report` rows" line is now polymorphic.
> (Open question) **Multi-user clients get one shared response per assignment** in v1 (§15.5).

> **v1.0 revision (review pass 1 — schema/RLS correctness).** Five fixes, all folded into the body:
> (P1) entrepreneur rows get a **dual-branch RLS policy** mirroring `documents_scope` — a client branch on `fsa_current_client_ids()` **plus** an entrepreneur branch on `fsa_current_user_id()` — replacing the wrong client-only `current_setting('fsa.client_id')` key that would have made entrepreneur rows invisible (§2, §4.6).
> (P1) one-of CHECK on all three scoped tables + **parent-match composite FKs** so a response/answer cannot drift from its parent's subject (§4.6).
> (P2) **partial unique indexes** enforce one active assignment **per subject** per survey (§4.6, §15.3).
> (P2) **polymorphic** deliverable snapshot — `source_type ∈ {report, document, plan_assessment}` with exact eligibility queries (§4.3, §4.7).
> (P2) S6 **registers learning layer 38** and pins the concrete `learning_updates` payload contract incl. no-PII evidence (§9).
> Notes: permissions renamed plural (`surveys.manage` / `surveys.view`); dual-subject precedent corrected to `Report` / `Document` (not `QuestionnaireResponse`, which is client/NPO).

> Feature plan for Claude Code (and any agentic tool). Read end-to-end before the first edit. This plan
> is additive and must not weaken any existing SME, DD, entrepreneur, NPO, broker, coach, or portal
> behaviour (CLAUDE.md "CRITICAL — DO NOT BREAK").

---

## 1. Purpose

Give the practice a way to **close the loop with the people we serve** — SME **clients** and
**entrepreneurs**: after we have done work for them, ask them directly whether **what we set out to do
actually landed** — whether the reviews and reports we promised were *received*, *accessible*, and
*met their expectations*.

This is an **engagement-validation / experience survey**, not an intake questionnaire. It is
deliberately a separate concern from the existing questionnaire engine (which collects analysis input
data). It answers a different question: *"Did we deliver, and could they use what we delivered?"*
Its *subject* is a client **or** an entrepreneur (v1); NPO board / broker / coach portals come later.

### Locked decisions (confirmed with owner, 2026-06-18)

| Decision | Choice | Consequence |
|---|---|---|
| **Architecture** | **Dedicated survey module** | New `surveys` / `survey_*` models that follow the questionnaire engine's patterns but stay separate. The intake `Questionnaire` engine is untouched. |
| **Activation & audience** | **Per-subject; client + entrepreneur portals** | Admin activates a survey on an individual **client** *or* **entrepreneur**. Audience is `client_primary` + `client_team` (SME) and `entrepreneur`. No global auto-publish; NPO board / broker / coach portals remain out of scope for v1. The survey *subject* is polymorphic (client **or** entrepreneur), via parallel nullable FKs — the repo's established pattern for mixed client/entrepreneur tables (`Report`, `Document`; **not** `QuestionnaireResponse`, which is client/NPO). |
| **Content** | **Deliverable-anchored** | The survey references the *specific* reports/reviews the client received and asks, per deliverable, whether it was received, accessible, and met what we set out to do. |
| **Results & learning** | **Admin + advisor, feeds governed learning queue** | Results visible to admin and the assigned advisor with aggregation. Detected gaps enter the **governed learning update queue** — never silently corrected (AI Integrity Principle, spec §3). |

---

## 2. Non-negotiables this feature must honour (from CLAUDE.md)

- **Honesty (§3).** Low scores and "we never received that report" answers are surfaced verbatim to
  admin + advisor. They are **never** softened, averaged-away, or suppressed. Negative findings enter
  the governed learning update queue; they are not silently corrected.
- **Honesty assurance (mandatory copy).** Responses are *attributed* (not anonymous), so the survey
  **must** lead with a clear, warm assurance: honest feedback is crucial to improving the service and
  portal, and feedback will never — in any shape or form — be held against the client. This statement
  is non-optional and is rendered on the survey intro and reaffirmed at submission (copy in §11).
- **Evidence-based (§3).** Deliverable-anchored questions are tied to *actual* released deliverables —
  reports, documents, and (for entrepreneurs) finalised plan assessments — that the subject was given
  access to, snapshotted at activation (polymorphic, §4.7). The survey does not invent deliverables.
- **Invite-only + MFA (§4).** No new auth surface. All routes sit inside the existing
  `auth` → `verified` → `mfa` → `terms` middleware stack. No public route.
- **Every action audit-logged (§4).** Create / publish / archive / activate / submit / cancel each
  write to `audit_events` via `AuditWriter`. Direct `UPDATE`/`DELETE` on audit rows stays forbidden.
- **No PII in raw logs (§4).** Free-text answers are never logged. Audit payloads store
  `notes_present: bool` / counts, mirroring `WellbeingCheckinService`.
- **Scoping (§4 / PLAN §6.2) — action-specific RLS (read ≠ write).** Per-subject tables
  (`survey_assignments`, `survey_responses`, `survey_answers`) carry **both** a nullable `client_id`
  and a nullable `entrepreneur_profile_id` (one-of CHECK). Each table is `ENABLE`d **and** `FORCE`d for
  row-level security (repo convention — table owners bypass RLS otherwise), and gets **separate
  SELECT / INSERT / write policies** (not one identical policy): a **broad dual-branch SELECT** (client
  on `fsa_current_client_ids()` + entrepreneur on `fsa_current_user_id()`, mirroring `documents_scope`)
  so admins and scoped advisors can read; but **INSERT on responses/answers is subject-only** and
  **assignment writes are super_admin/system** (§4.6). The entrepreneur branch is mandatory — those rows
  have `client_id IS NULL` and a client-only policy would hide them. `SurveyAssignmentPolicy` adds
  app-layer ownership as defence-in-depth, but the DB is the source of truth. CHECK, parent-match,
  answer↔survey binding, and uniqueness are all DB-enforced — see §4.6. Template tables
  (`surveys`, `survey_questions`) have neither key and no RLS (admin-only via route middleware).
- **No file uploads in v1.** A `file-attach` answer type would have to route through `SecureFileWriter`
  (scan → reject infected → encrypt → `Document` + audit). To keep v1 small and within the virus-scan
  guarantee, **file-attach answers are explicitly out of scope** (see §10).
- **No feature outside the plan.** This document *is* the plan record for the survey feature. Anything
  discovered beyond it is raised in the PR description, not silently built.

---

## 3. How it works (end-to-end narrative)

1. **Author a template (admin).** Super admin builds a reusable survey template in a library
   (`Admin → Surveys`), like the Welcome Message / Inspiration Board libraries. A template has a title,
   an intro, and an ordered set of questions. Some questions are **deliverable-anchored** placeholders
   (e.g. "For each report you received…"). Template lifecycle is `draft → published → archived`,
   mirroring `Questionnaire::published_at`. **Once published, a survey and its questions are immutable**
   — editing clones a new draft version (§8), so active assignments and historical results never drift.

2. **Activate per subject (admin).** On a specific **client's** detail page *or* an **entrepreneur's**
   detail page, admin activates a *published* survey. At activation the system **snapshots that
   subject's delivered artifacts**:
   - *Client:* reviewed `Report` rows for the engagement (`ReportType::Client`, `Trajectory`,
     `DueDiligence`, `NpoHealth`, etc.) plus documents/reviews the client was granted access to.
   - *Entrepreneur:* `ReportType::EntrepreneurAssessment` reports, finalised business-plan assessments,
     and documents scoped by `entrepreneur_profile_id`.
   The snapshot is stored on the assignment so the survey is stable even if reports change later.

3. **Subject responds (portal).** `client_primary` / `client_team` see an active survey on the SME
   portal (`Portal → Surveys`); an `entrepreneur` sees it on the entrepreneur portal
   (`Portal → Entrepreneur → Surveys`). Deliverable-anchored questions auto-expand into one block per
   snapshotted deliverable: *"Did you receive the [deliverable]? Could you open/access it? Did it meet
   what we set out to do?"* plus general experience questions (overall satisfaction, optional NPS,
   free-text).

4. **Results + learning (admin + advisor).** On submission: results aggregate for admin and the
   assigned advisor (raw distributions, not just averages). Scores below threshold and explicit
   "not received / not accessible / did not meet objective" answers generate **governed learning
   update candidates** routed to the learning update queue — surfaced, never auto-applied.

---

## 4. Data model

New tables. UUID PKs with `gen_random_uuid()` default and `timestampsTz`, matching repo convention
(`welcome_messages`, `board_posts`).

### 4.1 `surveys` — template (global, admin-owned)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `title` | string | |
| `intro_body` | longText nullable | Warm framing shown before questions |
| `status` | string | `draft` \| `published` \| `archived` (enum `SurveyStatus`) |
| `version` | unsignedInteger default 1 | bumped when a published survey is cloned to a new draft (immutability, §8) |
| `supersedes_survey_id` | uuid nullable, FK self | lineage: the published survey this draft was cloned from |
| `published_at` | timestampTz nullable | Mirrors `Questionnaire`; non-null ⇒ immutable |
| `created_by_user_id` | FK users nullOnDelete | |
| timestamps | | |

### 4.2 `survey_questions` — ordered questions on a template (global)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `survey_id` | FK surveys cascade | |
| `order` | unsignedInteger | |
| `type` | string | enum `SurveyQuestionType` (see §5) |
| `prompt` | string | |
| `options` | jsonb nullable | for select/scale labels |
| `required` | boolean default true | |
| `anchor` | jsonb nullable | non-null marks a deliverable-anchored question, e.g. `{"deliverable_scope":"report"}` |
| timestamps | | |

### 4.3 `survey_assignments` — per-subject activation (scoped)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `survey_id` | FK surveys restrict | the template used |
| `client_id` | FK clients cascade, **nullable** | **RLS key** when subject is a client |
| `entrepreneur_profile_id` | FK entrepreneur_profiles cascade, **nullable** | subject key when subject is an entrepreneur |
| `status` | string | `pending` \| `in_progress` \| `completed` \| `cancelled` \| `expired` (enum `SurveyAssignmentStatus`) |
| `activated_by_user_id` | FK users nullOnDelete | |
| `activated_at` | timestampTz | |
| `due_at` | timestampTz nullable | optional response window |
| `completed_at` | timestampTz nullable | |
| `deliverable_snapshot` | jsonb | frozen **polymorphic** array `[{source_type, source_id, label, delivered_at, reviewed_at, visible_to_subject}]` at activation — `source_type ∈ {report, document, plan_assessment}` (see §4.7) |
| timestamps | | |

### 4.4 `survey_responses` — one submission per assignment (scoped)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `survey_assignment_id` | FK survey_assignments cascade | |
| `survey_id` | uuid | denormalised from the assignment; composite-FK-pinned to it (answer↔survey binding, §4.6) |
| `client_id` | uuid **nullable** | **RLS key** for client subject (denormalised, mirrors `Report`/`Document`) — must equal the assignment's `client_id` (parent-match, §4.6) |
| `entrepreneur_profile_id` | uuid **nullable** | subject key for entrepreneur subject (denormalised) — must equal the assignment's (§4.6) |
| `submitted_by_user_id` | FK users nullOnDelete | which subject user submitted |
| `submitted_at` | timestampTz | |
| `overall_score` | smallint nullable | computed convenience score |
| `nps_score` | smallint nullable | 0–10 if NPS question present |
| timestamps | | |

### 4.5 `survey_answers` — answers (RLS-scoped via response)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `survey_response_id` | FK survey_responses cascade | |
| `survey_question_id` | FK survey_questions restrict | |
| `survey_id` | uuid | denormalised; composite-FKs pin it to both the response's survey and the question's survey (§4.6) |
| `client_id` | uuid **nullable** | **RLS key** for client subject (denormalised) |
| `entrepreneur_profile_id` | uuid **nullable** | subject key for entrepreneur subject (denormalised) |
| `anchor_ref` | jsonb nullable | which snapshotted deliverable this answer is about (`{source_type, source_id}`) |
| `answer_key` | string nullable | for a `deliverable_rating` question, **which metric** this row holds: `received` \| `accessible` \| `met_objective`. Null for flat (non-anchored) questions. |
| `value` | text nullable | free text / select value |
| `numeric_value` | smallint nullable | enables aggregation (ratings, yes=1/no=0) |
| timestamps | | |

> A `deliverable_rating` question emits **one `survey_answers` row per (snapshotted deliverable ×
> `answer_key`)** — e.g. for 3 deliverables it produces up to 9 rows (received/accessible/met_objective
> each). Aggregation (§8), validation, answer-uniqueness (§4.6), and learning evidence (§9) all key on
> `answer_key`.

### 4.6 Constraints, RLS & uniqueness — DB-enforced, not service discipline

**One-of CHECK (all three scoped tables).** `survey_assignments`, `survey_responses`, `survey_answers`
each get: `CHECK ((client_id IS NOT NULL)::int + (entrepreneur_profile_id IS NOT NULL)::int = 1)`.

**Parent-match (denormalised scope cannot drift).** A response's subject must equal its assignment's,
and an answer's must equal its response's — enforce in the DB, not the service layer, via composite FKs:
- add `UNIQUE (id, client_id)` and `UNIQUE (id, entrepreneur_profile_id)` on `survey_assignments`
  (and the equivalent uniques on `survey_responses`);
- `survey_responses (survey_assignment_id, client_id)` → FK `survey_assignments (id, client_id)`, and
  `(survey_assignment_id, entrepreneur_profile_id)` → `survey_assignments (id, entrepreneur_profile_id)`;
- `survey_answers (survey_response_id, client_id|entrepreneur_profile_id)` → the matching unique on
  `survey_responses`.
A response cannot then reference a different subject than its assignment, nor an answer than its
response. Add explicit rejection tests.

**One response per assignment (UNIQUE).** `UNIQUE (survey_assignment_id)` on `survey_responses` —
without it, two rapid or concurrent submits could create multiple completed responses for one
assignment. The recorder wraps the insert in a transaction; the second submit hits the unique and is
rejected (→ 409 / "already submitted", not a duplicate row). Add **duplicate + concurrent (race)
submit tests**.

**Answer uniqueness — two partial indexes (NULLs don't dedupe).** A single unique over
`(response, question, anchor_ref, answer_key)` does **not** stop duplicate *flat* answers: Postgres
treats NULLs as distinct, so multiple `(…, NULL, NULL)` rows all pass. Use two partial uniques:
```sql
-- flat (non-anchored) questions: at most one answer per (response, question)
CREATE UNIQUE INDEX survey_answers_flat_unique
  ON survey_answers (survey_response_id, survey_question_id)
  WHERE anchor_ref IS NULL AND answer_key IS NULL;
-- anchored deliverable_rating: one row per (response, question, deliverable, metric).
-- Index on EXTRACTED fields, not raw anchor_ref::text, so extra/reordered JSON keys can't bypass it.
CREATE UNIQUE INDEX survey_answers_anchored_unique
  ON survey_answers (survey_response_id, survey_question_id,
                     (anchor_ref->>'source_type'), (anchor_ref->>'source_id'), answer_key)
  WHERE anchor_ref IS NOT NULL AND answer_key IS NOT NULL;
```
Test both paths: a duplicate flat answer **and** a duplicate anchored metric are each rejected. The
recorder **canonicalises** `anchor_ref` to exactly `{source_type, source_id}` (extra keys rejected, not
silently kept), so a duplicate metric resubmitted with an extra or reordered JSON key still collides on
the extracted-field index rather than slipping through — add that test.

**Answer shape CHECK (no invalid or malformed hybrids).** The two partial uniques key off
`(anchor_ref, answer_key)` being *both* null (flat) or *both* set (anchored) — but a plain null/set
CHECK rejects neither the in-between shapes (`anchor_ref` null + `answer_key` set, or vice-versa, or an
**unsupported** `answer_key`) **nor a malformed anchor** like `{}` or `{"source_type":""}`: those are
non-null JSON, so they pass `anchor_ref IS NOT NULL`, yet their extracted `source_type` / `source_id`
are NULL/empty — which would let duplicate metrics **bypass** the extracted-field unique index. The
CHECK must validate the anchor's *contents*, not just its presence:
```sql
CHECK (
  (anchor_ref IS NULL AND answer_key IS NULL)                                  -- flat
  OR (                                                                          -- anchored
    answer_key IN ('received','accessible','met_objective')
    AND anchor_ref->>'source_type' IN ('report','document','plan_assessment')  -- present + valid type
    AND coalesce(anchor_ref->>'source_id','') <> ''                            -- present + non-empty
  )
)
```
Test each rejected shape, including `anchor_ref = '{}'` and an empty `source_id`.

**Answer ↔ survey & anchor binding (no foreign questions / invented anchors).** `survey_question_id` is
a plain FK to the *global* `survey_questions` and `anchor_ref` is free JSON — so a tampered submit could
answer a question from **another** survey, or invent a deliverable that was never snapshotted, polluting
results and learning evidence. The parent-match FKs bind *subject scope* only, not *which survey*. Close
both:
- **Question belongs to the assignment's survey (DB).** Denormalise `survey_id` onto `survey_responses`
  and `survey_answers`; add `UNIQUE (id, survey_id)` on `survey_questions`, `survey_assignments`,
  `survey_responses`; then composite-FK the chain so `survey_answers.survey_id` is pinned to the
  assignment's survey **and** the question lives in it:
  `survey_responses (survey_assignment_id, survey_id)` → `survey_assignments (id, survey_id)`;
  `survey_answers (survey_response_id, survey_id)` → `survey_responses (id, survey_id)`;
  `survey_answers (survey_question_id, survey_id)` → `survey_questions (id, survey_id)`.
  A question from a different survey then violates the FK.
- **Anchor exists in the snapshot AND matches the question (recorder).** No clean FK reaches into the
  per-assignment `deliverable_snapshot` JSON, so the recorder validates every answer's anchoring,
  symmetrically:
  - an **anchored** row (`anchor_ref` + `answer_key` set) must target a `deliverable_rating` question,
    and its `anchor_ref = {source_type, source_id}` must exist in `assignment.deliverable_snapshot`;
  - a **flat** row (no anchor) must target a **non-**`deliverable_rating` question;
  - when the question's `anchor.deliverable_scope` is set (e.g. `report`), the deliverable's
    `source_type` must match it — a report-scoped question rejects a `document` anchor.
  Unknown anchors, type mismatches, and anchored-answer-against-a-flat/rating/NPS-question are all
  rejected.
Tests: a cross-survey `survey_question_id` is rejected (FK); an `anchor_ref` not in the snapshot, an
anchored answer pointed at a flat/rating/NPS question, and a `source_type` ≠ `deliverable_scope` are
each rejected (recorder).

**RLS — action-specific (read ≠ write), on the `documents_scope` helper shape.** Every scoped table is
first `ALTER TABLE … ENABLE ROW LEVEL SECURITY;` **and** `ALTER TABLE … FORCE ROW LEVEL SECURITY;`
(repo convention — without `FORCE`, the table owner the app connects as would bypass every policy).
A single policy with
identical USING / WITH CHECK is **too broad for writes**: a scoped advisor (client access, or an
assigned entrepreneur's advisor) would pass the WITH CHECK and could *insert* a response/answer — but
only the **subject** may submit. So split SELECT from INSERT, using the repo's real helpers
(`fsa_current_role()` — which carries the user_type via `RequestContext::resolveRole`/`fsaRole()` —
`fsa_current_client_ids()`, `fsa_current_user_id()`).

*Reads — broad (admin, scoped advisor, and the subject), all three tables* — the `documents_scope`
dual-branch shape:
```sql
CREATE POLICY survey_responses_select ON survey_responses FOR SELECT
  USING (
    fsa_current_role() IN ('super_admin','system')
    OR (client_id IS NOT NULL AND client_id::text = ANY (fsa_current_client_ids()))
    OR EXISTS (SELECT 1 FROM entrepreneur_profiles ep
               WHERE ep.id = survey_responses.entrepreneur_profile_id
                 AND (ep.assigned_advisor_id::text = fsa_current_user_id()
                      OR ep.user_id::text = fsa_current_user_id()))
  );
```

*Writes to `survey_responses` / `survey_answers` — subject only* (advisors excluded; the entrepreneur
branch uses **`ep.user_id` only**, never `assigned_advisor_id`; the client branch requires a *client*
role, not advisor). Because responses are **attributed** (§15.4–5), a non-system insert must also stamp
**its own** user — `submitted_by_user_id` must equal `fsa_current_user_id()`, so one team member cannot
spoof a response as another:
```sql
CREATE POLICY survey_responses_insert ON survey_responses FOR INSERT
  WITH CHECK (
    fsa_current_role() = 'system'
    OR (
      submitted_by_user_id::text = fsa_current_user_id()   -- no spoofed submitter
      AND EXISTS (SELECT 1 FROM survey_assignments a        -- assignment still open
                  WHERE a.id = survey_responses.survey_assignment_id
                    AND a.status IN ('pending','in_progress'))
      AND (
        (client_id IS NOT NULL
         AND fsa_current_role() IN ('client_primary','client_team')
         AND client_id::text = ANY (fsa_current_client_ids()))
        OR (entrepreneur_profile_id IS NOT NULL
            AND EXISTS (SELECT 1 FROM entrepreneur_profiles ep
                        WHERE ep.id = survey_responses.entrepreneur_profile_id
                          AND ep.user_id::text = fsa_current_user_id()))
      )
    )
  );
```
The recorder **always sets `submitted_by_user_id` from the authenticated user** (`auth()->id()`) and
ignores any submitter in the request payload; the DB guard is the backstop.

`survey_answers` INSERT is tied to the **parent response's submitter AND the assignment still being
open** — not merely the client scope — so neither another `client_team` user nor the *original*
submitter (after completion) can append late/optional answers:
```sql
CREATE POLICY survey_answers_insert ON survey_answers FOR INSERT
  WITH CHECK (
    fsa_current_role() = 'system'
    OR EXISTS (
      SELECT 1 FROM survey_responses r
      JOIN survey_assignments a ON a.id = r.survey_assignment_id
      WHERE r.id = survey_answers.survey_response_id
        AND r.submitted_by_user_id::text = fsa_current_user_id()
        AND a.status IN ('pending','in_progress')        -- assignment still open
    )
  );
```
(The answer's subject scope is already pinned to that response by the parent-match composite FKs.)
Because the recorder flips the assignment to `completed` **last** (§8), answers can only be written
while it is still open; once completed, no further append passes this policy. Responses/answers are
**append-only** — no subject UPDATE/DELETE policy. Tests: a spoofed `submitted_by_user_id` is rejected;
a second `client_team` user cannot append answers to another user's response; and an answer appended
**after** the assignment is completed is rejected.

*Writes to `survey_assignments` — activator only.* Per the RBAC table, activation/cancellation is
**`super_admin` (+ `system`)**; the subject never writes the assignment:
```sql
CREATE POLICY survey_assignments_select ON survey_assignments FOR SELECT
  USING ( /* broad dual-branch, as survey_responses_select */ );
CREATE POLICY survey_assignments_write ON survey_assignments FOR ALL
  USING      (fsa_current_role() IN ('super_admin','system'))
  WITH CHECK (fsa_current_role() IN ('super_admin','system'));
```
The subject's request runs in a *client/entrepreneur* role, so the recorder flips `status`/`completed_at`
in a **system context — but it must restore it.** Use the save/restore `withSystemContext(callable)`
helper that already exists on `ProjectSettings` (it captures every `fsa.*` via `current_setting`, sets
system, runs the callback in `try/finally`, then restores) — **promote it to `RequestContext` for
reuse**, and wrap *only* the completion update. A bare `apply('system')` (as in
`WellbeingCheckinService`) uses session-level `set_config(…, false)` with **no restore**, leaving the
rest of the request running as `system` — do not copy that.
If advisors should activate (open decision §15.6), add an advisor branch here and grant the permission.
`fsa_current_user_id()` is populated on every authenticated request (it backs the documents /
message-thread policies). Template tables get no RLS. Tests must assert: the subject **can** insert; a
scoped **advisor cannot** insert a response/answer (WITH CHECK denial) but **can** SELECT;
cross-subject denial; and assignment writes are super_admin/system only.

**One active assignment per subject (partial unique).** §15(3) requires a single active assignment per
subject per survey — enforce it, don't just assert it:
```sql
CREATE UNIQUE INDEX surveys_one_active_client ON survey_assignments (survey_id, client_id)
  WHERE status IN ('pending','in_progress') AND client_id IS NOT NULL;
CREATE UNIQUE INDEX surveys_one_active_entrepreneur ON survey_assignments (survey_id, entrepreneur_profile_id)
  WHERE status IN ('pending','in_progress') AND entrepreneur_profile_id IS NOT NULL;
```
Add duplicate / concurrent-activation tests.

### 4.7 Deliverable snapshot — shape & eligibility

`deliverable_snapshot` is **polymorphic** (a deliverable is not always a `Report`):
`[{ source_type, source_id, label, delivered_at, reviewed_at, visible_to_subject }]`,
`source_type ∈ { report, document, plan_assessment }`. Eligibility queries at activation:
- **report** — `reports` matching the subject (`client_id` for a client; `entrepreneur_profile_id` +
  `ReportType::EntrepreneurAssessment` for an entrepreneur) AND `review_status = 'reviewed'` AND
  `reviewed_at IS NOT NULL` (`Report::reviewed()` — only reviewed reports reach the subject).
- **document** — `documents` in the subject's scope that were released to them, `scanner_result = 'clean'`.
- **plan_assessment** (entrepreneur) — finalised `plan_assessments` (`finalised_at IS NOT NULL`).

**Decision (v1): anchor only released / visible deliverables.** Every eligibility query above requires
the artifact to have been *released* to the subject, so `visible_to_subject` is `true` for every
anchored row in v1 (it records that release assertion at snapshot time). The respondent's
`received` / `accessible` answers then measure their **experience against our record of release** — a
"no" is a **respondent-reported discrepancy** (the signal that feeds §9), which we surface rather than
pre-flag. We do **not** anchor produced-but-unreleased artifacts in v1 — asking a subject about a report
we never sent them is confusing, and capturing internal "made it but didn't release it" gaps is a
separate concern, deferred (§14). The `visible_to_subject` field is retained for that future case. The
survey never invents a deliverable (Evidence-based, §3).

### Models (mirror existing conventions: `HasUuids`, `$guarded = []`, typed `$casts`)
`Survey`, `SurveyQuestion`, `SurveyAssignment`, `SurveyResponse`, `SurveyAnswer` —
each `final`. `SurveyAssignment` / `SurveyResponse` carry **both** `client()` and
`entrepreneurProfile()` nullable BelongsTo relations (exactly like `Report` / `Document`), plus a
`subject()` accessor returning whichever is set.

---

## 5. Enums

- **`SurveyStatus`**: `draft`, `published`, `archived`.
- **`SurveyAssignmentStatus`**: `pending`, `in_progress`, `completed`, `cancelled`, `expired`.
- **`SurveyQuestionType`** (deliberately a *survey* vocabulary, not the intake
  `QuestionnaireQuestionType`):
  - `rating` — 1–5 scale (primary satisfaction unit, `numeric_value`)
  - `nps` — 0–10 recommend score
  - `yes_no` — boolean (`numeric_value` 1/0; used for "received?" / "could you access it?")
  - `single_select`, `multi_select`
  - `long_text` — free comment (never logged)
  - `deliverable_rating` — special anchored type expanded per snapshotted deliverable into three
    answers, distinguished by `survey_answers.answer_key`: `received` (yes/no), `accessible` (yes/no),
    `met_objective` (1–5 rating).

---

## 6. RBAC (permission convention `domain.action`)

Add to `App\Enums\Permission`:
- `surveys.manage` — author templates, activate per subject, cancel, view all results. → **super_admin**.
- `surveys.view` — read survey results for *their assigned clients **and** entrepreneurs*.
  → **advisor**, **junior_advisor**.

Subject response is **not** a global permission — it is governed by `SurveyAssignmentPolicy`
(subject owns the active assignment: `client_id` belongs to the user's client, **or**
`entrepreneur_profile.user_id === auth id`) plus, for clients, RLS — exactly as wellbeing check-ins are
owned by the submitting user. Update `PermissionSeeder` + `RoleSeeder`; `SyncRbacAfterMigrations`
already re-seeds roles on `MigrationsEnded` (method `up`), so deploy/CI auto-grant the new permissions.

| Role | surveys.manage | surveys.view | respond |
|---|:---:|:---:|:---:|
| super_admin | ✅ | ✅ | — |
| advisor | — | ✅ (own clients + entrepreneurs) | — |
| junior_advisor | — | ✅ (own clients + entrepreneurs) | — |
| client_primary / client_team | — | — | ✅ (own active assignment) |
| entrepreneur | — | — | ✅ (own active assignment) |
| all others | — | — | — |

---

## 7. Routes

All inside the existing `auth` + `verified` + `mfa` (+ `terms` where applicable) stack.

**Admin — template authoring & results** (`admin` prefix, `permission:surveys.manage`):
```
GET    admin/surveys                       surveys.index      (library)
POST   admin/surveys                        surveys.store
GET    admin/surveys/{survey}/edit          surveys.edit       (builder)
PUT    admin/surveys/{survey}               surveys.update
POST   admin/surveys/{survey}/publish       surveys.publish
POST   admin/surveys/{survey}/archive       surveys.archive
GET    admin/surveys/{survey}/results       surveys.results    (aggregate)
```

**Activation — on the subject** (`permission:surveys.manage`):
```
POST   clients/{client}/survey-assignments                    survey-assignments.store          (activate, client)
POST   entrepreneurs/{entrepreneurProfile}/survey-assignments survey-assignments.entrepreneur.store (activate, entrepreneur)
PATCH  survey-assignments/{assignment}/cancel                 survey-assignments.cancel
```

**Advisor — per-subject results** (`permission:surveys.view`, scoped to assigned subjects):
```
GET    advisor/clients/{client}/surveys                    advisor.clients.surveys.index
GET    advisor/entrepreneurs/{entrepreneurProfile}/surveys advisor.entrepreneurs.surveys.index
```

**Client portal — respond** (`portal` prefix, `EnforceClientScope`, `SurveyAssignmentPolicy`):
```
GET    portal/surveys                        portal.surveys.index   (active assignments)
GET    portal/surveys/{assignment}           portal.surveys.show
POST   portal/surveys/{assignment}           portal.surveys.submit
```

**Entrepreneur portal — respond** (`portal` prefix, entrepreneur type-guard, `SurveyAssignmentPolicy`):
```
GET    portal/entrepreneur/surveys                 portal.entrepreneur.surveys.index
GET    portal/entrepreneur/surveys/{assignment}    portal.entrepreneur.surveys.show
POST   portal/entrepreneur/surveys/{assignment}    portal.entrepreneur.surveys.submit
```
`EnforceClientScope` **still runs here** — it is appended globally to the `web` (and `api`) group in
`bootstrap/app.php`, and it is what populates `fsa_current_user_id()` that the entrepreneur RLS branch
(§4.6) depends on. Entrepreneurs simply resolve to **no `client_ids`**, so the policy's client branch
matches nothing and the entrepreneur branch does the scoping — they are *not client-id-scoped*, but the
middleware must not be skipped. A shared controller resolves the subject; the policy enforces ownership
for both portals.

Run `php artisan wayfinder:generate --with-form` after route changes; commit the regenerated typed
routes under `resources/js/routes` + `resources/js/actions`.

---

## 8. Services (mirror existing patterns)

- **`SurveyLibrary`** — template CRUD + lifecycle, audited. **Published surveys/questions are
  immutable**, mirroring `QuestionnaireController::update`
  (`abort_if($q->isPublished(), 422, 'Published … immutable. Create a new draft version.')`):
  - `surveys.update` mutates **drafts only** — a `PUT` on a published survey returns 422.
  - editing a published survey **clones it to a new draft** (`version + 1`, `supersedes_survey_id` set,
    questions copied — like `QuestionnaireController::cloneStructure`), which is then published.
  - `publish` aborts if already published; `archive` is a status change that never touches questions.
  Because only **published** surveys can be activated (§3) and they can never change, every active
  assignment points at an immutable survey — results cannot drift. (No per-assignment question snapshot
  is needed; immutability + the assignment's `survey_id` reference already freeze it.)
- **`SurveyActivationService`** — builds the `deliverable_snapshot` from the **subject's** reviewed
  deliverables — client `Report`s + access records, or for an entrepreneur the
  `EntrepreneurAssessment` reports / finalised plan assessments / `entrepreneur_profile_id` documents —
  creates the `SurveyAssignment` against whichever subject FK applies, fires the subject notification,
  audits `survey.activated`. **Evidence-based**: only real, accessible deliverables enter the snapshot.
- **`SurveyResponseRecorder`** — in one transaction: **`SELECT … FOR UPDATE` locks the assignment row**
  first (serialising against a concurrent cancel), aborts unless `status IN ('pending','in_progress')`,
  then persists `SurveyResponse` + `SurveyAnswer` (setting `submitted_by_user_id` from `auth()->id()`,
  canonicalising `anchor_ref`), computes `overall_score`/`nps_score`, audits `survey.submitted` with
  **no free text** (store `comment_present: bool`), calls the feedback evaluator, and **last** flips the
  assignment to `completed` via a **conditional** update
  (`UPDATE … SET status='completed', completed_at=now() WHERE id=? AND status IN ('pending','in_progress')`)
  inside the restoring `withSystemContext` (§4.6). The cancel action takes the **same row lock** and a
  conditional update, so submit and cancel cannot interleave — whichever commits first wins; the loser
  sees the changed status and aborts. Mirrors `QuestionnaireResponseRecorder` +
  `WellbeingCheckinService.record()`.
- **`SurveyResultAggregator`** — distributions per question and per deliverable (received %,
  accessible %, met-objective mean), raw counts surfaced alongside averages (honesty).
- **`SurveyFeedbackEvaluator`** — see §9.

---

## 9. Governed learning loop (AI Integrity, §3)

Survey feedback feeds a **new registered learning layer** — following the established precedent (the
NPO module registered layers 34–37). Add to `LayerCadenceRegistry`:
`LAYER_ENGAGEMENT_EXPERIENCE_FEEDBACK = 38` (`'Engagement experience feedback'`, cadence weekly,
window 90, `governed_candidates_only => true`). It does **not** reuse an existing layer — service-delivery
feedback is a distinct signal from advisor/AI-quality feedback (layer 11) or wellbeing (layer 25), and
`learning_updates.layer_id` is required, so a registered ID is mandatory.

On submission, `SurveyFeedbackEvaluator` inspects answers against thresholds and writes one
`learning_updates` row per trigger (status defaults to `detected` = enqueued for human approval, never
auto-applied — spec §3, CLAUDE.md "No silent learning updates"). Triggers:
- any deliverable marked **not received** or **not accessible**,
- `met-objective` rating below threshold,
- low overall score / NPS detractor.

`learning_updates` payload contract (matches `2026_05_20_120000_create_learning_updates_tables`):

| Column | Value |
|---|---|
| `layer_id` | `38` |
| `source` | `{"feature":"survey","survey_id","assignment_id","subject_type":"client"\|"entrepreneur","subject_id"}` |
| `summary` | human-readable, e.g. *"2 of 4 deliverables flagged not-received / below-expectation for this engagement"* |
| `proposed_change` | `null` in v1 — these are review flags, not auto-changes (the Phase-3 approval UI decides) |
| `impact_scope` | `{"scope":"single_subject","subject_type","subject_id"}` — never cross-client |
| `clients_affected` | `1` for a client subject, `0` for an entrepreneur (the column counts clients; the real subject lives in `impact_scope`) |
| `magnitude` | `low`\|`medium`\|`high`, derived from how many deliverables failed / score severity |
| `confidence` | `null` (first-party stated feedback; confidence would describe an inference, not the raw answer) |
| `evidence` | **structured only — no PII**: `{"answers":[{"question_id","anchor_ref","answer_key","numeric_value"}],"thresholds":{…},"comment_present":bool}`. `answer_key` identifies which deliverable metric (received/accessible/met_objective) failed. Free-text comments are never copied into evidence. |
| `status` | `detected` |

Every candidate is audited. Discrepancies (subject says "I never got the report we recorded as
delivered") are treated like an accuracy discrepancy: surfaced to admin + advisor immediately, never
suppressed.

---

## 10. Notifications & lifecycle hooks

- **Activation → subject**: channel-preference notification to the client *or* entrepreneur (mirror
  `WellbeingCheckinPromptNotification`) — "Your advisor would like your feedback on the work delivered."
- **Submission → advisor**: notify the assigned advisor; **flag low scores / not-received answers**
  prominently on the dashboard verification-style panel.
- **Lifecycle (fast-follow, not v1 blocker)**: offer to activate a survey at **offboarding**
  (`OffboardingRecord`) — natural "did we deliver?" moment. v1 ships manual activation only; the
  offboarding suggestion is a follow-up WO.

---

## 11. Frontend (Inertia + React 19 + shadcn/ui)

Reuse the shared styling primitives (`page-header`, `section-card`, `empty-state`,
`AppLayout` padded container). No new design language.

- `resources/js/pages/admin/surveys/Index.tsx` — template library (status chips, create).
- `resources/js/pages/admin/surveys/Edit.tsx` — question builder (ordered, typed, anchored).
- `resources/js/pages/admin/surveys/Results.tsx` — aggregate results.
- `resources/js/pages/portal/surveys/Index.tsx` + `Show.tsx` — **client** active surveys + respond.
- `resources/js/pages/portal/entrepreneur/surveys/Index.tsx` + `Show.tsx` — **entrepreneur** active
  surveys + respond (same components/payload shape, entrepreneur portal shell). Both `Show` pages
  **lead with the mandatory honesty-assurance banner** (below) and reaffirm it above the submit button.
  The banner is hard-coded in the portal layer (not admin-editable) so it can never be removed or
  weakened when an admin authors a template.

> **Honesty-assurance copy (mandatory, baseline wording — refine for brand voice, do not weaken):**
> *"Your honest feedback is crucial — it's how we keep improving both the service we provide and this
> portal. Please answer candidly: there are no wrong answers, and your responses will never be used,
> in any shape or form, to be held against you. Tell us what you actually experienced."*
- Client **and** entrepreneur detail pages: **Activate survey** control + per-subject results panel
  (advisor).
- **Sidebar** (`app-sidebar.tsx`, `mainNavItemsFor`): add `Surveys` (admin, `surveys.manage`) —
  `ClipboardList`/`MessagesSquare` icon; add a portal `Feedback` item under **both** `clientNavItems`
  and `entrepreneurNavItems`, shown when an active assignment exists.

---

## 12. Testing (gates must stay green)

Each WO ships with PHPUnit feature + unit tests; `FakeAiClient` + `NoopScanner` bound. Required:
- **Lifecycle**: draft → publish → archive; only published templates can be activated.
- **Activation snapshot**: snapshot contains only the subject's reviewed/accessible deliverables
  (cover both a client subject and an entrepreneur subject).
- **Isolation — both subject paths**: client A cannot see/submit client B's assignment (RLS, assert
  403/404 + zero rows); entrepreneur X cannot see/submit entrepreneur Y's assignment (policy); a client
  cannot reach an entrepreneur assignment and vice-versa.
- **CHECK constraint**: an assignment with neither or both subject FKs is rejected.
- **Write authority (RLS)**: the subject **can** insert their response/answers; a scoped **advisor**
  (client access or assigned-entrepreneur advisor) **can SELECT** but **cannot INSERT** a response/answer
  (WITH CHECK denial); assignment writes are super_admin/system only.
- **Immutability**: a `PUT` to a *published* survey is rejected (422); editing clones a new draft
  version (`version + 1`, `supersedes_survey_id` set); an active assignment's rendered questions are
  unchanged after a newer version is published.
- **Answer uniqueness**: a duplicate **flat** answer and a duplicate **anchored** metric are each
  rejected (the two partial indexes, §4.6).
- **Answer shape**: invalid hybrids — `anchor_ref` null + `answer_key` set, `anchor_ref` set +
  `answer_key` null, and an unsupported `answer_key` — are each rejected by the CHECK (§4.6).
- **Answer ↔ survey binding**: an answer whose `survey_question_id` belongs to a *different* survey is
  rejected (composite FK); an anchored answer whose `anchor_ref` is **not** in the assignment's
  `deliverable_snapshot` is rejected by the recorder.
- **Anchor symmetry**: an anchored answer pointed at a flat/rating/NPS question is rejected; a
  `source_type` that doesn't match the question's `anchor.deliverable_scope` is rejected; a duplicate
  metric resubmitted with an extra/reordered JSON key still collides (extracted-field index +
  canonicalisation).
- **No spoofed submitter**: a non-system insert with `submitted_by_user_id` ≠ the authenticated user is
  rejected by the INSERT policy; the recorder ignores any payload submitter.
- **Answer attribution**: a second authorised `client_team` user of the same client **cannot** append
  answers to a response another user submitted (answer INSERT ties to the parent response's submitter).
- **Closed assignment**: a submit against a `cancelled` / `expired` assignment (no response yet) is
  rejected by the INSERT policy's active-status EXISTS check; an answer appended **after** the
  assignment is completed is also rejected.
- **Submit/cancel race**: a concurrent cancel + submit cannot leave a cancelled assignment with a
  response — the `FOR UPDATE` lock + conditional updates serialise them (whichever commits first wins).
- **System context restored**: after the recorder completes the assignment, the request's `fsa.*`
  session context is back to the subject's (not left as `system`).
- **Malformed anchor**: `anchor_ref = '{}'`, `{"source_type":""}`, or an empty `source_id` are rejected
  by the answer-shape CHECK (so they can't bypass the extracted-field unique index).
- **One response per assignment**: a second submit (and a concurrent/race submit) for the same
  assignment is rejected by `UNIQUE (survey_assignment_id)` → 409, not a duplicate completed row.
- **deliverable_rating expansion**: one anchored question produces the right `received` / `accessible` /
  `met_objective` answer rows (keyed by `answer_key`); a duplicated metric in one response is rejected;
  aggregation and learning evidence read `answer_key` correctly.
- **Permission matrix**: neither clients nor entrepreneurs can reach `admin/surveys*`; advisor sees
  only assigned subjects' results; `surveys.view` ≠ `surveys.manage`.
- **Honesty / learning**: a low-score / "not received" submission enqueues a governed learning
  candidate and is **not** suppressed; audit row written with no free text.
- **Audit**: every create/publish/archive/activate/submit/cancel writes an `audit_events` row.
- **No regressions**: existing questionnaire/wellbeing/report suites stay green.
- Static gates: `pint --test`, `tsc --noEmit`, ESLint, Prettier, `composer audit`, `npm audit`.

---

## 13. Work Orders (one WO per branch, one PR each)

| WO | Title | Depends on | Deliverable |
|---|---|---|---|
| **S1** | Schema, models, enums, RLS, RBAC | — | 5 migrations (subject = nullable `client_id` **+** `entrepreneur_profile_id`, one-of CHECK on all 3 scoped tables, parent-match composite FKs, **answer-shape CHECK**, **answer↔survey composite-FK binding via denormalised `survey_id`**, `UNIQUE(survey_assignment_id)`, two partial answer-uniques, partial-unique active-assignment indexes, `surveys.version`/`supersedes_survey_id` — §4.6/§4.1); **`ENABLE` + `FORCE` RLS** on all 3 scoped tables with **action-specific** policies (broad SELECT; subject-only INSERT for responses/answers; super_admin/system writes for assignments); `Survey*` models with dual `subject()` relations + `isPublished()`; 3 enums; `surveys.manage`/`surveys.view` perms + seeders; tests for read vs write branches, advisor-insert denial, cross-subject denial, CHECK, answer-shape, cross-survey question FK, parent-match, all uniques, permission grant. |
| **S2** | Admin template authoring | S1 | `SurveyLibrary` service, admin controller + routes, Index/Edit pages, draft/publish/archive, **published-immutability guard** (422 on editing published; clone-to-new-draft like `QuestionnaireController`), audit, tests. |
| **S3** | Per-subject activation + deliverable snapshot | S2 | `SurveyActivationService` (client **and** entrepreneur snapshot sources), activation routes on client **and** entrepreneur detail, subject notification, audit, tests for both subject types. |
| **S4** | Subject portal response flow | S3 | Client **and** entrepreneur portal routes/pages (`EnforceClientScope` runs on both — §7), shared `SurveyResponseRecorder` (transactional; **`FOR UPDATE` lock** on the assignment + conditional active-status completion inside a restoring `withSystemContext`; one-response-per-assignment; `submitted_by_user_id` from `auth()->id()` only; `deliverable_rating` → `answer_key` rows; `anchor_ref` canonicalised + validated against snapshot, question-type and `deliverable_scope`), `SurveyAssignmentPolicy` (both ownership paths), scoped submit + validation + audit (no PII); tests for both subjects + duplicate/race submit + **submit/cancel race** + spoofed-submitter denial + cross-user answer-append denial + post-completion answer-append denial + **system-context-restored** + anchor-symmetry + one-shared-response-per-`client_team` (§15.5). |
| **S5** | Results + advisor visibility | S4 | `SurveyResultAggregator`, admin aggregate page, per-subject advisor view (clients + entrepreneurs), dashboard flag panel, tests. |
| **S6** | Governed learning feedback loop | S4 | Register layer 38 in `LayerCadenceRegistry`; `SurveyFeedbackEvaluator` writes governed `learning_updates` rows (layer_id 38, structured no-PII evidence, status `detected`, contract §9); thresholds; never-silent; audited; tests assert payload shape + no free text in evidence. |
| **S7** | Notifications & offboarding hook (fast-follow) | S3, S5 | Activation prompt + submission alert + low-score flag; optional offboarding activation suggestion; tests. |

Branch naming `wo/S1-survey-schema`, etc. PR title leads with the WO ID.

---

## 14. Out of scope (v1) / explicit deferrals

- File-attach answers (would require `SecureFileWriter` virus-scan routing — defer).
- Anonymous responses (v1 is attributed: response is owned by a known subject user — client or
  entrepreneur — and audited).
- Scheduled / recurring auto-activation (v1 is manual per-subject).
- **NPO board, broker, coach** portal surveys (v1 covers **client + entrepreneur** portals only).
- Cross-client benchmarking of survey results (spec puts anonymous benchmarking in Phase 2+).
- Phase-3 learning-update **approval UI** (S6 only enqueues governed candidates).
- **Internal delivery-gap capture** (anchoring produced-but-unreleased artifacts so "we made it but
  never sent it" is measurable). v1 anchors only released deliverables (§4.7); this is a later concern.
- **Per-team-user responses** for multi-user clients — v1 records one shared response per assignment
  (§15.5).

---

## 15. Decisions still to confirm (low-risk defaults chosen)

These are defaulted so implementation is not blocked; flag in the S1 PR if the owner wants otherwise:

1. **NPS question** — included as an optional question type, off by default per template. *(default: optional)*
2. **Response window** — `due_at` optional; no auto-expiry job in v1 (status `expired` reserved for later). *(default: no hard expiry)*
3. **Re-activation** — admin may activate a *new* assignment later; **one active assignment per
   *subject* per survey** at a time, enforced by the partial unique indexes in §4.6 (not just asserted).
   *(default: re-activation allowed, single active per subject)*
4. **Anonymity** — **CONFIRMED: attributed, not anonymous** (admin + advisor see which client user
   responded), consistent with audit + RLS. Because it is attributed, the mandatory honesty-assurance
   banner (§2, §11) is shipped to reassure the client that candid feedback will never be held against
   them. The assurance copy is hard-coded in the portal layer and cannot be removed by template authors.
5. **Multi-user clients (`client_team`) — one shared response per assignment.** A client's audience can
   include several SME users (`client_primary` + `client_team`). v1 treats the response as the
   **organisation's single collective feedback**: the assignment yields **one** `survey_responses` row
   (enforced by `UNIQUE (survey_assignment_id)`, §4.6), completed by whichever authorised client user
   submits first, recorded in `submitted_by_user_id`; the survey then shows as completed for the whole
   client. The alternative — a separate response per team user — is a deliberate later change (drop the
   unique, key responses on `(assignment, user)`). *(default: one shared response per assignment)*
6. **Who activates / cancels assignments?** The RBAC table (§6) scopes `surveys.manage` to
   **super_admin**, and the `survey_assignments` write policy (§4.6) matches that
   (super_admin/system only). If the owner wants **advisors to activate for their own assigned clients /
   entrepreneurs**, that is a deliberate change: grant `surveys.manage` (or a narrower `surveys.activate`)
   to `advisor`, **and** add an advisor branch to the assignment write policy (client-scope advisors +
   `assigned_advisor_id` for entrepreneurs). The RLS and the RBAC must move together. *(default:
   super_admin only — confirm in the S1 PR)*

---

*Aligned with Spec V2.4 §3 (AI Integrity), §4 (Security baseline), §9 (verification honesty), and the
NPO Module Rules (this feature is scoped to the client + entrepreneur portals and does not alter NPO
behaviour).*
