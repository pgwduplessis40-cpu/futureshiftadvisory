# Due diligence

WO-75 starts the DD track with onboarding, target isolation, the DD-specific
questionnaire, and the standard liability disclaimer. WO-76 adds the DD virtual
data room and tokenised guest upload. WO-77 runs the eight DD workstreams on the
analysis spine. WO-78 adds DD valuation and FX normalisation. WO-79 adds the DD
business-plan builder. WO-80 adds the DD report. WO-81 adds post-acquisition
conversion.

## Onboarding

`DdOnboarding::start()` creates a `dd_engagements` row only when:

- the buyer client uses the `due_diligence` engagement type
- a fresh `ConflictDeclarer::DUE_DILIGENCE` declaration exists for the same
  advisor and client
- the DD-specific questionnaire is published

The target business is stored on `dd_engagements.target_details`, not on the
buyer `clients` row. The advisor client detail page receives a
`due_diligence` payload and renders an acquisition-target panel so buyer data
and target data stay visually separate.

## Questionnaire

`DdSpecificQuestionnaireSeeder` publishes version 1 of the `dd_specific`
questionnaire. DD clients use that set only during onboarding; standard advisory
is deferred until the post-acquisition gap flow.

## Disclaimer

Every DD engagement records acknowledgement of the standard disclaimer:
FSA support is advisory only and is not legal, tax, accounting, investment, or
acquisition advice. A qualified New Zealand lawyer and accountant must be
engaged before any acquisition decision, and FSA accepts no liability for
acquisition decisions made from platform DD outputs.

## Data Room

`DataRoom` stores DD artifacts separately from the standard document filing
surface:

- every upload still goes through `SecureFileWriter`
- every persisted document is categorised as `dd_artifact`
- `dd_data_room_items` attaches the document to a DD engagement, workstream, and
  folder
- guest-upload rows use `source = guest_upload` and retain the issuing
  `dd_guest_link_id`

The eight WO-77 workstream folders are available from WO-76 onward: Financial,
Valuation, Legal, Tax, Commercial / Market, Operational, HR / People, and NZ
Regulatory.

## Guest Upload

Guest links are token-only and upload-only. `dd_guest_links` stores only a
SHA-256 token hash, expiry, revocation state, upload count, and the fixed
workstream/folder scope. The public API route accepts `POST` uploads only; there
is no guest route for listing or reading data room contents.

On upload, the service resolves the token, rejects expired/revoked/maxed links
before scanning, runs virus scanning before persistence, and writes
`dd.guest_upload_received` or `dd.guest_upload_rejected` audit events. Revoking a
link sets `revoked_at` immediately, so later uploads fail before the scanner is
called.

## Workstreams

`DdWorkstreamRunner` runs the eight DD workstreams through `AnalysisRunner` using
the `dd_workstream` module value:

- Financial
- Valuation
- Legal
- Tax
- Commercial / Market
- Operational
- HR / People
- NZ Regulatory

Each workstream reads only its own `dd_data_room_items` evidence. Verified
documents are double-weighted (`2`) while clean, unverified documents count as
`1`. An unresolved accuracy discrepancy in a workstream pauses that workstream
before an analysis run is created. The DD runner intentionally uses a scoped
document/data-quality gate so a discrepancy in Legal, for example, does not
block Financial.

NZ-specific checks are attached to relevant workstreams:

- Legal / NZ Regulatory: PPSR, LINZ, and IPONZ fixtures/contracts
- Tax / NZ Regulatory: IRD GST status
- HR / People / NZ Regulatory: Holidays Act liability scaffold
- Commercial / Market, Operational, NZ Regulatory: owner-dependency score

## Valuation

`App\Services\Dd\Valuation` is a thin DD adapter over the Phase 2
`BusinessValuation`/`PvEngine` stack. It forces target financial inputs from the
DD engagement or explicit data-room evidence so buyer accounting snapshots do not
leak into the acquisition target valuation.

The adapter stores a `dd_valuations` row linking:

- the DD engagement
- the underlying `business_valuations` row
- the reused `pv_calculations` DCF/PV row
- FX normalisation metadata
- buyer negotiating position

