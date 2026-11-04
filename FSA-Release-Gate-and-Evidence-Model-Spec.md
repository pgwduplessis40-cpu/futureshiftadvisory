# FSA Release Gate and Evidence Model - Functional Specification (All Phases)

4 November 2026 - Prepared for Pieter, Principal Advisor, Future Shift Advisory

Revision: corrected against the three supplied PDFs on 4 November 2026. This revision updates the specification and test expectations; it does not approve a client release or alter the client's saved data.

Implementation record (5 November 2026): the authorised Phase 1 work is recorded in [the codebase mapping and implementation notes](docs/architecture/release-gate-phase-1-mapping.md#10-implementation-record). It enables 20 rules and explicitly defers 17. F1 remains Draft; later-phase financial reconstruction, evidence and final-artifact certification requirements remain in force.

## Purpose and scope

This spec prevents FSA from certifying a plan, budget pack or founder brief unless its financial calculations, current plan claims, evidence classifications and final files reconcile to one captured source version. It covers six phases, built in order. Draft downloads remain available for review with clear draft markings; possession of a draft is not certification or permission to issue it externally.

**What triggered it.** The Drawer Full of Giants Ltd review found material disagreements between the current plan narrative and the displayed budget. It also found that some diagnoses in the original spec went beyond what the PDFs establish. Correct those diagnoses before turning them into release rules or acceptance tests.

### Reviewed source baseline

Page references below are PDF page numbers, including covers. These are the exact supplied exports, not a fresh production-state inspection. File hashes identify the evidence; preserve the originals unchanged.

| Source | Filename | Pages | SHA-256 |
| --- | --- | --- | --- |
| Plan | `tania-hassounia-business-plan.pdf` | 49 | `c629b7434424fa0cbc0d117fec93cc18dc085247a9a0bc5cfac293b39513d382` |
| Pack | `tania-hassounia-budget-pack.pdf` | 11 | `1e3610e1046ddba6e929d7327be388ef8c41d99709961dfae680108c977bc789` |
| Brief | `tania-hassounia-founder-brief-advisor-draft (1).pdf` | 11 | `9b13e5e86e5a1ad175d71ffac996e11cef64d03bb9d99d59b542b356f639f140` |

| Finding | Corrected observation and limits | PDF evidence |
| --- | --- | --- |
| Revenue ramp | The plan states $3,000, $6,000 and $9,000 for Months 1-3; the forecast shows $12,250.00, $12,470.54 and $12,695.22. This is a confirmed mismatch in the presented base case. | Plan p. 44; Brief p. 6; Pack p. 11 |
| Break-even and answer versions | The plan still renders a Year 2 profitability/break-even statement immediately before an UPDATED section. The pack reports Year 1 and marks break-even M1. Select the current answer and the same break-even definition; do not treat superseded prose as an agreed input. | Plan p. 43; Pack p. 3 |
| Owner pay | The plan states a current $550/week and intended steps to $875, $1,200 and $1,500. It also states $3,698/month, which is inconsistent with $550 x 52 / 12 = $2,383.33. The budget displays $875/week, $3,791.67/month and flat monthly total fixed costs; it does not show the proposed steps. | Plan p. 45; Brief pp. 6, 8; Pack pp. 5, 11 |
| Revenue scope and capacity | The updated plan makes Intensives the only base stream and states 6-8 Intensive days/month at $1,500/day. The pack lists six streams with 19 aggregate capacity units, without stream-level revenue or a conversion of those units to founder time. The conflict needs reconciliation, but neither 19 units = 19 days nor $14,917 total revenue = Intensive-only revenue is established. | Plan pp. 44-45; Pack pp. 7, 11 |
| Opening cash and runway | The plan states approximately $7,300 in business accounts excluding owner's compensation, $600/month essential costs and an additional reserve description. The model uses $7,531 opening cash. The nominal difference is $231 (3.16% of $7,300), but matching dates, included accounts and restricted funds are not established. The plan's no-sales essential-cost runway and the model's forecast cash runway are different measures. | Plan pp. 27, 45; Pack pp. 2, 9; Brief p. 6 |
| Missing planned expenditure | The plan's $1,750 content investment has no corresponding line in the displayed cost schedules. The event, Samoa visit and second trademark need cost/timing or an explicit exclusion; their amounts cannot be invented. Professional indemnity and a DFoG trademark line are present, so do not repeat the older claim that both are wholly absent. | Plan pp. 20-21; Pack pp. 5-7; Brief pp. 8-9 |
| Funding disclosure | $14,183 available less $7,531 opening cash implies $6,652 other funding. The exports do not identify its sources, availability dates or whether any amount duplicates opening cash. This is missing provenance, not proof that no source exists in the database. | Pack pp. 2, 4, 9; Brief pp. 6-7 |
| Cash arithmetic | All 12 closing balances in the brief equal the preceding balance plus net movement; net movements sum to $60,020.56 and closing cash is $67,551.56. Month 1 can be reconstructed as $6,652 funding - $4,335.07 fixed costs - $1,838.18 tax = $478.75. This reconstruction does not verify the funding source or the full payments ledger. Zero customer collections alone does not prove a cash-identity failure. | Brief pp. 5-7; Pack pp. 10-11 |
| Downside results | Revenue-downside lowest cash is $8,620.15 versus base $8,009.75. A reconstruction with 20% lower revenue and direct costs reduces Month 1 tax from $1,838.18 to $1,227.78, explaining the $610.40 cash improvement. This is consistent with payment timing, not proof of a broken engine. Complete scenario inputs and ledgers are still needed. Cost upside has the lowest displayed cash ($7,735.43); the pack's description of revenue downside as the hardest test needs an explicit ranking metric. | Brief p. 10; Pack pp. 3, 8 |
| Funding measure | The $232.73 is $14,415.73 reserve/funding target less $14,183 available. The presented base cash curve stays positive. It is a reserve-target shortfall, not evidence of an operating cash deficit or a necessary borrowing request. The scenario table's $0 cash-cover amounts measure something different. | Brief pp. 7, 10; Pack pp. 2-4 |
| Horizon and actuals | The exports contain one forecast year starting April 2026, with no separate actuals for elapsed months through October. Year 2 profitability remains asserted and later costs are listed. The brief explicitly says Years 2-3 are unavailable and later commitments are excluded. The PDFs do not prove the historical claim that a Month 13 finding disappeared during a horizon change. | Plan p. 43; Pack pp. 7, 9-11; Brief pp. 5, 9 |
| Document and evidence quality | UPDATEed/UPDATED content remains on plan pp. 11, 17 and 43; onboarding content is duplicated on pp. 36-37; raw Markdown remains on pp. 22 and 40. Pack pp. 5-7 contain advisor prompts. The brief states no supporting files are linked; no claim-by-claim evidence index is printed. Run-in headings and large unused page areas remain. The supplied brief does not contain the earlier `[Extract; see full source.]` marker, and it does print the currency symbol before $232.73. | Plan pages noted; Pack pp. 5-7; Brief pp. 3-4, 7, 11 |
| Existing controls | All three exports are visibly drafts, not approved external releases. Pack p. 2 lists evidence, funding-readiness, dated-update and sourcing blockers. The originals therefore do not establish either that only the funding gap was caught or that FSA certified these documents. | Plan pp. 1-2; Pack pp. 1-2; Brief pp. 1, 11 |

The brief's comparable financial figures agree with the pack after rounding to its displayed whole-dollar precision (NB-02). Different display precision is a formatting issue, not itself a numeric contradiction. A model-input snapshot, complete monthly ledgers, claim records and release history are needed to verify the remaining internal checks.

**First recommendation.** Establish one agreed current set of assumptions, named funding sources and committed costs before rebuilding the forecast. Keep unknowns explicit. Prioritise reconciliation, answer versions, facts binding and exact-file release checks before expanding the evidence wizard and audience variants. F1 preserves the flawed exports; F2 uses a separately approved reconstruction, never silent edits to F1 or the live client record.

**In scope:** the release gate rule library, the revenue and cash engine, facts-bound narrative, the evidence model, document templates and QA.

**Out of scope:** tax or legal advice, lender submission portals, and visual brand design beyond the structure defined here.

## Decisions and open items

The following design decisions incorporate the review recommendations. The five commercial defaults below remain subject to the Principal Advisor's confirmation before their matching policy is finalised.

**Decided**

| Decision | Detail |
| --- | --- |
| Quality standard | One universal core document of lender-grade quality for any audience; only the ask page varies |
| Rule implementation | Hybrid: rule logic, integrity severity floors and override eligibility hard-coded and tested; permitted thresholds, severities and message wording configurable without a deploy |
| Override authority | Arithmetic, provenance, current-source reconciliation and release identity cannot be overridden. Only the Principal Advisor may accept an eligible business-risk exception, with a logged reason and external disclosure |
| Evidence | Not everything must be evidenced; unevidenced claims are recorded as founder evidence and visibly labelled |
| Model results | An advisor release decision never changes a model result |
| Missing data | An applicable check that cannot run returns Cannot verify and prevents progression at its severity; it never becomes Pass |
| Applicability | Not applicable requires a demonstrated business/scope reason. Missing inputs or a shortened required horizon are not reasons to skip a check |
| Funding | Cash deficit, reserve-target shortfall and external funding request are separate measures; a positive reserve gap alone does not establish a need for borrowing |

**Defaults pending confirmation**

| Item | Default | Status |
| --- | --- | --- |
| Evidence minimum for lender-ready release | Established business (2+ years trading): missing financial history or revenue proof raises advisor review. Startup: no blanket attachment minimum; complete claim classification, unverified labels and applicable integrity inputs still required | To validate with a bank contact or accountant |
| Concentration threshold | Advisor review when more than 50% of Year 1 revenue rests on inputs with no basis or test | Confirm |
| Materiality for missing plan costs | Block when a committed in-scope plan cost is $1,000 or more and lacks a budget/actuals mapping; clarify unpriced or discretionary items through Review | Confirm |
| Capex review threshold | Flag asset-like items of $1,000 or more classed as operating cost; this is a review trigger, not an automatic accounting/tax classification | Confirm |
| Opening cash tolerance | 1% for figures on the same date and account/restriction basis; otherwise require a cash reconciliation | Confirm |

Other configurable defaults: revenue-downside volume reduction 20%, cost-upside rate increase 10%, collections delay 30 days, and a slow-start first quarter at 50% of planned volume. Record the version and dimension of each shock; these are not minimum changes required in cash or profit. Use decimal money calculations with an explicit rounding policy and a 1-cent internal reconciliation tolerance; allow only documented display-rounding differences in comparisons of published figures.

## Trust model

Every document version is in one of three states, and only the last may be issued externally through FSA's release flow. An internal watermarked review copy can be downloaded without changing its state. These controls cannot recall a file already downloaded or prevent a person sharing a draft outside FSA.

| State | Entry condition | Who sets it |
| --- | --- | --- |
| Draft | Created or regenerated; checks may be failing | System |
| Advisor-reviewed | All applicable integrity Blocks pass; any eligible business-risk Block has a recorded exception; all Reviews are resolved or accepted on the same source snapshot | Principal Advisor |
| Release-certified | Advisor-reviewed, plus complete claim classification, typed fact binding, final-artifact checks and atomic release of the checked snapshot and exact files | System, on the Principal Advisor's release action |

**What FSA certifies:** arithmetic consistency under the stated model policies, reconciliation of current plan claims to that model, document quality and complete classification of every claim in the exact files identified by the release record. Accepted business risks are disclosed; they cannot conceal a failed integrity check. Certification is not a promise of forecast performance or lender acceptance.

**What FSA does not certify:** that the founder's assumptions or assertions are true. The founder remains responsible for plan content, assumptions and evidence, and the document states this on the cover and on the "What the founder is carrying" page.

Changes to any source used by the release (including plan answers, model inputs or engine version, actuals, evidence, rule configuration, templates or rendered narrative) require a new Draft version. Append a supersession event to the previous release; retain its immutable record and original files. The earlier record remains proof of what was released then, not certification of the current workspace. Reconcile report dates and actuals cutoffs when creating a new version.

## Phase plan

Build in this order. Each phase ships behind passing fixture tests and does not start until the previous phase's done criteria are met, except where noted.

| Phase | Scope | Depends on | Done when |
| --- | --- | --- | --- |
| 1 | Release gate framework, source-version contract and rule library | Repository mapping and archived F1 evidence | Phase 1 rules produce the scoped F1 results; applicable missing inputs cannot pass; non-overridable integrity checks, draft access and release log skeleton work |
| 2 | Revenue and cash engine | Phase 1 and agreed reconstruction inputs | Driver-based model reproduces the agreed ramp and shared capacity; complete cash ledgers and scenario bridges reconcile; actuals, funding measures and lender metrics are computed |
| 3 | Facts-bound narrative, answer versioning and exact-artifact release service | Phases 1 and 2 | No unbound claim number or superseded answer renders; all documents use one snapshot; concurrent changes and unverified fallback output prevent certification |
| 4 | Evidence model | Phase 1 | Every claim is classified; critical facts require a document or a visible unverified label; the founder-carrying page renders |
| 5 | Document templates | Phases 2 to 4 | Universal core plus ask page variants render for all fixtures with zero hygiene failures |
| 6 | QA hardening | Phases 1 to 5 | Coverage thresholds met; independent recalculation, retained-artifact and concurrency tests pass; release log complete |

Phase 4 can run in parallel with Phases 2 and 3 once Phase 1 lands, but the fuller evidence wizard and audience variants must not delay the core reconciliation work. Rules are tagged with the phase in which they become enforceable. A deferred rule is recorded as not yet enabled, not Pass or Not applicable. Full Release-certified status is unavailable until all rules and release prerequisites for that document are implemented and satisfied; Phase 6 hardens checks already required for release.

## Phase 1: release gate framework

Phase 1 generalises the existing reconciliation gate into a rule library that runs on every document before it can leave Draft. Preserve current integrity protections and one assessment workflow. Explicit policy changes in this revision are the replacement of the blanket attachment prerequisite with claim classification, and the separation of reserve gaps from cash deficits. Replace those legacy predicates only alongside equivalent or stronger checks, regression tests and visible explanations; do not bypass the gate wholesale.

**Rule record.** Each rule is code with tests and has these fields:

- `id` (for example PM-01) and `name`
- `category`: plan vs model, model integrity, scenario sanity, document hygiene, narrative binding, evidence
- `inputs`: the plan answers, model outputs and document text it reads
- `condition`: the exact test, using named configurable thresholds
- `severity`: Block, Review, Info or Autofix
- `minimum_severity` and `override_policy`: code-enforced severity floor and `never` or `principal_advisor`
- `applies_to`: internal, external or both
- `applicability`: a deterministic predicate, its source inputs and the reason for any Not applicable result
- `subchecks`: named assertions with their own result, compared values, precision, source references and missing inputs; an applicable missing subcheck prevents an aggregate Pass
- `message`: a plain-English template naming conflicting values, or the specific missing input when the result is Cannot verify
- `phase`: the phase in which the rule becomes enforceable

**Severities**

| Severity | Effect |
| --- | --- |
| Block | Document cannot progress until the check passes, except an explicitly eligible business-risk rule may receive a Principal Advisor exception. Integrity Blocks and their missing inputs cannot be overridden or downgraded |
| Review | Principal Advisor must resolve or accept the item before release; acceptance is logged |
| Info | Shown in the findings panel only |
| Autofix | System corrects presentation text and logs the change before final rendering; it then reruns affected checks. Never silently alter financial inputs, quotations or evidence |

**Result states.** Every enabled rule returns Pass, Fail, Cannot verify or Not applicable. Fail means a demonstrated violation; Cannot verify means an applicable check lacks a required input. Both prevent progression at the rule's severity, but messages and logs must preserve the distinction. Not applicable requires a proven scope condition, such as no debt for a debt-service ratio. An empty horizon, unparsed field or missing ledger is Cannot verify, not Not applicable. Required future claims and prior unresolved findings survive a horizon reduction; any scope change is explicitly recorded. Draft-only prompts can be permitted in internal review while failing the external-release candidate check.

**Structured plan fields.** Capture the current answer version and typed fields for break-even definition/period, owner-pay schedule and triggers, dated opening cash by account and restriction, stream membership, price and unit, shared capacity, ramp, margin target and period, runway definition and named costs. Retain source locations and unresolved competing claims during migration; extraction from old prose is a proposal, not founder approval. Financial free text must bind to those fields or produce a reconciliation finding. Do not make a conflict disappear by ignoring the prose that is still rendered.

**Overrides.** All default Block conditions in the catalogue are integrity requirements with `minimum_severity=Block` and `override_policy=never`. Business-risk Reviews PM-07, PM-08, MI-05, SS-02, EV-03 and EV-04 may be configured as Block and have `override_policy=principal_advisor`; an exception then accepts the disclosed risk, not a factual error. Other Review outcomes remain subject to advisor resolution/acceptance, and cannot waive an associated integrity Block. Exceptions record the rule/configuration version, snapshot and finding hashes, compared values, reason, user and time, and appear in the document's advisor review appendix. A changed dependency invalidates the exception. An override never changes a model result, substitutes for a missing integrity input or turns Fail into Pass.

**Findings output.** One findings list per document version, grouped by severity, each linking to the plan answer or budget input to fix. The list replaces the free-text review findings currently shown on the brief.

## Rule catalogue

The catalogue holds 37 rules. Severity shown is the default, subject to the integrity floors above. Each rule must declare its comparison basis, tolerances and required inputs. The evidence column refers to the reviewed PDFs, not presumed database contents. F1 below distinguishes demonstrated violations from missing inputs and observable passing subchecks. Block/Review branches are evaluated separately; a reserve-policy Review cannot downgrade a funding-provenance Block.

**Plan vs model**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| PM-01 | Every rendered profitability/break-even claim uses a current answer, names its measure (monthly operating break-even, annual profit or cumulative cash) and agrees with the matching model fact and period. Conflicting rendered versions fail; an unidentifiable measure is Cannot verify | Block | 1 | Year 2 statement remains on Plan p. 43; Pack p. 3 reports Year 1 and M1 |
| PM-02 | Current and staged owner pay, frequency conversions, effective dates and triggers reconcile within the plan and to the monthly model schedule. A yearly average cannot stand in for a current rate without a labelled schedule | Block | 1 | $550/week is $2,383.33/month, not the plan's $3,698; the export uses $875/week |
| PM-03 | Opening cash reconciles on the same date, currency, account and restriction basis within the configured tolerance (default 1%). Different dates/bases require a documented roll-forward; an earlier date is not an exemption | Block | 1 | $7,300 and $7,531 are shown, but a comparable dated cash bridge is unavailable |
| PM-04 | Each stream's delivery volume and revenue fit its documented unit, price and capacity schedule; compare total revenue to a single-stream ceiling only after base-case membership is established. Missing stream allocations or unit definitions are Cannot verify | Block | 1 | $14,917 aggregate Month 12 revenue cannot be assigned entirely to Intensive days from these exports |
| PM-05 | The model ramp for the same included streams and forecast start period matches the current plan schedule within the stated currency/display tolerance | Block | 2 | Plan p. 44: $3,000/$6,000/$9,000; Brief p. 6: $12,250.00/$12,470.54/$12,695.22 |
| PM-06 | Every stream has explicit base/upside membership, and monthly base totals equal only included streams. Membership agrees with the current plan; missing flags or contributions are Cannot verify | Block | 2 | Plan p. 45 excludes developing offers; Pack p. 7 lists six streams but does not expose their monthly contributions or membership flags |
| PM-07 | Compare actual model NPBT percentage with the stated target on a named period and basis; flag an unexplained variance or unspecified target period. A target is not a command to force the forecast into that range | Review | 1 | Year 1 57.1% versus a stated mature-business target of 20-30%, with no bridge |
| PM-08 | Giving, reinvestment and owner-distribution policies have explicit amounts/triggers and cash schedules, or a visible statement that they are excluded/conditional. Do not double-count reinvestment already in cost or capex lines | Review | 2 | Plan p. 40 states 50% giving, 25% reinvestment and 25% distributions; exports do not show the treatment |
| PM-09 | A committed in-scope plan cost at or above $1,000 has a mapped budget/actuals line and timing; a discretionary item can be explicitly deferred/excluded with the plan claim updated. A missing quantified commitment is Block; unpriced/unclear-scope items and smaller costs require Review, not an invented zero | Block / Review | 1 | $1,750 content work is missing from displayed schedules; event, visit and second-brand trademark need scope/cost clarification |
| PM-10 | Every runway claim names its start cash, period, cost basis and whether it includes future receipts and owner pay. Compare like measures; label and reconcile essential-cost/no-sales runway separately from forecast cash runway | Review | 2 | Plan's 12 months excludes owner income; pack's >12 months uses forecast cash. They must not be presented as the same metric |
| PM-11 | Aggregate founder delivery and preparation time across included streams fits one shared time budget; additional delivery has a specified provider, capacity, timing and cost or a documented no-cost basis. Missing unit-to-time conversions are Cannot verify | Block | 2 | Six rows total 19 generic capacity units; no conversion to the founder's 6-8 Intensive days is printed |
| PM-12 | Funding-position claims reconcile to named sources, cash deficit, reserve-target shortfall and any external request. Unknown/double-counted sources or a self-funded claim relying on uncovered cash deficits block. A reserve-only gap is Review with a disclosed action/acceptance, not automatically borrowing or a false self-funded claim | Block / Review | 1 | $232.73 is a reserve-target gap; the funding status of the separate $6,652 cannot be verified |

**Model integrity**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| MI-01 | Every closing cash balance equals opening cash plus net movement; movement equals customer receipts plus other operating receipts and financing inflows, less operating payments, cash tax/GST, capex, principal repayments and distributions. Consecutive balances and annual totals reconcile; every movement has a ledger category, date and source, counted once. Missing ledger components are Cannot verify | Block | 1 | All 12 displayed balance identities pass. Month 1 positive movement is consistent with a $6,652 inflow; its source and complete ledger remain unverified |
| MI-02 | Opening cash and each funding source reconcile to the available-funds total without duplication. Record source identity/type, commitment/evidence status, amount, availability date, restrictions and draw schedule; do not use a balancing plug or future funding to cover an earlier deficit | Block | 1 | $14,183 - $7,531 = $6,652 is arithmetically clear, but named source records are not in the PDFs |
| MI-03 | Unrounded fixed-cost rates, units, frequencies and staged schedules reconcile to monthly expense and annual totals under the recorded rounding policy. Separately reconcile expense recognition to cash-payment dates; document permitted displayed rounding | Block | 1 | Printed trace/model totals agree at whole-dollar precision; underlying exact rates and cash dates are not fully disclosed |
| MI-04 | Tax expense follows the stated entity/tax policy: eligible losses and explicit adjustments determine taxable profit; cash tax follows its separate due-date schedule. No automatic tax receipt is created by a loss. Missing applicable policy/schedule inputs are Cannot verify | Block | 1 | Displayed annual tax is consistent with 28% to published precision; this does not establish a full loss/timing reconciliation |
| MI-05 | Asset-like expenditure at/above the $1,000 review threshold classified as operating cost receives classification review. Amount alone does not determine capex; apply a stated accounting policy, useful life and cash date | Review | 1 | Future vehicle, laptop, phone and studio items are labelled operating costs. Their dates are beyond Year 1, so Year 1 capex of zero is not itself an error |
| MI-06 | The model covers each period for which a financial outcome or funding sufficiency is claimed. Aspirational later activity may be separately labelled unmodelled; listing an explicitly excluded future commitment does not assert it is funded. Missing required periods cannot erase findings | Block | 1 | Unresolved Year 2 profitability claim fails against a one-year forecast; Brief p. 9 correctly discloses exclusion of later costs |
| MI-07 | At the release snapshot date, every elapsed reporting month has reconciled actuals or is explicitly established as pre-trading/not applicable. Actuals and forecasts have distinct labels and a checked cash handover; missing records are Cannot verify | Block (external) | 2 | April-October actuals are not supplied in the reviewed PDFs; absence from the production database is not established |
| MI-08 | Where the required scope includes Month 13, reconcile its revenue to Month 12 by stream with growth, seasonality, capacity and starts/stops explained. Missing Month 13 is Cannot verify. Not applicable only when the legitimate scope has no Month 13 claim or requirement | Review | 2 | Cannot test the bridge from these one-year exports; no historical finding-erasure event is proven |

**Scenario sanity**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| SS-01 | Scenario inputs use the same baseline, dates and opening balances with only declared shocks and documented dependent responses. Recompute the full cash ledger, profit and reported extrema; explain differences through a bridge of receipts, costs, tax, funding and timing. An unexplained result is Cannot verify or Fail if a mismatch is demonstrated. No universal ordering of lowest cash/profit is imposed | Block | 1 | Higher revenue-downside cash is consistent with $610.40 less Month 1 tax; full scenario ledgers are unavailable |
| SS-02 | Validate each shock on its own defined input dimension and interval: revenue-downside volumes, cost-upside rates, slow-start quarter volumes, or collection-date delay. Show requested and effective shocks after capacity caps and actuals protection; flag ineffective/undersized stresses. Do not apply a 20% annual revenue test to a collections-delay or slow-start scenario | Review | 2 | The PDFs label scenarios but do not print shock parameters; similar cash results do not prove inadequate stress |
| SS-03 | Calculate cash deficits and reserve-target shortfalls for every required scenario on the same declared basis. Identify the worst scenario separately for each metric; any proposed external request addresses the chosen requirement and its dates after named non-external sources, without double subtraction. A no-request decision with a reserve-only gap is disclosed, not converted automatically into debt | Block | 2 | $232.73 reserve gap and $0 scenario cash cover are different measures; per-scenario reserve targets and complete source schedules are not supplied |

**Document hygiene**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| DH-01 | No advisor-entry prompts or internal review instructions in the external-release artifact; allow them in clearly marked internal review copies | Block (external) | 1 | Cost/revenue prompts appear in the internal pack on pp. 5-7; they would block promotion of that content to external issue |
| DH-02 | No unresolved update/superseded placeholders, runs of underscores, TBD or truncated-extract markers in release content. Legitimate draft notices are required on review copies and are not this rule's defect | Block | 1 | UPDATEed/UPDATED sections remain on Plan pp. 11, 17, 43; the supplied brief has no truncated-extract marker |
| DH-03 | No duplicated headings or near-duplicate blocks within a document | Review | 1 | "Client Preparation & Onboarding" appears twice |
| DH-04 | No raw markdown artifacts in rendered text (for example asterisks) | Block | 1 | "**Collaboration **" in the values list and profit allocation |
| DH-05 | Monetary amounts have unambiguous currency/units, plurals are correct and a shared display policy governs precision and dates. Distinguish display-style differences from mismatched values (NB-02) | Review | 1 | "1 days" and mixed precision remain; the brief correctly prints "$232.73" |
| DH-06 | System-generated text uses hyphens, not em dashes | Autofix | 1 | Em dashes in the executive summary |
| DH-07 | The same offer is described consistently across sections | Review | 3 | Intensive is "two- or three-day" in one place and "two- to four-day" in another |

**Narrative binding**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| NB-01 | Every quantitative claim in generated narrative has a typed reference to a current model fact, historical record, source-backed claim or explicitly labelled founder assumption. Validate semantic field, entity, unit, period, scenario and provenance; matching an unrelated numeric value is insufficient. Missing binding metadata is Cannot verify | Block | 3 | Forecasts, paid work, trading dates and market statistics require different source types; PDFs alone cannot prove token provenance |
| NB-02 | The same financial metric, entity, period and scenario agrees across plan, brief and pack at the declared display precision, using a shared rounding policy. Consistent cents versus rounded dollars is Pass | Block | 3 | Comparable brief/pack financial figures agree after rounding, including all 12 monthly rows |

**Evidence**

| ID | Rule and condition | Severity | Phase | Tania evidence |
| --- | --- | --- | --- | --- |
| EV-01 | Every externally rendered claim maps to a versioned claim record with one of the four statuses and an auditable classifier. Unsupported claims cannot be promoted to Verified simply by attaching a file | Block | 4 | A blanket founder-assertion note exists; a complete claim inventory is not supplied |
| EV-02 | Each critical factual claim (registration, GST, insurance held, historical results, paid/completed work) has relevant supporting evidence with scope/status or an adjacent/scoped visible unverified label. A general endnote alone does not qualify every claim in a separate document | Block | 4 | Paid-work and registration claims in the plan lack linked proof or scoped unverified labels; Brief p. 11 reports no linked files |
| EV-03 | Sensitivity-ranked key assumptions have a basis, validation step, date and fail response, or an explicitly accepted missing-validation Review. Do not assume all founder assumptions lack a basis | Review | 4 | Plan goals and risk sections contain tests and responses, but no complete mapping from model inputs to those tests is supplied |
| EV-04 | Calculate the union of Year 1 revenue exposed to Founder-stated/Founder assumption inputs missing a basis or test; count each stream-month once even if price and volume are both unsupported. Review above 50%; absent attribution data is Cannot verify, not 100% or zero | Review | 4 | The PDFs do not establish the dependency mapping or unsupported share |
| EV-05 | Every claim has an index entry; cover counts reconcile to unique claim IDs and their current status. Source snapshots/links have access dates and permitted visibility; no private evidence leaks into exports | Block | 4 | No claim index or evidence-strength counts are printed |

## Phase 2: revenue and cash engine

Phase 2 replaces the growth-formula revenue with a driver-based model that reproduces the agreed current plan. Prices and profit measures are NZD and GST exclusive unless labelled otherwise. Bank cash must separately account for GST receipts/payments and restricted tax reserves where applicable; a GST-exclusive analytical cash view must be labelled and reconciled to bank cash before supporting a funding decision.

**Revenue streams.** Each stream has: ID, name, unit (for example Intensive day or engagement), price per unit, monthly sales/delivery schedules, preparation and delivery hours per unit, shared founder-time allocation, additional delivery provider/capacity/cost, evidence status and an `in_base_case` flag. Distinguish bookings from delivery and invoicing. Document how engagement lengths convert to days; a historical four-day $4,000 engagement does not independently prove a $1,500/day realised rate.

- Monthly revenue per stream = min(volume, total capacity) x price
- Contractor cost = max(0, min(volume, total capacity) - founder capacity) x contractor cost per unit
- Only streams flagged `in_base_case` count in the base scenario; excluded streams appear as upside lines
- Compare total founder hours across streams to one shared monthly availability budget, including preparation, administration and business development. Independently assigning the full founder capacity to every stream is not permitted.
- Additional delivery must have a costed provider or a documented valid no-cost basis. Missing resources, duration or unit conversions return Cannot verify rather than assuming generic units are days.

**Costs.** Direct costs are a percentage of revenue or a cost per unit. Fixed costs carry rate, quantity, frequency, expense-recognition schedule and cash-payment dates. Annual expense can be spread for profit reporting while cash is paid on its due date. Owner pay has dated weekly amounts and deterministic triggers (for example trailing three-month revenue), with explicit treatment of downward/reversible steps in a downside. Weekly-to-average-monthly conversion is x 52 / 12; actual cash follows the payment calendar. Do not present an annual average as the current weekly rate. Map every committed plan cost, including marketing support, to a budget or actuals line; document deferred discretionary projects separately.

**Collections and payments.** Parameters include deposit percentage, booking dates/lead time, balance timing, debtor days, creditor days and opening receivables/payables. The plan's preferred arrangement (deposit at booking, balance before work) must be expressible. Allocate dated receipts and payments to months; do not round every positive number of days into an entire month's delay. Month 1 receipts can include amounts due from prior bookings/receivables, but never invented receipts. Show the full MI-01 bridge, including named financing movements, cash taxes, capex, debt principal and distributions. Track opening and closing receivables/payables so amounts shifted beyond the horizon remain visible.

**Capex and tax.** Asset classification follows a stated accounting policy; the $1,000 threshold only triggers review. Capital purchases are paid on their cash dates and depreciated over a stated life from a stated start date. Preserve eligible loss carry-forward and explicit taxable-profit adjustments: planning tax expense is `max(0, taxable profit after eligible loss offsets) x configured rate`, subject to the documented rounding and entity policy. A loss does not automatically generate a cash refund. Record loss opening balance, losses used, closing balance, expense, payments/refunds and due dates separately. Tax/GST cash timing cannot be silently omitted from a bank-cash or facility assessment. This is an indicative planning model, not a tax return calculation; eligibility and supplied policies must be recorded. See [IRD guidance on business losses](https://www.ird.govt.nz/situations/my-business-is-making-a-loss).

**Actuals rebasing.** Confirm the intended forecast start first. For each elapsed month, import or enter reviewed revenue, receipts, operating/capital payments, funding, taxes, debt, distributions, receivables/payables and closing bank/restricted cash. Store account mappings, source period, import version and reconciliation status. A latest-period Xero summary is not a complete monthly actuals ledger; repository mapping must establish what the integration supplies and what needs additional work. Permit documented manual actuals when Xero is unavailable. Lock actual months against scenario shocks and start the forecast from reconciled closing balances and outstanding obligations. Missing actuals trigger Cannot verify under MI-07; lack of trading must be demonstrated rather than silently treated as zero.

**Horizon.** 36 months by default, or a shorter explicitly approved scope that covers every claimed financial outcome and funded commitment. Calendar dates accompany month numbers. Explain the Month 12-13 bridge by stream; neither a historical date in an evidence claim nor a clearly unmodelled aspiration requires a forecast for that date. A reduction cannot erase an unresolved required-period finding without a recorded scope decision and corresponding removal/revision of the outcome claim.

**Scenarios** (defaults configurable)

| Scenario | Definition |
| --- | --- |
| Base | Plan case: in-base streams, plan ramp, staged owner pay |
| Revenue downside | Future delivery volumes x 0.8, with the effective revenue reduction reported after capacity limits and pricing |
| Slow start | First three forecast months after the actuals cutoff have volumes x 0.5, then the plan ramp resumes; historical actuals remain fixed |
| Cost upside | Eligible forecast operating and capex cost rates x 1.1; specify the included categories. Recompute tax and dependent responses, rather than multiplying tax, financing receipts or historical actuals |
| Collections delay | Eligible future collection dates later by 30 days; show receivables remaining beyond the horizon |
| Combined downside | Revenue downside, cost upside and collections delay together |

Every scenario is checked by SS-01 for consistent inputs and reconciled results and SS-02 for the correct input shock. A lower-volume scenario may legitimately improve an early cash minimum through lower supplier payments or tax. Show that bridge and any profit deterioration; never alter results to force monotonicity. Name the metric used to rank a scenario as worst (cash deficit, reserve shortfall, profit or another disclosed measure). A slow-start first quarter at half volume is a 12.5% annual volume reduction for a flat 12-month base, not an automatic failure of a separate 20% revenue-downside test.

**Lender metrics produced as standard**

```latex
\text{break-even units per month} = \frac{\text{monthly fixed costs}}{\text{price per unit} \times \text{gross margin}}
```

```latex
\text{DSCR} = \frac{\text{profit before tax} + \text{interest} + \text{depreciation}}{\text{principal repayments} + \text{interest}}
```

- Break-even utilisation = break-even units divided by total capacity
- The single-stream break-even equation above requires a positive contribution margin and a declared unit; use an explicit sales mix for multiple streams. Zero/negative contribution means no finite break-even under that case, not zero units. Separate monthly operating break-even, annual profitability and cumulative cash recovery.
- No-sales expense cover = unrestricted available cash divided by the stated monthly expense basis. Report essential-cost cover excluding owner pay separately where useful. Forecast cash runway = first date unrestricted forecast cash becomes negative; if it does not within the horizon, report only that horizon coverage, not an unsupported indefinite runway.
- Lowest cash month and amount for each scenario
- Owner pay as a percentage of revenue
- DSCR uses the same stated period for numerator and denominator, with debt amount, rate, draw dates, term, fees, repayment schedule and any interest-only period recorded. With no debt service, show Not applicable, not zero, infinity or a fabricated repayment capacity. The formula above is a labelled EBITDA-based planning ratio; do not represent it as every lender's covenant definition.

**Funding measures and sizing.** Store and show these separately for each scenario:

1. `C[s,t]`: dated unrestricted closing cash before the proposed new external facility. It already includes verified opening cash and each committed existing funding source exactly once, on its available/draw date, and all modelled cash outflows. Include the opening checkpoint; use finer dated checkpoints if monthly netting would hide a known payment before funding arrives. Unverified funding is a missing input to approval, not a balancing plug.
2. `cash_deficit[s] = max(0, -min_t(C[s,t]))`: additional cash needed to avoid a negative balance under that scenario.
3. `B[s]`: the reserve policy's three months of fixed costs, with the monthly cost basis and included owner pay explicitly recorded. `Q[s] = 10% x (B[s] + identified one-off cash costs[s])` is the default contingency. The principal one-off costs are already outflows in `C`; only their contingency is added here. `reserve_target[s] = B[s] + Q[s]`.
4. `reserve_target_shortfall[s] = max(0, max_t(reserve_target[s] - C[s,t]))`: additional cash needed to maintain that reserve. This includes any cash deficit; do not add the two measures together and do not subtract opening cash/funding again.
5. Record the maximum of each measure across required scenarios and the scenario/date producing it. A proposed external funding request is a separate decision after named owner/internal contributions, cost deferrals and reserve-policy choices have been modelled. State the selected objective, amount, source and timing. Recompute cash, fees, interest and repayments with that request before claiming it solves the requirement or reporting DSCR.

A self-funded business with positive forecast cash but a reserve-only shortfall may have an explicitly accepted reserve risk/action plan without a borrowing request. It must still pass funding provenance and disclose the unchanged gap. F1's legacy $232.73 is the printed static target-minus-available result; retain it as historical evidence, not a golden result for this revised dated sizing method. Tests must prove that $100 opening cash against $150 expenditure produces a $50 cash deficit (before reserves), not a negative requirement from subtracting the same $100 twice, and that a later draw cannot fund an earlier payment.

**Conditional arithmetic examples from Tania's data** (not a complete rebuilt forecast):

- At owner pay $875 a week: fixed costs $4,335 a month, gross margin 89.1%, break-even about 3.2 Intensive days a month
- At owner pay $550 a week: fixed costs about $2,927 a month, break-even about 2.2 days
- These break-even examples assume a $1,500 Intensive day and that the old blended 89.1% margin remains appropriate. An Intensive-only rebuild must establish its own direct-cost basis before these can become acceptance targets.
- At about 3.24 days, utilisation is about 54% of six days and 41% of eight days; it is not always below half of the stated 6-8-day capacity. At about 2.19 days, it is about 36% and 27% respectively.
- The $3,000/$6,000/$9,000 ramp is an input schedule. Whether cash falls below opening cash depends on confirmed deposits, payment dates, owner-pay steps, other funding and expenditure. Do not predefine a first-quarter dip before those inputs are agreed.

## Phase 3: facts-bound narrative and answer versioning

Phase 3 gives every quantitative claim a typed source and prevents superseded answers from rendering. Forecast figures come from the model; historical trading dates, paid engagements and market/competitor figures come from their own source records or labelled founder claims. The finance engine must not invent those non-model facts.

**Facts object.** Write a versioned registry containing model outputs, reviewed historical/source claims and founder assumptions. Each entry records ID, source type/version, value, unit/currency, entity, time basis, scenario, evidence status, allowed display precision and dependency hashes. Model entries include all break-even definitions, shared capacity, monthly/yearly results, current/staged owner pay, opening cash and restrictions, lowest cash/date, cash deficit, reserve-target shortfall, proposed external request, named funding and draw dates, runway measure/basis and scenario bridges. A release snapshot hashes the model inputs and engine version, current plan answers, facts, actuals, evidence, rules and templates; a model-input hash alone is insufficient.

**Token binding.** Templates and AI prompts reference facts as tokens, for example `{{fact:break_even_month}}`. When AI writes narrative:

1. The prompt includes the locked typed facts and permits quantitative claims only through their allowed tokens.
2. Resolve tokens through a controlled renderer and retain the binding map. Post-check currency, percentages, periods, counts and written-out quantitative claims; distinguish structural numbering from factual assertions.
3. Validate field meaning as well as value, entity, unit, period and scenario. A revenue token used as profit is invalid even if its digits occur in the registry. Unknown references fail; unavailable provenance returns Cannot verify. Reject or regenerate the affected text.
4. Historical years, market statistics and competitor prices must use their corresponding claim/source tokens, not fake forecast facts. Show evidence status and source date as required by EV rules.

**Founder text.** Founder-authored free text may describe intent, but quantitative financial claims must reconcile to the current structured inputs/facts and factual claims must map to evidence/assumption records. Propose bindings for founder review; do not silently rewrite assumptions to match the budget. Scan the rendered prose as well as structured fields so a conflicting legacy sentence cannot bypass the gate.

**Answer versioning.** Replace the append-with-"UPDATED" pattern:

- Each plan answer stores versions with timestamp, author and reason for change
- Only the current version renders; earlier versions stay in history for advisors
- Appending a new block beneath an old one is not allowed; the founder edits or supersedes
- Duplicate section detection (DH-03) runs on rendered output

**Staleness.** When any dependency changes, mark affected blocks and their review decisions stale. Rebind/regenerate and rerun the checks before leaving Draft. An advisor may accept unchanged explanatory wording only after its factual bindings have been revalidated; acceptance cannot retain obsolete figures. Derived summary generation should not invalidate an underlying input decision merely because it writes derived text, but the resulting narrative and artifact still require their own checks. The brief uses only current answers, facts and evidence from its captured snapshot.

**Brief generation.** Build plan sections from current structured fields and typed templates, preserving the substantive offer, milestones, risks and evidence. Do not substitute the first paragraph of each answer for a summary. Fix run-in headings such as "Purpose and direction: Mission The mission...". Retain a regression test for truncated-extract placeholders, but do not report that placeholder as present in the supplied F1 brief.

**Atomic release and artifact retention.** Capture one immutable source snapshot and a release-group ID shared by the plan, pack and brief. Render the intended final files into private candidate storage; run financial, narrative, hygiene and layout checks on those actual bytes, including any fallback-renderer output. Before publishing, atomically compare the captured dependency versions with current versions, verify reviewer authority and decision hashes, and commit the manifest/state change. An intervening edit, render failure, unverified fallback or changed rule/template prevents release. Persist the exact certified files and their hashes; downloads serve those files, not a fresh rendering of current workspace data. Retries are idempotent. Superseded releases remain auditable, with access/status rules that do not present them as current. Add a new watermarked Draft artifact for changed content; never modify old certified bytes in place.

## Phase 4: evidence model

Phase 4 classifies every claim honestly. A startup cannot evidence everything, so the standard is classification and visible risk, not proof for all.

**Claim record**

| Field | Description |
| --- | --- |
| `claim_id`, `version`, `section`, `source_answer_version` | Stable identity, source version and every rendered location |
| `text` | The claim as displayed |
| `type` | critical fact, revenue driver, cost, market, assumption, other |
| `status` | Verified, Own record, Founder-stated, Founder assumption |
| `evidence_refs` | Relevant document/version hashes, source links with access date or record IDs; record permitted visibility and the specific fact supported |
| `basis` | Why the founder believes it; expected for assumptions, with missing basis recorded as a gap rather than invented |
| `validation_step`, `validation_date`, `fail_response` | Expected test plan for key assumptions; missing elements follow EV-03 review/acceptance |
| `classified_by`, `classified_at`, `verification_reason`, `source_as_at`, `review_due_at` | Audit trail, verification scope and staleness controls |
| `model_dependencies`, `revenue_exposure` | Input/stream/month mapping used for sensitivity and concentration; no double counting |

**Status definitions**

| Status | Meaning | Example |
| --- | --- | --- |
| Verified | An authorised advisor checked a relevant source for the particular fact and period, with the reason recorded | A registration extract supports registration; a reconciled bank receipt can support payment |
| Own record | A supplied business record, with its limits stated; existence alone is not independent verification | An invoice supports an invoiced engagement but does not by itself prove payment |
| Founder-stated | Belief or experience, no proof yet | "Several prospects are in conversation" |
| Founder assumption | Forward-looking input the founder chooses | 25% growth, 6 to 8 days a month |

**Critical facts** must carry a document or a visible "unverified" label in external output (EV-02). They are: company registration, GST registration, insurance held, historical financials, and any engagement described as paid or completed. The test is whether a document could reasonably exist today.

Founders can submit records, assumptions and classification proposals. Only an authorised advisor may mark a claim Verified after recording what was checked; uploading an unrelated document is not enough. Validate relevance, date, coverage and accessibility. Changed/removed/expired evidence marks affected claims and releases stale. Use claim-scoped labels in every document; the brief's blanket disclaimer cannot cure unsupported factual claims in the plan. Avoid counting repeated renderings as additional evidence.

**Evidence ladder.** The wizard prompts the founder to climb, asking "What would convince a sceptic?" (for example enquiry in writing, quote sent, deposit or signed booking).

**Key assumptions** are the model inputs with the largest effect on Year 1 revenue and cash, ranked by sensitivity. Each needs a basis, validation step, date and fail response (EV-03).

**Concentration rule (EV-04).** Compute the union of Year 1 stream-month revenue exposed to Founder-stated/Founder assumption inputs missing a basis or test. Count each stream-month once even if several inputs are unsupported. Missing attribution is Cannot verify at Review severity. For genuinely zero Year 1 revenue, show Not applicable to revenue concentration and assess cash/cost assumptions under EV-03. Above 50%, raise Review. Report calculated break-even utilisation without requiring it to be low; funding requests use the declared worst-scenario measure.

**Evidence tiers** (guidance, not requirements):

1. Identity and legal: Companies Office extract, ownership, insurance certificates
2. Financial history: two years of statements or tax returns, GST returns, three to six months of bank statements, aged debtors
3. Revenue proof: invoices, deposits, signed bookings
4. Cost proof: statements or quotes for the largest cost lines
5. Market proof: sourced statistics and competitor pages with access dates
6. Director's personal position: banks only, kept private and never printed

For a business trading two years or more, missing Tier 2 or Tier 3 items raise a recorded evidence-minimum Review alongside claim-level EV-02 findings. For a startup, no attachments alone is not a Block if all claims are classified and applicable factual claims are labelled unverified. Financial integrity and actuals requirements still apply. This explicitly replaces the legacy blanket requirement in `PlanIssueReadiness` to attach a document to at least one completed response, only when claim-level controls and regression tests are in place. Until then, retain the existing prerequisite. Keep private director records out of exported indexes as well as PDF content; use authorised evidence descriptions rather than private source URLs.

**Outputs of the evidence model**

- An evidence index appendix listing every claim by status with links
- A cover summary of counts, for example "9 verified, 7 own records, 12 founder assumptions" (checked by EV-05)
- A "What the founder is carrying" page: key assumptions in order of revenue impact, with basis, planned test and downside result. It reads as candour and is mandatory in every external document

## Phase 5: documents and templates

Phase 5 gives every founder one universal core brief that holds up for any audience, plus a swappable ask page. Lenders are the strictest readers, so the core is built to their standard.

**Universal core structure**

| Page | Content |
| --- | --- |
| 1 | Cover and key-facts panel: business, owner, trading since, headline numbers, evidence-strength counts, document state |
| 2 | Business and offer: what is sold, price table, delivery model |
| 3 | Traction and evidence: actuals to date, paid clients, repeat work, referrals, confirmed bookings |
| 4 | Market and competitors: sourced statistics and a competitor price table |
| 5 | Financial summary: plan, base and downside side by side, with chart |
| 6 | Cash, break-even and runway: monthly cash, lowest point, break-even days and utilisation |
| 7 | Risks and mitigations: top five with early-warning signals and key-person cover |
| 8 | What the founder is carrying: key assumptions, basis, test, downside result |
| 9 | Ask page (variable) |
| Appendix | Evidence index, assumptions table, monthly detail |

**Ask page variants**

| Variant | Content |
| --- | --- |
| Self-funded | No external funding sought; named internal sources, cash deficit, reserve-target gap/action and break-even headroom. A proposed external facility uses the lender variant rather than contradicting "no funding sought" |
| Lender | Amount, term, use of funds, repayment capacity (DSCR in base and worst scenario), security and guarantees |
| Investor | Amount, use of funds, traction, unit economics, return and exit story |
| Grant or capability funding | Need, outcomes, evidence, budget, matched contribution |

Only the ask-page presentation changes between variants using the same assumptions and financing case. Core figures come from one captured model run and remain identical in those variants. A changed proposed loan, equity injection, repayment schedule or use of funds requires a new financing case, recalculation and release; do not retain old core figures while changing the economics on the ask page.

**Formatting standards (enforced by the hygiene rules)**

- Key-facts panel and a KPI strip on the cover; no half-empty pages
- Executive summary: a lead sentence, four to six bullets, then the KPI strip
- Real subheadings and bullets; no run-in headings
- Plan, base and downside columns on the financial page, in whole dollars
- Currency symbol on every amount; one date format across documents (for example 4 November 2026); correct plurals ("1 day")
- Hyphens, never em dashes, in all generated text
- Chart: monthly revenue against costs as columns with a single cash line; labels must not collide; one axis unless a second axis is essential and labelled
- Advisor-entry prompts and internal review notes never print in external output; show evidence status per row and deliberately authored disclosures of accepted business risks in an advisor review appendix
- The page ends on the ask or, for self-funded founders, on the founder-carrying page and a clear statement of what is and is not certified
- Draft markings are absent only from successfully checked final candidates published by the Principal Advisor's release action. Changed content receives a new Draft artifact with markings; retained certified files are never rewritten

## Phase 6: QA hardening and release log

Phase 6 makes quality repeatable so a lender can ask when and how a number was certified and get an answer.

**Test layers**

| Layer | What it checks |
| --- | --- |
| Rule tests | Pass, Fail, Cannot verify and justified Not applicable; subcheck aggregation, boundaries, forbidden overrides/downgrades and applicability after horizon changes |
| Fixture tests | Versioned inputs, provenance and expected outcomes for each enabled phase; historical PDF observations are not fabricated database facts. Changes to expected results require review |
| Independent recalculation | A separate calculator independently computes inputs, schedules and every monthly movement using the declared decimal/rounding policy. Internal money identities agree within 1 cent; published rounding is checked separately. A spreadsheet copying production results is not an independent oracle |
| PDF snapshot tests | Inspect all final pages, continuations and fallback output: collisions, excessive empty space, truncation, update markers, draft markings and cross-document values |
| Narrative tests | Wrong values, swapped fields, wrong periods/scenarios, spelled-out quantities, non-model facts, superseded answers and stale bindings |
| Release tests | Server-side authority, atomic snapshot comparison, edits during rendering, dependency/configuration changes, idempotent retries, failed/fallback rendering, exact-file downloads, supersession and immutable history |

**Coverage thresholds.** 80% for general code and 90% for the critical path: rule library, finance engine, fact binding, evidence classification and the release log.

**Release log.** An immutable record per release and per state change:

- Release-group ID, document ID/version/type, audience, financing case, report date and actuals cutoff
- Immutable source snapshot reference/hash: current plan answers, model inputs and engine, facts, actuals, evidence, templates, renderer and rule configuration
- Rule-set version, applicability reasons and each rule/subcheck result (Pass, Fail, Cannot verify, Not applicable), missing inputs and deferred-phase status where relevant
- Exact storage reference, hash and completed PDF checks for each file, including renderer/fallback identity
- Reviewer, timestamp and resulting state
- Eligible exceptions/Review acceptances: rule and finding hashes, values compared, disclosed risk, reason, user and time; protected integrity override attempts are denied and audited
- Evidence counts by status and the evidence index version

Only authorised administrators can change permitted rule configuration; audit old/new values, user and time, and enforce code-level floors and override eligibility. A source change creates a new Draft version and an append-only supersession event. Do not mutate an immutable record or delete historical findings. An accepted risk explains a failed business-risk result but never relabels it Pass. Phase 1 establishes this contract, Phase 3 implements exact-artifact release, and Phase 6 extends adversarial coverage.

**AI lender-persona review.** An optional pre-release step in which an AI reads the document as a sceptical credit analyst and lists questions. Output is advisory only, shown to the Principal Advisor, and can never change a gate result.

## Test fixtures

Fixtures are the acceptance tests for every phase. F1 uses the source hashes and page references above. The PDF review establishes observable assertions, not hidden inputs, token provenance, production rule execution or release history. Prepare a versioned extraction manifest with typed known values, explicit unknown fields and page provenance. Do not replace missing inputs with plausible values to make a test pass or fail.

Keep original client PDFs in controlled storage. Committing identifiable client records to test data requires the client's permission; this specification edit is not that permission. Prefer an anonymised equivalent with the same discrepancies, a separate hash manifest and checked numeric expectations for shared CI. Record which version each test uses.

### Fixture F1: corrected expectations

F1 is an archived, visibly marked Draft. Test internal viewing (which remains possible) and an attempted external-release transition against the same content (which remains blocked). The matrix specifies release-transition checks once their phases are enabled. Prompts allowed during draft viewing are not evidence of an earlier external release. A later-phase rule is deferred until enabled, never counted as Pass.

Cannot verify / Block means required inputs prevent release, not that a calculation is proven wrong. Keep observable passing subchecks even where the full rule cannot be verified. Those subchecks do not establish an aggregate Pass.

| Rule | Expected F1 result / effect | Evidence or verification still required |
| --- | --- | --- |
| PM-01 | Fail / Block | Rendered Year 2 claim conflicts with Year 1/M1 output; resolve versions and measure (Plan p. 43; Pack p. 3) |
| PM-02 | Fail / Block | $550/week conflicts with the plan's $3,698/month and the displayed $875/week budget; stages are unreconciled (Plan p. 45; Brief p. 8) |
| PM-03 | Cannot verify / Block | Obtain dates, account scope, restrictions and the $7,300-to-$7,531 bridge; unlike balances are not a proven 1% breach |
| PM-04 | Cannot verify / Block | Per-stream revenue, units and capacity schedules are missing; do not compare all revenue to an Intensive-only ceiling |
| PM-05 | Fail / Block | Base ramps differ: $3,000/$6,000/$9,000 versus $12,250.00/$12,470.54/$12,695.22 (Plan p. 44; Brief p. 6) |
| PM-06 | Cannot verify / Block | Intensive-only narrative versus six rows needs explicit membership flags and stream contributions; listing a row does not prove its revenue contribution |
| PM-07 | Fail / Review | Year 1 NPBT 57.1% versus 20-30% mature-business target has no period/basis explanation; do not force profit down to the target (Plan p. 40; Pack p. 10) |
| PM-08 | Fail / Review | Profit allocation has neither displayed cash treatment nor explicit exclusion; obtain the ledger before asserting actual payments are absent (Plan p. 40) |
| PM-09 | Fail / Block for $1,750; Review for unpriced items | No content-investment mapping in displayed schedules. Clarify event/visit/second-trademark timing and cost; preserve the existing indemnity and DFoG trademark lines |
| PM-10 | Fail / Review | Explain essential-cost/no-sales runway versus forecast cash runway; different values alone are not a contradiction (Plan p. 45; Pack p. 2) |
| PM-11 | Cannot verify / Block | No shared founder-time or unit conversion; 19 generic units is not proof of 19 delivery days |
| PM-12 | Cannot verify / Block for sources; Review for reserve gap | $6,652 funding provenance is missing; $232.73 is a reserve-target gap, not a demonstrated operating deficit or mandatory loan |
| MI-01 | Cannot verify / Block for full ledger; balance subchecks Pass | All 12 consecutive balances, total movement $60,020.56 and closing $67,551.56 reconcile. Complete dated movement ledger is required |
| MI-02 | Cannot verify / Block | $14,183 - $7,531 = $6,652 passes arithmetic; source identities, dates, restrictions and non-duplication are unverified |
| MI-03 | Cannot verify / Block at full precision; displayed-total subcheck Pass | Trace/model both round to $4,335. Obtain exact rates, staged expense schedules and cash dates; displayed rounding is not a proven error |
| MI-04 | Cannot verify / Block for full tax schedule; annual display subcheck Pass | Displayed annual tax is consistent with 28% after rounding. Full loss/taxable-profit policy and cash-tax schedule are not supplied |
| MI-05 | Fail / Review | Asset-like future vehicle/equipment items are operating costs; review classification without requiring Year 1 capex to include Year 3 purchases (Pack p. 7) |
| MI-06 | Fail / Block | Year 2 profitability remains claimed beyond the one-year model; acknowledge the brief's correct disclosure of later-cost exclusion |
| MI-07 | Cannot verify / Block for external issue | No April-October actuals reconciliation supplied; do not assert the production database has none |
| MI-08 | Cannot verify / Review | The unresolved Year 2 claim requires a Month 13 bridge; none is available. F1 does not prove historical finding erasure |
| SS-01 | Cannot verify / Block for full scenarios; no monotonicity failure | Full scenario inputs/ledgers are absent. The $610.40 revenue-downside cash improvement has a valid illustrative tax bridge and is not itself Fail |
| SS-02 | Cannot verify / Review | Shock parameters are not printed. Similar cash results do not prove inadequate stress; a 20%/10% reconstruction or current local code is not provenance for the exported run |
| SS-03 | Cannot verify / Block | Need per-scenario reserve calculations and source schedules; retain $232.73 static reserve gap and $0 displayed scenario cash deficits as distinct historical measures |
| DH-01 | Fail / Block on external transition; Not applicable to draft-only prompts during internal viewing | Advisor-entry prompts on Pack pp. 5-7 cannot be promoted unchanged into external output |
| DH-02 | Fail / Block | Update markers/competing versions on Plan pp. 11, 17, 43. No `[Extract; see full source.]` marker occurs in the supplied brief; draft banners are legitimate |
| DH-03 | Fail / Review | Duplicate Client Preparation & Onboarding headings and near-duplicate content on Plan pp. 36-37 |
| DH-04 | Fail / Block | Raw Markdown in values/profit allocation (Plan pp. 22, 40) |
| DH-05 | Fail / Review | "1 days" and inconsistent display conventions remain. The brief includes "$232.73"; no missing-symbol failure for that sentence |
| DH-06 | Fail / Autofix before rerender/recheck | Generated summary em dashes remain (Brief p. 2); preserve source quotations and audit transformations |
| DH-07 | Fail / Review | Two- or three-day Intensive in summary versus two- to four-day in updated plan (Brief p. 2; Plan p. 44) |
| NB-01 | Cannot verify / Block | Typed bindings are not supplied. Historical dates and market/paid-work numbers require their own sources, not invented model facts |
| NB-02 | Pass for comparable rendered financial metrics | Brief/pack totals and all 12 common monthly rows agree at common display precision; this does not prove shared source hashes or narrative provenance |
| EV-01 | Cannot verify / Block | No complete versioned claim inventory/classifier audit supplied; blanket founder-assertion note does not establish completeness |
| EV-02 | Fail / Block | Paid/completed work and registration claims lack linked proof or scoped unverified labels in the plan; the brief's general note is insufficient |
| EV-03 | Cannot verify / Review | Goals/risks contain real validation actions, but no complete sensitivity-ranked mapping to basis/test/date/fail response |
| EV-04 | Cannot verify / Review | No dependency/basis mapping to calculate unsupported revenue share; do not assume all revenue is unsupported or all assumptions untested |
| EV-05 | Fail / Block | No complete evidence index or cover counts printed |

F1 stays Draft. Keep the passing balance/rounding observations passing as defects are fixed; do not introduce false failures to reproduce the original blanket list. When source inputs become available, add an enriched, separately versioned fixture with explicit expected changes rather than silently changing what the PDF baseline proves.

### Other acceptance fixtures

| Fixture | Description | Expected result |
| --- | --- | --- |
| F2 | Separately approved reconstruction: current Intensive-only base/ramp, dated owner-pay steps, shared capacity, confirmed cash/funding, committed costs, profit allocations, supported horizon, reconciled elapsed actuals and classified evidence | Zero integrity Blocks/Cannot verify; Reviews resolved/accepted; independently computed results and one snapshot across all retained PDFs. Recalculate the Intensive-only margin; no predetermined cash dip or forced scenario ordering. Release-certified only after exact-artifact checks |
| F3 | Pre-trading startup seeking a facility; no elapsed trading months, documented pre-trading cash movements, future revenue assumptions and complete proposed debt terms | No Block merely for assumptions; missing validation produces EV-03/EV-04 Review under defined conditions. No history fabricated; dated sizing and scenario DSCR computed; founder-carrying page renders |
| F4 | Seasonal business with 14 required model months | MI-08 evaluates the explained Month 12-13 bridge; scenario ledgers reconcile without forcing cash ordering |
| F5 | Self-funded declaration with a verified uncovered worst-scenario cash deficit and no proposed external request | PM-12 fails and release blocks; SS-03 correctly reports the deficit and passes only if sizing/disclosure is correct. Naming a required amount is not proof of funding availability |
| F6 | Capex-heavy business with asset-like items initially classed as operating costs | MI-05 Review until accepted/corrected; resulting classification, depreciation and cash timing reconcile independently, including purchases beyond the initial year |
| F7 | Plan claims financial outcomes in Years 2 and 3 but the model horizon is empty | MI-06 returns Cannot verify and blocks release; it is never Pass or Not applicable |
| F8 | Generated narrative contains a wrong figure, wrong-period token or revenue token labelled as profit | NB-01 Fail even if the same digits occur elsewhere; reject/regenerate |
| F9 | Principal Advisor accepts an eligible risk: SS-02 has been configured as Block for an undersized but correctly calculated/disclosed stress | Exception logs the unchanged Fail result and appears in the appendix; release proceeds only if every integrity check passes. Companion attempt to override MI-01/MI-02 is denied |
| F10 | Source/model/evidence/rules/template changes after certification | New Draft version and stale dependencies; append supersession event; retained old records and file hashes stay unchanged |
| F11 | Complete named $6,652 funding inflow, zero customer collections, $4,335.07 fixed payments and $1,838.18 tax; opening cash $7,531 | MI-01/MI-02 Pass with net $478.75 and closing $8,009.75. Removing the source record yields Cannot verify, not an invented cash error |
| F12 | Fully specified paired scenarios reproduce base $8,009.75 and revenue-downside $8,620.15 with $610.40 lower tax and otherwise matched Month 1 payments/funding | SS-01 Pass with an explanation of the cash increase; SS-02 checks the declared 20% volume shock. All months/ledgers reconcile and profit differences are disclosed |
| F13 | $100 opening cash, $150 expenditure, no reserves/new financing; separately a positive-cash case below its reserve target and a funding draw after an earlier payment | Cash deficit is $50 without double subtraction. Reserve-only gap is separately disclosed/accepted and need not create a loan. Later draw cannot cure earlier negative cash |
| F14 | Startup with zero attachments, all claims classified and critical claims visibly unverified; no missing applicable cash/actuals data | Evidence rules allow release once other checks pass; legacy blanket attachment predicate is replaced. Claiming Verified without a reviewed supporting source is rejected |
| F15 | Concurrent source edit during render, failed renderer/fallback, retry, or download after release | Stale/unchecked artifacts never publish; valid retry is idempotent; downloads reproduce certified bytes and hash |
| F16 | Generated text includes trading since 2010, a sourced market statistic and a model forecast | NB-01 accepts the correctly typed/dated sources; neither historic dates nor market numbers are fabricated in the finance model. An unbound or misattributed claim fails |
| F17 | Captured required Month 13 finding, then horizon reduced to 12 months without revising the claim/scope | MI-06 blocks and MI-08 is Cannot verify; history remains. A genuinely 12-month scope with no longer claim is a separate Not applicable case, not deletion of prior findings |

When a fixture's expected result changes, the change must be reviewed by the Principal Advisor in the same change request.

## Constraints and instructions for the coding agent

**Before writing application code (mandatory first task).** Recheck the current repository and write a short mapping note before implementation. Updating this specification does not itself require starting a phase:

1. Where the existing reconciliation gate lives and how it is invoked
2. How plan answers, Budget Pack inputs and model outputs are stored (tables, models, JSON)
3. How the plan, pack and brief PDFs are generated
4. Where AI narrative is produced and which prompts exist
5. What Xero imports actually contain, which periods they cover and how a complete actuals ledger/reconciliation will be supplied
6. The proposed locations for the rule library, typed facts, claim records, immutable release snapshots and artifact store
7. Every draft/download/share route and fallback renderer that must use the same server-side release contract
8. The precise legacy predicates replaced by claim-level evidence policy and separate cash/reserve funding measures, with tests preserving all other integrity protections
9. The F1 extraction manifest and remaining unknowns; the separate founder/advisor decisions needed to establish F2's current inputs

Share the note with the Principal Advisor for confirmation before starting Phase 1.

**Stack and integrations.** Laravel and PHP backend, React and TSX frontend. Integrations include Stripe, Xero, ClamAV, OpenAI and Anthropic; verify their capabilities in the mapping rather than assuming an integration supplies every required ledger or evidence record.

**Non-negotiable constraints**

- Extend the existing assessment/reconciliation workflow and preserve integrity protections; implement only the explicitly documented policy replacements with regression coverage
- Rule logic, integrity floors and exception eligibility are code-enforced and tested; permitted configuration changes are authorised, versioned and audited
- Applicable missing inputs return Cannot verify and prevent progression at the rule's severity; retain that distinct result rather than relabelling it Fail or Pass. Not applicable requires a demonstrated scope reason
- Only eligible business-risk Blocks can receive a Principal Advisor exception; arithmetic, provenance, binding and release-identity Blocks cannot be overridden or downgraded
- AI cannot invent quantitative claims: use typed model, historical, evidence or founder-assumption tokens with semantic validation
- Cash deficit, reserve-target shortfall and proposed external request remain distinct; no double counting of cash/funding and no forced downside monotonicity
- All released documents share one frozen source version; check and retain the exact final files, including fallback artifacts, before certification
- Every rule ships with tests and is added to the fixture expectations
- Generated text uses hyphens, never em dashes
- Coverage: 80% general, 90% on the critical path (rule library, engine, fact binding, evidence classification, release log)
- No changes to Tania's stored plan/budget or original PDFs; use controlled copies or anonymised fixtures and keep unknown fields explicit

**Delivery approach**

- Deliver each phase in small reviewable pull requests where needed; do not bundle the entire engine, templates and evidence workflow into one change
- Each pull request states which rule IDs and fixtures it enables and shows the fixture results
- Open items in the Decisions section use the stated defaults until the Principal Advisor changes them; thresholds must remain configurable

**Definition of done for the programme:** F2 reaches Release-certified from agreed inputs with all three final PDFs checked and retained under one snapshot. F1 remains Draft with the corrected rule/subcheck outcomes, including passing balance/rounding assertions and explicit unknowns. F3-F17, independent recalculation, permissions, exact-artifact downloads, supersession and concurrency checks pass. No certified artifact is served by regenerating from mutable current data, and no accepted risk changes a model result or waives an integrity failure.
