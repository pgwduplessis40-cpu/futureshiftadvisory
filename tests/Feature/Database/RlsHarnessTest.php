<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proves end-to-end that the RLS harness installed by WO-02 actually
 * isolates rows when applied to a real table.
 *
 * The test creates a temporary `rls_smoke_documents` table, enables RLS,
 * installs a policy keyed on `fsa_current_role()` and `fsa_current_client_ids()`,
 * seeds rows for two synthetic clients, switches the per-request context,
 * and asserts that visibility tracks the context exactly.
 *
 * Skipped automatically when the test database is not Postgres - SQLite has
 * no row-level security. Per docs/dev-setup.md, the canonical test database
 * (futureshift_test) is Postgres.
 */
final class RlsHarnessTest extends TestCase
{
    use RefreshDatabase;

    private string $clientA;

    private string $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS harness requires Postgres (see docs/dev-setup.md).');
        }

        DB::statement(<<<'SQL'
            CREATE TABLE rls_smoke_documents (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                client_id uuid NOT NULL,
                label text NOT NULL
            );

            ALTER TABLE rls_smoke_documents ENABLE ROW LEVEL SECURITY;
            ALTER TABLE rls_smoke_documents FORCE ROW LEVEL SECURITY;

            CREATE POLICY rls_smoke_documents_scope ON rls_smoke_documents
                USING (
                    fsa_current_role() = 'super_admin'
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() = 'super_admin'
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
        SQL);

        $this->clientA = (string) Str::uuid();
        $this->clientB = (string) Str::uuid();

        // Seed under super_admin so RLS does not refuse the inserts.
        app(RequestContext::class)->apply(RequestContext::ROLE_SUPER_ADMIN, []);
        DB::table('rls_smoke_documents')->insert([
            ['client_id' => $this->clientA, 'label' => 'A-doc-1'],
            ['client_id' => $this->clientA, 'label' => 'A-doc-2'],
            ['client_id' => $this->clientB, 'label' => 'B-doc-1'],
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS rls_smoke_documents');
        }

        parent::tearDown();
    }

    public function test_a_user_scoped_to_client_a_sees_only_client_a_rows(): void
    {
        app(RequestContext::class)->apply('advisor', [$this->clientA]);

        $labels = DB::table('rls_smoke_documents')->pluck('label')->all();

        sort($labels);
        $this->assertSame(['A-doc-1', 'A-doc-2'], $labels);
    }

    public function test_a_user_with_no_client_scope_sees_no_rows(): void
    {
        app(RequestContext::class)->apply('advisor', []);

        $this->assertSame(0, DB::table('rls_smoke_documents')->count());
    }

    public function test_a_user_scoped_to_both_clients_sees_all_rows(): void
    {
        app(RequestContext::class)->apply('advisor', [$this->clientA, $this->clientB]);

        $this->assertSame(3, DB::table('rls_smoke_documents')->count());
    }

    public function test_super_admin_bypasses_scope(): void
    {
        app(RequestContext::class)->apply(RequestContext::ROLE_SUPER_ADMIN, []);

        $this->assertSame(3, DB::table('rls_smoke_documents')->count());
    }

    public function test_a_user_cannot_insert_a_row_for_a_client_they_do_not_own(): void
    {
        app(RequestContext::class)->apply('advisor', [$this->clientA]);

        $this->expectException(QueryException::class);

        DB::table('rls_smoke_documents')->insert([
            'client_id' => $this->clientB,
            'label' => 'attempted-cross-client-write',
        ]);
    }

    public function test_helper_functions_default_to_empty_when_context_unset(): void
    {
        // Explicitly reset context to simulate an unauthenticated request that
        // somehow reached an RLS-protected query.
        app(RequestContext::class)->apply(RequestContext::ROLE_GUEST, []);

        $this->assertSame(0, DB::table('rls_smoke_documents')->count());
    }
}
