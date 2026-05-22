# Succession planning

WO-54 adds deterministic succession planning over the Phase 2 analysis spine.

## Flow

`SuccessionPlanner` creates an `analysis_runs` row with module `succession`,
uses the shared data-quality scorer and document-verification gate, and writes a
client-scoped `succession_plans` row only when the run is not blocked.

The plan records:

- a weighted exit-readiness score from owner readiness, management depth,
  process documentation, financial readiness, and timeline clarity;
- assessed exit options such as trade sale, management buyout, or internal
  succession;
- an owner-dependency reduction plan;
- a target exit PV calculation routed through `PvEngine`;
- whether owner readiness is the primary constraint.

Target exit PV uses the shared PV ledger with type `business_valuation`; it does
not create a `business_valuations` row because this is a target exit scenario,
not the current reconciled valuation used by the WO-43 waterfall.

## Coaching signal boundary

When owner readiness is the primary constraint, WO-54 writes a raw
`coaching_signals` row with signal type `owner_readiness_primary_constraint`.
The evidence is stamped with `raw_observation_only=true` and
`auto_referral=false`. No Phase 2 path consumes the signal, creates a referral,
notifies a coach, or calibrates a threshold.

## RLS

`succession_plans` is client-scoped. PostgreSQL RLS allows `system`/`super_admin`
or rows whose `client_id` is present in `fsa_current_client_ids()`. Tests verify
cross-client isolation under a non-bypass role when local Postgres would
otherwise bypass RLS.
