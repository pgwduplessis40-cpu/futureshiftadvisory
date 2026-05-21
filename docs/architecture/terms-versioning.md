# WO-10 terms versioning

WO-10 introduces the editable and publishable terms contract used by the WO-11 acceptance gate.

## Storage

- `terms_versions` is the immutable version header once published.
- `terms_clauses` stores the 14 clauses for each version.
- `terms_acceptances` is created here because material republishing needs to expire active acceptances; WO-11 extends the table with signed-PDF evidence columns.

`TermsVersionSeeder` reads `docs/legal/terms-v1.md`, imports exactly 14 clauses, and marks clauses 1, 5, 6, 10, and 12 as material by default.

## Publishing

Draft versions can be edited in the admin terms UI. Publishing sets `published_at`, `published_by_user_id`, `material`, `notice_period_days`, and the reviewer reference.

Published versions are treated as immutable. Prior versions remain readable forever through the preview route.

When a material version is published, active acceptances for the prior published version are updated:

- `expires_at = published_at + notice_period_days`
- `reacceptance_notice_queued_at = published_at`

This timestamp is the Phase 1 notification queue seam. WO-12 will move user-facing notification delivery into the central notification centre.

Non-material publishing writes the immutable audit event only and does not touch existing acceptances.

## Authorization

Admin routes are already behind `role:super_admin`. The `TermsVersionPolicy::publish` method also requires `user_type = super_admin`, so publishing is not granted by permission drift alone.
