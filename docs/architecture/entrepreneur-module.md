# Entrepreneur module

Phase 3 builds the entrepreneur track from readiness through advisory conversion.

## WO-82 - Readiness Assessment

Entrepreneur-scoped tables hang off `entrepreneur_profiles`, not `clients`.
WO-82 retrofits `entrepreneur_profiles` with PostgreSQL RLS:

- super admins and system jobs can see all rows
- assigned advisors can see their assigned profiles
- entrepreneur users can see only their own profile

The same scope is used by `readiness_assessments`. `fsa_current_user_id()` is
the SQL helper used by these policies, matching the user id already pushed into
the request context.

`readiness_assessments` stores the raw readiness response payload, the computed
score, the Ready / Develop First / Not Yet outcome, personal-readiness barriers,
the assessing user, and assessment time.

The readiness service scores numeric answers on a 0-100 scale:

- 78+ with no personal barriers -> Ready
- 45-77 or any personal barrier -> Develop First
- below 45 -> Not Yet

Develop First outcomes with personal barriers write a raw `coaching_signals`
row linked to the entrepreneur profile. The signal explicitly records
`raw_observation_only = true` and `auto_referral = false`; it is not a coach
referral and does not notify a coach.

WO-82 also seeds the published `entrepreneur_readiness` questionnaire with 16
questions covering concept clarity, customer need, evidence, industry
experience, personal capacity, resilience, financial runway, support, and launch
concerns.

## WO-83 - Idea Validation

`idea_validations` captures the founder's problem, target customer, solution,
value proposition, demand signal, and revenue model before the plan builder
opens.

`App\Services\Entrepreneurs\IdeaValidationService` sends the concept payload to
the AI contract with an integrity prompt and aggregate past-plan pattern context.
The stored `ai_evaluation` includes the model, prompt hash, uncertainty,
attributions, and a `past_plan_pattern` source reference. When no comparable
finalised plans exist, the service records that absence explicitly instead of
inventing a benchmark.

Viability alerts are informational only. Weak problem, customer, solution, value
proposition, demand, or revenue-model evidence creates non-blocking alerts so an
advisor can discuss risk without pretending the system has rejected the idea.

The plan builder is locked until an advisor passes the gate with a note. Passing
the gate timestamps the validation, records the advisor, audits the decision,
and moves the entrepreneur to `building_phase1`.

## WO-84 - Five-Phase Plan Builder

WO-84 reuses the shared `business_plans`, `plan_phases`, and `plan_sections`
engine introduced for DD plan building, adding the entrepreneur adapter
`App\Services\Entrepreneurs\PlanBuilder`.

The entrepreneur builder starts only after a passed idea-validation advisor gate.
It creates an entrepreneur-owned `business_plans` row with five ordered phases:

1. Foundation
2. Market
3. Strategy
4. Legal & Operations
5. Financial

The phase dependency graph is stored in `plan_phases.depends_on`:

- Market depends on Foundation
- Strategy depends on Foundation and Market
- Legal & Operations depends on Foundation
- Financial depends on Foundation and Strategy

Jumping ahead is allowed so founders can draft thoughts when they have them, but
the section metadata records a `dependency_warning` listing incomplete
dependencies. Advisors can therefore see when a later section is premature
without losing the founder's work.

WO-84 also adds `attached_document_ids` and `predictive_score` JSON columns to
`plan_sections` for WO-85/86.

## WO-85 - AI Guidance, Predictive Score, and NZ Resources

`nz_resources` is an admin-managed catalogue of New Zealand resources keyed by
industry, business type, and gap tags. WO-85 seeds a small starting set from
business.govt.nz, Retail NZ, and Inland Revenue.

`App\Services\Entrepreneurs\Guidance` generates section-specific guidance using
the AI contract plus:

- the current section draft
- detected gap tags
- NZ resource matches
- aggregate past-plan pattern context

Guidance is persisted into `plan_sections.metadata.ai_guidance`, and the live
predictive score is persisted into `plan_sections.predictive_score`.

The predictive score is deliberately conservative. Thin draft sections are
capped below 60, gap tags reduce the score, and the stored payload includes
`no_flattery = true`. Guidance copy must describe weak sections as not ready
rather than praising them.

## WO-86 - Section-Attached Document Verification

Entrepreneur plan sections can attach `plan_attachment` documents owned by the
same entrepreneur profile. `App\Services\Entrepreneurs\PlanDocuments` verifies
each attachment with the existing AI document verifier and stores the document
ids on `plan_sections.attached_document_ids`.

Document verifications now support `entrepreneur_profile_id` and
`plan_section_id`, and document/document-verification RLS includes the same
profile-scoped advisor/entrepreneur visibility used by the rest of the
entrepreneur module.

Verified attachments raise the criterion score used by assessment services.
Outstanding advisory flags or accuracy discrepancies block scoring through the
existing `DocumentVerificationBlockedException` path until resolved.

## WO-87a - Rating Framework Engine

`rating_frameworks` and `rating_criteria` are global admin-managed reference
tables, not entrepreneur-profile scoped tables. Authorisation is handled by
admin permissions rather than RLS.

WO-87a seeds the 11 spec-defined founding criteria:

