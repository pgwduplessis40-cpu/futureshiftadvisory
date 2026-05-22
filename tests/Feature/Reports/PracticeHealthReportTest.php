<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Console\Commands\CreatePracticeHealthSnapshots;
use App\Enums\ClientStatus;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\AccountingConnection;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\ImprovementOpportunity;
use App\Models\PracticeHealthSnapshot;
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\RedFlag;
use App\Models\Report;
use App\Models\RiskCost;
use App\Models\User;
use App\Services\Reports\PracticeHealthReport;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PracticeHealthReportTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_practice_health_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON practice_health_snapshots FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_portfolio_report_aggregates_active_clients_only(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('practice-health@example.test', 'Active Portfolio Limited');
        $inactive = $this->client('Paused Portfolio Limited', status: ClientStatus::PAUSED);
        ClientTeamMember::query()->create([
            'client_id' => $inactive->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $this->pvFixture($client, current: 100000, improvement: 25000, risk: 10000);
        $this->financialSnapshot($client, revenue: 500000);
        $this->releasedProposal($client);
        $this->storedReport($client);
        $this->redFlag($client);

        $this->pvFixture($inactive, current: 900000, improvement: 500000, risk: 250000);
        $this->financialSnapshot($inactive, revenue: 7000000);

        $payload = app(PracticeHealthReport::class)->forUser($advisor);

        $this->assertSame(1, $payload['summary']['active_clients']);
        $this->assertSame(100000.0, $payload['summary']['current_pv']);
        $this->assertSame(25000.0, $payload['summary']['improvement_pv']);
        $this->assertSame(10000.0, $payload['summary']['risk_mitigation_pv']);
        $this->assertSame(135000.0, $payload['summary']['target_pv']);
        $this->assertSame(500000.0, $payload['summary']['revenue_under_management']);
        $this->assertSame(1, $payload['phase_two']['released_proposals']);
        $this->assertSame(1, $payload['phase_two']['generated_reports']);
        $this->assertSame(1, $payload['phase_two']['open_red_flags']);
        $this->assertSame($client->id, $payload['clients'][0]['client_id']);
    }

    public function test_report_scope_matches_advisor_portfolio_and_super_admin_practice(): void
    {
        [$advisorA, $clientA] = $this->clientWithAdvisor('advisor-a-practice@example.test', 'Advisor A Limited');
        [, $clientB] = $this->clientWithAdvisor('advisor-b-practice@example.test', 'Advisor B Limited');
        $superAdmin = User::factory()->withTwoFactor()->superAdmin()->create([
            'email' => 'super-practice@example.test',
        ]);

        $this->pvFixture($clientA, current: 100000, improvement: 25000, risk: 10000);
        $this->pvFixture($clientB, current: 200000, improvement: 50000, risk: 30000);

        $reports = app(PracticeHealthReport::class);
        $advisorPayload = $reports->forUser($advisorA);
        $superPayload = $reports->forUser($superAdmin);

        $this->assertSame(1, $advisorPayload['summary']['active_clients']);
        $this->assertSame(135000.0, $advisorPayload['summary']['target_pv']);
        $this->assertSame([$clientA->id], collect($advisorPayload['clients'])->pluck('client_id')->all());

        $this->assertSame(2, $superPayload['summary']['active_clients']);
        $this->assertSame(415000.0, $superPayload['summary']['target_pv']);
    }

    public function test_snapshots_cache_monthly_practice_and_advisor_metrics(): void
    {
        [, $client] = $this->clientWithAdvisor('snapshot-advisor@example.test', 'Snapshot Client Limited');

        $this->pvFixture($client, current: 120000, improvement: 30000, risk: 5000);
        $this->financialSnapshot($client, revenue: 650000);

        $this->artisan(CreatePracticeHealthSnapshots::class, [
            '--all-advisors' => true,
        ])->assertSuccessful();

        $this->assertSame(2, PracticeHealthSnapshot::query()->count());
        $advisorSnapshot = PracticeHealthSnapshot::query()->where('scope', 'advisor')->firstOrFail();
        $practiceSnapshot = PracticeHealthSnapshot::query()->where('scope', 'super_admin')->firstOrFail();

        $this->assertSame(155000.0, (float) data_get($advisorSnapshot->metrics, 'summary.target_pv'));
        $this->assertSame(650000.0, (float) data_get($practiceSnapshot->metrics, 'summary.revenue_under_management'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'practice_health.snapshot_created',
            'subject_id' => $advisorSnapshot->id,
        ]);
    }

    public function test_advisor_dashboard_receives_practice_health_payload(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('dashboard-practice@example.test', 'Dashboard Practice Limited');
        $this->pvFixture($client, current: 100000, improvement: 25000, risk: 10000);
        $this->financialSnapshot($client, revenue: 500000);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('practiceHealth.summary.active_clients', 1)
                ->where('practiceHealth.summary.target_pv', 135000)
                ->where('practiceHealth.summary.revenue_under_management', 500000)
                ->where('practiceHealth.clients.0.client_id', $client->id));
    }

    public function test_practice_health_snapshots_are_isolated_by_advisor_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Practice health RLS assertions require Postgres.');
        }

        $advisorA = $this->advisor('rls-practice-a@example.test');
        $advisorB = $this->advisor('rls-practice-b@example.test');
        $snapshotA = $this->snapshotFor($advisorA);
        $snapshotB = $this->snapshotFor($advisorB);

        app(RequestContext::class)->apply('advisor', [], (string) $advisorA->getKey());

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('practice_health_snapshots')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($snapshotA->id, $visibleIds);
        $this->assertNotContains($snapshotB->id, $visibleIds);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $email, string $clientName): array
    {
        $advisor = $this->advisor($email);
        $client = $this->client($clientName, $advisor);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function client(string $name, ?User $createdBy = null, ClientStatus $status = ClientStatus::ACTIVE): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'status' => $status,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    private function pvFixture(Client $client, float $current, float $improvement, float $risk): void
    {
        BusinessValuation::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::BusinessValuation, $current)->getKey(),
            'sde_value' => ['low' => $current * 0.9, 'mid' => $current, 'high' => $current * 1.1],
            'ebitda_value' => ['low' => $current * 0.9, 'mid' => $current, 'high' => $current * 1.1],
            'dcf_value' => ['low' => $current * 0.9, 'mid' => $current, 'high' => $current * 1.1],
            'reconciled_low' => $current * 0.9,
            'reconciled_mid' => $current,
            'reconciled_high' => $current * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'Practice health PV fixture', 'source_reference' => 'test:practice-health-valuation'],
            ],
            'as_at' => now(),
        ]);

        ImprovementOpportunity::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::ImprovementOpportunity, $improvement)->getKey(),
            'title' => 'Process improvement',
            'annual_benefit' => $improvement,
            'duration_years' => 1,
            'pv_of_impact' => $improvement,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Practice health improvement fixture', 'source_reference' => 'test:practice-health-improvement'],
            ],
        ]);

        RiskCost::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::RiskCost, $risk)->getKey(),
            'title' => 'Risk mitigation',
            'financial_impact' => $risk,
            'probability' => 1,
            'duration_years' => 1,
            'applied_impact' => $risk,
            'annual_expected_cost' => $risk,
            'pv_of_cost' => $risk,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Practice health risk fixture', 'source_reference' => 'test:practice-health-risk'],
            ],
        ]);
    }

    private function pvCalculation(Client $client, PvType $type, float $presentValue): PvCalculation
    {
        return PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => $type,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Practice health fixture rate.',
            'inputs' => ['cash_flows' => []],
            'result' => ['present_value' => $presentValue],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture PV', 'source_reference' => 'test:practice-health-pv'],
            ],
        ]);
    }

    private function financialSnapshot(Client $client, float $revenue): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'practice-health-'.$client->getKey(),
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'fixture',
            'token_envelope_meta' => ['fixture' => true],
            'scopes' => ['accounting.reports.read'],
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'source' => 'fixture',
            'source_badge' => 'fixture',
            'degraded' => false,
            'profit_and_loss' => ['revenue' => $revenue],
            'balance_sheet' => [],
            'cash_flow' => [],
            'metrics' => [],
            'pulled_at' => now(),
        ]);
    }

    private function releasedProposal(Client $client): Proposal
    {
        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 10000,
            'roi_ratio' => 2.5,
            'justification' => ['fixture' => true],
        ]);

        return Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['summary' => 'Practice health fixture.'],
            'services' => [['name' => 'Advisory']],
            'pv_summary' => ['target_pv' => 135000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['phase' => 'phase_2_release_only'],
            'released_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function storedReport(Client $client): Report
    {
        return Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Advisor,
            'title' => 'Advisor report',
            'generated_at' => now(),
            'metadata' => [],
        ]);
    }

    private function redFlag(Client $client): RedFlag
    {
        return RedFlag::query()->create([
            'client_id' => $client->getKey(),
            'source_type' => 'practice_health_test',
            'source_key' => 'practice-health-'.$client->getKey(),
            'category' => RedFlag::CATEGORY_FINANCIAL,
            'severity' => 'critical',
            'headline' => 'Practice health red flag',
            'detail' => 'Critical practice health fixture.',
            'surfaced_at' => now(),
        ]);
    }

    private function snapshotFor(User $advisor): PracticeHealthSnapshot
    {
        return PracticeHealthSnapshot::query()->create([
            'scope' => 'advisor',
            'advisor_user_id' => $advisor->getKey(),
            'client_ids' => [],
            'metrics' => [
                'summary' => [
                    'active_clients' => 0,
                    'target_pv' => 0,
                    'revenue_under_management' => 0,
                ],
            ],
            'generated_at' => now(),
        ]);
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
            GRANT SELECT ON practice_health_snapshots TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT practice_health_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT practice_health_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT practice_health_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
