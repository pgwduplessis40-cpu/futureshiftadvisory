# Payment schedules

WO-67 introduces the scheduling layer between proposal signature and actual
gateway charges. WO-68 fills the Stripe/Windcave gateway contract and failover
boundary. WO-69 consumes due schedules and creates payment/receipt records.

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

## Gateway Clients

`Gateway` charges through the configured primary gateway (`stripe` by default)
and fails over to the other gateway on a `PaymentGatewayException`. A successful
failover returns `PaymentChargeResult.failoverFrom`, which WO-69 will persist to
`payments.failover_from`.

Stripe and Windcave each have fake, live, and fallback clients:

- feature flag off: fallback delegates to fixture clients
- feature flag on: live clients call `ResilientHttp`
- missing credentials or failed live charge: the live client throws; it does not
  fabricate a paid state

When both gateways fail, `Gateway` writes a `payment_gateway.double_failure`
audit event and sends an urgent `payment.gateway.failure` notification to super
admins and client advisors.

## Webhooks

WO-68 adds signed webhook endpoints:

- `POST /api/webhooks/payments/stripe`
- `POST /api/webhooks/payments/windcave`

Stripe verification uses the `Stripe-Signature` `t=...`, `v1=...` HMAC shape.
Windcave verification uses `X-Windcave-Timestamp` and
`X-Windcave-Signature`. Both compare `timestamp.raw_body` HMACs and reject stale
timestamps. The endpoints audit receipt/rejection; persisted payment rows are
created by the scheduled processor.

## Processing and Receipts

`payments:process-scheduled` runs every five minutes with overlap protection.
It scans active `payment_schedules` where `next_run_at <= now()`.

For each due schedule, `PaymentProcessor` creates a `payments` attempt row in
`pending`, calls `Gateway`, then updates the attempt:

- success: `succeeded`, gateway reference, optional `failover_from`, receipt PDF
  on `secure_local`
- first failure: `retrying`, `failed_reason`, schedule `next_run_at` moved by
  `PAYMENT_RETRY_DELAY_MINUTES`
- final failure: `failed`, schedule paused

Successful one-off schedules are marked `completed`. Successful monthly
retainers advance `next_run_at` by month until it is in the future.

Failed payments send urgent `payment.failed` notifications to advisors on the
client team and the client primary contact. Gateway-level double failures also
send the WO-68 `payment.gateway.failure` notification.

Charge failures never mutate the proposal lifecycle. A proposal that is already
`signed` remains `signed` even when the first scheduled payment fails.

## Security

Schedules reference tokenised authorities only. Raw card or bank details remain
out of scope for this table and continue to be rejected at authority capture and
charge request time. Client-scoped RLS applies to `payment_schedules`,
`payments`, and `receipts`.
