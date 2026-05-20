# Phase 1 schema notes

This file records schema additions by work order. It is intentionally concise; migrations remain the source of truth.

## WO-04 - AI integrity foundation

### `learning_updates`

Governed queue for proposed AI behaviour changes. Phase 1 only writes detected candidates; no automatic implementation is allowed.

Key columns:

- `id` UUID primary key
- `layer_id` small integer identifying the future learning layer
- `source` JSONB with detector/prompt/source metadata
- `summary` human-readable candidate summary
- `proposed_change` JSONB
- `impact_scope` JSONB
- `clients_affected`
- `magnitude`
- `confidence`
- `evidence` JSONB
- `effective_date`
- `status` (`detected`, `staged`, `approved`, `rejected`, `deferred`, `implemented`, `rolled_back`)
- `decided_by_user_id`, `decided_at`
- `rollback_id`

### `learning_update_implementations`

Implementation and review ledger for an approved learning update. Created now so Phase 3 can add approval UI without changing the storage contract.

Key columns:

- `id` UUID primary key
- `learning_update_id`
- `implemented_at`
- `review_due`
- `review_outcome`
- `rolled_back_at`

## WO-05 - Integration resilience layer

### `integration_calls`

Per-attempt ledger for external integration calls. Every retry, success, failure, cached response, and fallback response is recorded with a shared correlation id for the logical call.

Key columns:

- `id` UUID primary key
- `service`
- `endpoint`
- `request_id`
- `status` (`success`, `retry`, `failure`, `cached`, `fallback`)
- `latency_ms`
- `attempt`
- `error_payload` JSONB
- `correlation_id`
- `occurred_at`

### `integration_health_samples`

Five-minute rollup table for WO-30 dashboard surfaces.

Key columns:

- `id` UUID primary key
- `service`
- `window_start`, `window_end`
- `success_rate`
- `p95_latency_ms`
- `health` (`green`, `amber`, `red`)
