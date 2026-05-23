# Due diligence

WO-75 starts the DD track with onboarding, target isolation, the DD-specific
questionnaire, and the standard liability disclaimer. WO-76 adds the DD virtual
data room and tokenised guest upload. Later WOs add workstreams, valuation, plan
builder, report, and post-acquisition conversion.

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
