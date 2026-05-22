# Fee proposals

WO-56 turns the WO-55 fee calculation into a branded, release-controlled
proposal artifact. It remains Phase 2 only: no payment collection, digital
signature workflow, or client acceptance transition is reachable yet.

## Lifecycle

`ProposalStatus` defines the full forward-compatible state set, including
`awaiting_signature` and `signed`, but Phase 2 guards those states as reserved.
Reachable Phase 2 transitions are:

- `draft` after generation
- `released` after manual advisor release
- `recalled` when a released proposal is withdrawn
- `expired` when the release window elapses
- `renewed` when an expired proposal is cloned into a new version

Renewed proposals must be released again before they have a client-visible
window. The default release window is `PROPOSAL_EXPIRY_DAYS`, falling back to 30
days.

## Artifact Generation

`ProposalBuilder` owns proposal creation, consent rows, PDF rendering, release,
recall, expiry, and renewal. It reads the client PV waterfall, the selected
`fee_calculations` row, and consent elections for insurance and coach referral
paths. The generated PDF is stored on the encrypted `secure_local` disk under a
proposal-scoped path, with byte size and storage path recorded on the proposal
row.

The proposal HTML includes the Future Shift Advisory brand, scope, services,
fee range, PV summary, ROI ratio, referral consent elections, and an acceptance
section that explicitly marks signature and payment as Phase 3.

## Expiry

`proposals:expire` expires released proposals whose `expires_at` is in the
past. The scheduler runs it daily at 00:20 with overlap protection. Expiry and
all manual lifecycle actions write audit events through `AuditWriter`.

## Advisor Surface

The client detail page exposes a thin advisor panel for generating proposals
from recent fee calculations and for manual release, recall, and renewal. Route
access uses the existing `proposals.release` permission while client visibility
continues through the client policy and RLS scope.
