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