1. Type of business
2. Location
3. Means of doing business
4. Discuss the industry
5. What sets the business apart
6. Describe unique success factors
7. Mission and Vision statement
8. Intellectual property
9. Goals and objectives
10. Culture
11. Legal Environment

Seeded weights and descriptors are placeholders (`is_placeholder = true`) and
the framework is explicitly `production_ready = false` until WO-87b records
owner-entered values.

Admin edits create a new framework version rather than mutating the prior
version. Learning-driven suggestions are written to `learning_updates` with
`automatic_application = false`, preserving the no-silent-learning rule.

Grade bands are fixed from the spec:

- Exceptional: 90+
- Strong: 75-89
- Developing: 60-74
- Needs Work: below 60

## WO-87b - Founding Weights and Descriptors

WO-87b records the founding admin-managed values as a new published framework
version. Version 1 remains the WO-87a placeholder seed; version 2 clears
`is_placeholder`, sets criterion weights that total 100, fills descriptors for
all grade bands, and marks `production_ready = true`.

The values are kept in `FoundingRatingFrameworkValuesSeeder` so a fresh
environment can reproduce the owner-confirmed baseline after the placeholder
framework seed. The service path `RatingFrameworkManager::confirmFoundingValues`
validates that all 11 criteria are present, weights total 100, and each grade
band has a descriptor before publishing.

## WO-88 - AI First-Pass Scoring and Advisor Assessment

`plan_assessments` stores assessment rounds for entrepreneur plans. A first-pass
assessment scores all 11 criteria from the current published rating framework,
stores AI scores, document-support context, and the overall grade.

Advisor score adjustments are allowed only with a note. Each adjustment writes a
governed `learning_updates` candidate with `automatic_application = false` so
future calibration remains owner-approved.

Mentor notes are split into entrepreneur-visible section notes, an
entrepreneur-visible overall note, and a private advisory note. The private note
is never returned by the entrepreneur-visible payload.

Framework criteria remain hidden while the founder is building. They become
visible only after the assessment is finalised, ready for the report appendix.

## WO-89 - Assessment Report and Concept PV

`ReportComposer::composeEntrepreneurAssessment()` builds the founder assessment
report through the shared `reports` and `report_sections` pipeline. WO-89 adds
`entrepreneur_profile_id` to reports, report sections, and PV calculations, and
relaxes `client_id` so entrepreneur-only reports do not need a synthetic client.
RLS includes the same assigned-advisor / entrepreneur-user visibility used by
the rest of the entrepreneur module.

The report has four parts:

1. Criterion scores with AI first-pass scores, advisor-adjusted scores where
   present, document-support notation, and a data-quality indicator.
2. Written feedback for each of the 11 criteria.
3. Overall grade with an explicit rationale and concept PV projection.
4. Prioritised improvement actions linked to NZ resources where active matches
   exist.

Concept PV is stored as `pv_calculations.type =
entrepreneur_concept_projection` and linked back from
`plan_assessments.concept_pv_calculation_id`. It is deliberately labelled as an
indicative projection from draft-plan maturity, not a valuation or investment
recommendation.

## WO-90 - Iterative Resubmission and Progress

`plan_revisions` records every resubmission round for a business plan. There is
no maximum round count. `App\Services\Entrepreneurs\Revision` opens a plan for
revision, submits the revised draft, re-runs the first-pass assessment, and
stores a `progress_comparison` payload for the new round.

The comparison payload includes:

- previous and current assessment rounds
- previous and current weighted scores
- previous and current grade
- overall score delta
- trajectory percentage, calculated against the prior remaining opportunity to
  reach 100
- per-criterion deltas with improved / regressed / unchanged direction
- biggest improvements
- remaining criteria below 60

Advisor entrepreneur detail now receives the latest plan progress summary and
renders round count, latest grade, trajectory percentage, biggest improvements,
and remaining gaps.

## WO-91 - Benchmarking, Advisory Readiness, and Living Plan

`config/entrepreneurs.php` defines `benchmark_min_cohort` from
`BENCHMARK_MIN_COHORT`, defaulting to 5. `Benchmarking::forPlan()` compares an
entrepreneur plan only against prior finalised same-industry plans. If the
cohort is below the configured threshold, the benchmark is suppressed and no
figures, distributions, or cohort size are returned. When the cohort is large
enough, output remains aggregate-only: cohort size, average score,
percentile band, grade distribution, and privacy flags. It never returns
per-plan values, plan ids, min, or max.

`advisory_readiness_signals` stores the systematic advisory-readiness signal
for a profile. `AdvisoryReadiness::evaluate()` creates or updates the signal
when the latest assessed plan reaches the readiness threshold, moves the
profile to `advisory_ready`, and sends an advisor notification.

Living plans use quarterly timestamps on `business_plans`.
`LivingPlan::schedule()` sets the next update date after launch,
`LivingPlan::duePlans()` finds due launched entrepreneur plans,
`LivingPlan::prompt()` records the prompt, and `LivingPlan::reassess()` creates
a fresh assessment round, stores divergence flags, schedules the next quarterly
update, and re-evaluates advisory readiness.

The entrepreneur portal dashboard now receives latest plan progress, advisory
readiness score, next living-plan update, and divergence flags.
