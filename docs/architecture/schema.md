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

## WO-06 - Secure file storage + virus scanning

### `documents`

Ledger for sensitive uploads. File bytes are stored on the `secure_local` encrypted disk; this table stores metadata, scan state, and future linkage to clients, entrepreneur profiles, verification rows, and upload owners.

Key columns:

- `id` UUID primary key
- `client_id`
- `entrepreneur_profile_id`
- `category` (`financial_statement`, `contract`, `insurance_certificate`, `hr_record`, `compliance_doc`, `plan_attachment`, `dd_artifact`, `other`)
- `original_filename`
- `stored_path`
- `byte_size`
- `mime_type`
- `sha256`
- `uploaded_by_user_id`
- `scanner_result` (`pending`, `clean`, `infected`, `error`)
- `scanner_payload` JSONB
- `expires_at`

Scanner errors are persisted as quarantined documents with `scanner_result=error`; client-visible queries must use the model's `visibleToClients` scope.

## WO-07 - User roles, permissions, RBAC

### `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

Spatie `laravel-permission` tables back the Phase 1 RBAC matrix. `roles.name` values match the nine `users.user_type` values; permission names are defined in `app/Enums/Permission.php` and seeded by `PermissionSeeder`/`RoleSeeder`.

Key rules:

- The executable role matrix is `RoleSeeder::rolePermissions()`.
- `User::fsaRole()` resolves the first assigned Spatie role for RLS request context and falls back to `primary_role` only when no Spatie role has been assigned.
- `dd_guest` is a token type only for future DD upload links; it is not a Spatie role and must not be stored as `users.user_type`.

## WO-08 - Invite-only registration + MFA enforcement

### `users` additions

Identity and MFA metadata used by invite-only auth, MFA enforcement, RBAC fallback role resolution, and WO-09 session timeout policy.

Key columns:

- `user_type` (`super_admin`, `advisor`, `junior_advisor`, `entrepreneur_mentor`, `client_primary`, `client_team`, `entrepreneur`, `broker`, `coach`)
- `primary_role`
- `mfa_enabled_at`
- `mfa_method`
- `last_password_set_at`
- `session_timeout_minutes`
- `suspended_at`
- `suspended_reason`

### `invite_tokens`

One-shot account invitation records. Only the SHA-256 token hash is stored.

Key columns:

- `id` UUID primary key
- `email`
- `target_role`
- `target_user_type`
- `token_hash`
- `expires_at`
- `accepted_at`
- `issued_by_user_id`
- `accepted_by_user_id`

### `mfa_factors`

MFA factor ledger synced from Fortify's TOTP state.

Key columns:

- `id` UUID primary key
- `user_id`
- `type` (`totp`)
- `label`
- `secret_envelope`
- `recovery_codes_envelope`
- `confirmed_at`
- `last_used_at`

## WO-09 - Session management + step-up MFA

### `sessions` additions

The Laravel database session table carries lightweight security observability for the WO-09 middleware.

Key columns:

- `risk_score` latest calculated request risk score for the session
- `step_up_at` timestamp when the session was last forced into step-up MFA

The enforcement source of truth remains the server-side session store: `EnforceSessionSecurity` keeps last activity and device-signal markers in session data so the same logic works with the array session driver in tests.

## WO-10 - Terms model + version control

### `terms_versions`

Version header for the platform terms contract. Published versions are immutable and remain readable forever.

Key columns:

- `id` UUID primary key
- `version`
- `title`
- `material`
- `published_at`
- `published_by_user_id`
- `notice_period_days`
- `reviewer_reference`
- `pdf_path`
- `created_by_user_id`

### `terms_clauses`

The 14-clause body for each terms version. Clauses are copied forward into drafts so historical text remains intact.

Key columns:

- `id` UUID primary key
- `terms_version_id`
- `clause_number`
- `title`
- `body`
- `material`

### `terms_acceptances`

Acceptance ledger. WO-10 uses it to expire active acceptances when a material version is published; WO-11 writes accepted/declined gate outcomes and signed-PDF evidence.

Key columns:

- `id` UUID primary key
- `user_id`
- `terms_version_id`
- `accepted_at`
- `declined_at`
- `expires_at`
- `reacceptance_notice_queued_at`
- `signed_pdf_path`
- `signed_pdf_sha256_envelope`
- `signed_pdf_envelope_meta`
- `signed_pdf_byte_size`
- `ip`
- `user_agent`
