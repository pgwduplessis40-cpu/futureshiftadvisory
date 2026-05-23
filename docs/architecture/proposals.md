# Fee proposals

WO-56 turns the WO-55 fee calculation into a branded, release-controlled
proposal artifact. WO-66 adds the client portal sign-off flow, tokenised
payment-authority capture, and signed proposal evidence. WO-67 adds payment
schedules for signed proposals; actual charging and receipts remain separate.

## Lifecycle

`ProposalStatus` now has a sign-off-managed lifecycle. Draft/release/recall,
expiry, and renewal remain advisor lifecycle actions. The signature states are
reachable only through `SignoffFlow`; direct model writes to those statuses are
blocked.

- `draft` after generation
- `released` after manual advisor release
- `recalled` when a released proposal is withdrawn
- `expired` when the release window elapses
- `renewed` when an expired proposal is cloned into a new version
- `awaiting_signature` after review, both consent steps, payment-method choice,
  and a successful tokenised authority capture
- `signed` after typed signature capture with a valid authority on file

Renewed proposals must be released again before they have a client-visible
window. The default release window is `PROPOSAL_EXPIRY_DAYS`, falling back to 30
days.

## Sign-Off Flow

WO-66 records seven ordered steps in `proposal_signoff_steps`: `review`,
`insurance_consent`, `coach_consent`, `payment_method`, `authority`,
`signature`, and `confirmation`.

The `authority` step calls `AuthorityCapture`, which delegates to the configured
gateway contract (`StripeClient` or `WindcaveClient`). Gateway fixtures return a
token, which is encrypted through `KeyEnvelope` and stored in
`payment_authorities.gateway_token_envelope`. Raw card numbers are rejected
before persistence.

The `signature` step renders signed evidence to the encrypted `secure_local`
disk and stores a `KeyEnvelope`-wrapped SHA-256 hash on the proposal. Signing
does not require a successful charge; charges and receipts begin in WO-69.

## Payment Schedules

`ScheduleBuilder` creates `payment_schedules` only when the proposal is already
`signed` and the selected `payment_authority` is active, belongs to the same
proposal, and has not been revoked. Schedules are NZD only for WO-67 and support
`one_off` and `monthly_retainer` cadence values.

Authority revocation is routed through the same builder so active or paused
schedules are marked `revoked` with `revoked_at` set, and both the authority and
schedule changes are audited. WO-67 does not process charges; WO-69 consumes due
schedules.

## Artifact Generation

`ProposalBuilder` owns proposal creation, consent rows, PDF rendering, release,
recall, expiry, and renewal. It reads the client PV waterfall, the selected
`fee_calculations` row, and consent elections for insurance and coach referral
paths. The generated PDF is stored on the encrypted `secure_local` disk under a
proposal-scoped path, with byte size and storage path recorded on the proposal
row.

The proposal HTML includes the Future Shift Advisory brand, scope, services,
fee range, PV summary, ROI ratio, referral consent elections, and an acceptance
section. Signed evidence is rendered separately when the client completes the
signature step.

## Expiry

`proposals:expire` expires released proposals whose `expires_at` is in the
past. The scheduler runs it daily at 00:20 with overlap protection. Expiry and
all manual lifecycle actions write audit events through `AuditWriter`.

## Advisor Surface

The client detail page exposes a thin advisor panel for generating proposals
from recent fee calculations and for manual release, recall, and renewal. Route
access uses the existing `proposals.release` permission while client visibility
continues through the client policy and RLS scope.
