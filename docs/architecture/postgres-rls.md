# Postgres row-level security

Per spec §4 ("row-level database security per client"), every client-scoped table on the Future Shift Advisory platform enforces isolation **at the database layer**, not only in application policies. This document describes the harness and how to add new client-scoped tables.

## How it works

1. **Per-request context.** On every authenticated request, the `EnforceClientScope` middleware (`app/Http/Middleware/EnforceClientScope.php`) calls the Postgres function `fsa_set_request_context(role, client_ids, user_id)` (installed by migration `0000_01_01_000010_install_rls_helpers.php` and extended by WO-14). That function sets three session-local variables: `fsa.role` (e.g. `super_admin`, `advisor`), `fsa.client_ids` (comma-separated UUID list), and `fsa.user_id` (the authenticated user id).
2. **RLS policies on each table.** Every client-scoped table has `ROW LEVEL SECURITY` enabled and a policy that consults those variables. Unauthenticated requests still go through the middleware (with an empty client list and `role='guest'`), so any RLS-protected query returns zero rows by default — no leak through forgotten context.
3. **Helper readers.** `fsa_current_role()`, `fsa_current_client_ids()`, and `fsa_current_user_id()` are convenience functions used by policies for legibility.

WO-14 applies context in two steps: first role plus user id, then the client ids resolved from `client_team`, then the final role/user/client context. That allows `client_team` itself to be RLS-protected while still letting the current user discover their own memberships.

## Adding a new client-scoped table

In the table's migration:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('client_id')->index();
            // ... other columns
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents ENABLE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY documents_client_scope ON documents
                    USING (
                        fsa_current_role() = 'super_admin'
                        OR client_id::text = ANY (fsa_current_client_ids())
                    )
                    WITH CHECK (
                        fsa_current_role() = 'super_admin'
                        OR client_id::text = ANY (fsa_current_client_ids())
                    );
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
```

Notes:

- **`USING` vs `WITH CHECK`.** `USING` controls which rows are visible for `SELECT`/`UPDATE`/`DELETE`; `WITH CHECK` controls which rows may be `INSERT`ed or updated *to*. Always set both so a client cannot be tricked into writing data attributed to another client.
- **Skip on non-Postgres.** Wrap RLS DDL in a driver check so the migration still applies cleanly if someone runs it against SQLite (which has no RLS). This protects local dev fallbacks; production is always Postgres.
- **`super_admin` bypass.** The platform allows super-admin access across all clients (e.g. for incident response). The policy above grants the bypass; the `EnforceClientScope` middleware sets the role from the user's primary Spatie role (WO-07).
- **Owner role.** The Postgres role used by the Laravel app must NOT be the table owner if you want RLS to apply to it (table owners bypass RLS by default). Either run the app as a non-owner role, or add `ALTER TABLE documents FORCE ROW LEVEL SECURITY;` after enabling RLS. Phase 1 production deployment will use a dedicated `fsa_app` role; in Herd dev the bundled `herd` role IS the owner, so add `FORCE ROW LEVEL SECURITY` to the policy DDL above to keep behaviour consistent across environments.

## Testing a new policy

`tests/Feature/Database/RlsHarnessTest.php` shows the harness pattern: create a temporary table, install a policy, seed rows for two synthetic clients, set context as user A, assert only A's rows are visible.

Use that test as the template for per-table tests. Every new RLS-protected table should ship with its own RLS test in the same WO PR — do not let an RLS-protected table land without a test that proves the policy actually denies cross-client reads.

## Phase 1 status

The harness (helper functions, middleware, demo test) lands in WO-02. Per-table policies arrive as their tables do, starting with `audit_events` (WO-03 — note: cross-tenant audit_events does not need client-scoped RLS; only enabled and restricted via the append-only trigger), then `clients` (WO-14), `documents` (WO-18), and the remaining client-scoped tables through Phase 1.

## What this does NOT replace

- **Laravel authorization (`Policy` classes).** RLS is a defence-in-depth layer. Resource authorization still lives in Laravel Policies so that 403s are returned at the HTTP layer rather than empty result sets being misinterpreted as "no data." The two layers must agree; if they disagree, that is a defect.
- **Per-action audit logging.** Every mutating action is also recorded in `audit_events` regardless of RLS outcome.
