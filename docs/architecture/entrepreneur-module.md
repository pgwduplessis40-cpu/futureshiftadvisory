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
