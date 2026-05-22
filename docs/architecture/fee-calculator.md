# Fee calculator

WO-55 adds the Phase 2 fee-calculation ledger and deterministic calculator. It
stops at proposal-ready fee suggestions; payment collection and digital
acceptance remain Phase 3.

## Methods

`FeeCalculator` supports three methods:

- `hours_based`: sums per-service hours times rates. If requested, it adds a
  retainer conversion by dividing the total fee across the supplied month count.
- `outcome_based`: references the current client improvement PV, risk-cost PV,
  annual revenue, and complexity multiplier to produce a low/mid/high range.
  The persisted justification stores the exact PV and revenue figures used.
- `entrepreneur`: a distinct lower-entry path for early-stage founders. It uses
  simple stage-based ranges and does not depend on PV rows.

`roi_ratio` is always calculated as `improvement_pv_total / suggested_mid`,
matching the Phase 2 requirement that ROI is referenced to improvement PV rather
than total PV.

## Storage

`fee_calculations` stores method, inputs, suggested range, improvement PV total,
risk-cost PV total, ROI ratio, and structured justification. Future proposal
generation reads this table in WO-56.

## RLS

`fee_calculations` is client-scoped. PostgreSQL RLS allows `system`/`super_admin`
or rows whose `client_id` is present in `fsa_current_client_ids()`. The WO-55
tests include a non-bypass-role isolation assertion.
