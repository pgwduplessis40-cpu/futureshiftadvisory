<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalHealth;

use App\Models\Client;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OperationalHealthFixtureRlsTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_operational_health_rls_app';

    private bool $usingTestRole = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Operational health fixture RLS test requires Postgres.');
        }

        $this->seed(RoleSeeder::class);

        if ($this->currentRoleBypassesRls()) {
            $this->createNonBypassRole();
            DB::statement('SET ROLE '.self::RLS_APP_ROLE);
            $this->usingTestRole = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->usingTestRole) {
            DB::statement('RESET ROLE');
            DB::statement('DROP OWNED BY '.self::RLS_APP_ROLE);
            DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
        }

        parent::tearDown();
    }

    public function test_fixture_command_uses_system_context_under_enforced_rls(): void
    {
        Storage::fake('secure_local');

        $context = app(RequestContext::class);
        $context->apply('advisor', []);

        $this->artisan('fsa:seed-operational-health-fixtures')
            ->assertSuccessful();

        $this->assertSame('advisor', DB::selectOne('SELECT fsa_current_role() AS role')->role);
        $this->assertSame(2, $context->withSystemContext(
            fn (): int => Client::query()
                ->where('registry_sources->source', 'operational_health_fixture')
                ->count(),
        ));
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %1$s;
            GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }
}
