# Payment schedules

WO-67 introduces the scheduling layer between proposal signature and actual
gateway charges. It deliberately does not charge cards or bank accounts; WO-69
will consume due schedules and create payment/receipt records.

## Schedule Creation

`ScheduleBuilder` creates `payment_schedules` from a signed proposal and an
active `payment_authority` captured during the WO-66 sign-off flow. The builder
checks that the authority belongs to the same proposal/client, is active, and has
not been revoked.

Supported WO-67 cadences:

- `one_off`
- `monthly_retainer`

Schedules are NZD only in WO-67. The amount may be supplied by the caller; when
omitted, the builder falls back to the proposal's `pv_summary.fee_suggested_mid`.

## Revocation Boundary

Authority revocation goes through `ScheduleBuilder::revokeAuthority()`. The
method marks the authority as `revoked`, sets `revoked_at`, then marks all
active or paused schedules for that authority as `revoked`. Both the authority
and each affected schedule write audit events.

This keeps the important payment distinction intact:

- revoked authority: no future scheduled charge should run
- failed charge: proposal remains `signed`; WO-69 records a failed payment and
  notifications/retry/failover, without reverting signature state

## Security

Schedules reference tokenised authorities only. Raw card or bank details remain
out of scope for this table and continue to be rejected at authority capture.
Client-scoped RLS applies to `payment_schedules`.