`FxNormaliser` converts source-currency valuations to NZD using the latest RBNZ
`exchange_rates` row (`NZD/{currency}`), records the fetched timestamp, and
stores +/-10% sensitivity around the source-to-NZD rate. Native NZD valuations
do not require an exchange-rate row.

## Plan Builder

WO-79 pulls the shared five-phase business-plan engine forward so DD can use it
before the entrepreneur builder UI lands. The shared engine owns
`business_plans`, `plan_phases`, and `plan_sections`; the DD adapter stays thin
and only maps DD evidence into that engine.

`App\Services\Dd\PlanBuilder` creates or updates one DD-owned plan per
engagement, linked by `business_plans.dd_engagement_id` and the buyer
`client_id`. The plan tables also carry nullable `entrepreneur_profile_id` and a
database owner XOR check so a future entrepreneur plan and a DD plan use the same
storage contract without ambiguous ownership.

The DD adapter auto-populates sections from:

- the acquisition target and target details into Foundation
- completed workstream findings into Market, Strategy, Legal & Operations, or
  Financial according to the workstream type
- the latest DD valuation into Financial when present
- a strategy-integration summary once any workstreams are complete

Marking an engagement as `acquisition_proceeding` first rebuilds the plan, then
checks that every phase has at least one complete section. Incomplete plans stay
as drafts for advisor completion. Complete plans are marked `founding`, store a
`founding_advisory_payload`, and move the DD engagement to
`acquisition_proceeding`; WO-81 consumes that payload for the new advisory
profile.

## Report

`ReportComposer::composeDueDiligence()` generates a dedicated
`ReportType::DueDiligence` report for a specific `DdEngagement`. It uses the
existing report storage, PDF renderer, and PowerPoint generator, but takes the DD
engagement as input so multiple DD engagements for one buyer are not conflated.

The report creates these sections:

- executive summary
- valuation, including SDE, EBITDA, DCF/PV, FX, and buyer position
- workstream findings
- PV-ranked risk register
- price-adjustment schedule
- 100-day integration plan
- buyer readiness
- Proceed / Renegotiate / Abandon recommendation
- liability disclaimer

WO-80 adds `dd_risk_register` and `dd_integration_plans`. The risk register is
rebuilt from completed DD workstream findings and uses the shared risk-cost PV
engine to rank risks by present value of cost. Severity maps to
`deal_killer`, `major`, `minor`, or `informational`; deal-killer risks drive an
`abandon` recommendation, while major risks or a valuation walk-away signal drive
`renegotiate`. The price-adjustment schedule is indicative only and stays inside
the DD report with the standard legal/accounting review disclaimer.

The 100-day integration plan is rebuilt from the ranked risk register and always
includes a day-100 review action. Both new DD report tables are buyer-client
scoped by RLS.

## Post-Acquisition Conversion

`App\Services\Dd\PostAcquisition` converts a DD engagement only after it has
been marked `acquisition_proceeding`. The service is idempotent for a DD
engagement and writes a `post_acquisition_migrations` handoff row linking:

- the DD engagement
- the buyer client
- the new post-acquisition advisory client
- the founding DD business plan, when present
- the DD report
- the post-acquisition gap questionnaire response
- the generated proposal
- migrated DD document IDs
- the DD PV baseline

The acquired business becomes its own `post_acquisition_advisory` client. Buyer
team membership is copied with post-acquisition module access, and the acquired
client stores `registry_sources.source_label = Sourced from DD`.

DD data-room documents are copied into new `documents` rows for the advisory
client. The migrated rows use a visible filename prefix (`Sourced from DD - ...`)
and retain the source DD document id, source stored path, DD engagement id, and
workstream in `scanner_payload`.

WO-81 seeds a `post_acquisition_gap` questionnaire. Conversion creates an
unsubmitted response for the new advisory client and pre-fills only DD-known
questions: acquired-business details, inherited DD risks, and migrated document
set. Remaining question ids are stored on the handoff metadata so the client
only completes the gaps.

The auto-generated proposal is a draft proposal for the new advisory client. Its
outcome-based fee calculation stores `source = due_diligence`, the DD report id,
the DD PV baseline from the latest DD valuation midpoint, and the PV-ranked DD
risk total. This keeps the proposal unusually precise without treating FSA as a
legal, tax, accounting, lending, or investment adviser.
