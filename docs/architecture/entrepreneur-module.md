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
