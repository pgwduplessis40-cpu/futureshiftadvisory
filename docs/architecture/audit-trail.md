# Immutable audit trail

Per spec §4 ("Immutable Audit Trail — every action by every user logged: timestamp, user, role, action, affected record, IP, device, before/after values") and PLAN.md §7.3.

## Schema

`audit_events` (Postgres, append-only). See migration `0000_01_01_000020_create_audit_events_table.php` for the full definition.

| Column | Type | Notes |
|---|---|---|
| id | uuid | `gen_random_uuid()` default |
| occurred_at | timestamptz | `now()` default |
| actor_user_id | uuid | null for system-triggered events |
| actor_role | text | `super_admin`, `advisor`, `client_primary`, `system`, … |
| client_id | uuid | tenant scope for the event |
| action | text | dot-delimited, e.g. `client.created`, `terms.accepted` |
| subject_type | text | morph type of the affected model |
| subject_id | text | morph id (string — uuid or int) |
| before | jsonb | redacted snapshot before mutation |
| after | jsonb | redacted snapshot after mutation |
| ip | inet (string) | recorded from the request |
| user_agent | text | recorded from the request |
| request_id | uuid | correlates events across one request |

Indexes: `occurred_at`, `(actor_user_id, occurred_at)`, `(client_id, occurred_at)`, `(subject_type, subject_id)`, `action`, `request_id`.

## Append-only enforcement

The migration installs:

- `fsa_audit_events_block_mutation()` — plpgsql function that raises `P0001`.
- `audit_events_no_update` — BEFORE UPDATE trigger calling the function.
- `audit_events_no_delete` — BEFORE DELETE trigger calling the function.
- `audit_events_no_truncate` — BEFORE TRUNCATE (statement-level) trigger.

`AuditEvent::save()` also raises in PHP if the row already exists, giving a clearer stack trace before the query reaches Postgres.

## Write path

All audit writes funnel through `App\Services\Audit\AuditWriter`. Convenience trait `App\Concerns\WritesAudit` lets controllers call `$this->audit($action, $subject, before: …, after: …)` without manually resolving the writer.

The writer pulls actor / role / IP / user-agent / request_id from the current `Request` and `Auth` context. Pass an explicit `actor` to override for system-triggered events.

## PII redaction

`App\Services\Audit\Redactor` walks every `before` / `after` value (recursively into arrays and strings) and replaces:

| Pattern | Placeholder |
|---|---|
| Email | `[email:<sha256-10>]` |
| NZ phone | `[phone:<sha256-10>]` |
| NZ bank account (BB-bbbb-AAAAAAA-SSS) | `[bank:<sha256-10>]` |
| IRD number | `[ird:<sha256-10>]` |

The placeholder hash is deterministic — two events referencing the same email get the same opaque token, so reviewers can correlate without seeing raw PII.

NZBN is **not** redacted. It is a public business identifier published in the NZBN Register, and redacting it would harm audit utility for no privacy gain.

## Read-tracking

`App\Http\Middleware\LogAuditEvent` (alias `audit.read`) records a `route.viewed`-style event for any route it is attached to, but only on successful (2xx) responses. Use it on sensitive read endpoints (document downloads, T&C views, audit-log views, signed-PDF previews):

```php
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
    ->middleware(['auth', 'audit.read:document.downloaded']);
```

## Chain verification

`php artisan fsa:audit:verify` runs daily at 02:30 (registered in `bootstrap/app.php`). Phase 1 checks:

1. No `occurred_at` is in the future (clock skew or tampering).
2. No `request_id` spans more than 24 hours of events.
3. The most recent event is not older than 7 days (silent platform == suspicious).

Failures exit non-zero and print findings. WO-12 provides the notification resolver and digest primitive; a future command-specific notification can route audit-chain failures through it.

## What this does NOT cover (Phase 1)

- **Hash chaining of rows.** A cryptographic chain (each row hashes the prior one) raises the bar for undetected tampering. Phase 2+ extension; the current append-only trigger plus daily verification is the Phase 1 bar.
- **Backup verification.** Backup integrity is an operational concern handled by the backup tool.
- **Audit-log UI.** Browsing audit events is Phase 2; for now use `php artisan tinker` or direct SQL with super-admin credentials.
