<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\FeeCalculation;
use App\Models\ImprovementOpportunity;
use App\Models\PvCalculation;
use App\Models\RiskCost;
use App\Services\Fees\FeeCalculator;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_fee_calculations_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

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
                DB::statement('REVOKE SELECT ON fee_calculations FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_hours_based_method_uses_admin_rate_and_retainer_conversion(): void
    {
        Config::set('fees.service.default_hourly_rate', 250);
        $client = $this->client();

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::HoursBased, [
            'services' => [
                ['name' => 'Discovery', 'hours' => 10, 'rate' => 250],
                ['name' => 'Implementation plan', 'hours' => 4, 'rate' => 300],
            ],
            'retainer_conversion' => true,
            'retainer_months' => 7,
        ]);

        $this->assertSame(FeeMethod::HoursBased, $calculation->method);
        $this->assertSame(3500.0, $calculation->suggested_low);
        $this->assertSame(3500.0, $calculation->suggested_mid);
        $this->assertSame(3500.0, $calculation->suggested_high);
        $this->assertEquals(500.0, $calculation->justification['retainer']['monthly_fee']);
        $this->assertSame('Implementation plan', $calculation->justification['services'][1]['name']);
        $this->assertEquals(250.0, $calculation->justification['services'][1]['rate']);
        $this->assertSame('config_fallback', $calculation->justification['services'][1]['rate_source']);
        $this->assertArrayNotHasKey('rate', $calculation->inputs['services'][0]);
    }

    public function test_outcome_based_method_references_pv_totals_and_roi_ratio(): void
    {
        $client = $this->client();
        $this->improvement($client, 150000);
        $this->risk($client, 30000);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::OutcomeBased, [
            'annual_revenue' => 500000,
            'complexity' => 'high',
        ]);

        $this->assertSame(FeeMethod::OutcomeBased, $calculation->method);
        $this->assertEquals(150000.0, $calculation->improvement_pv_total);
        $this->assertEquals(30000.0, $calculation->risk_cost_pv_total);
        $this->assertSame(33250.0, $calculation->suggested_mid);
        $this->assertSame(round(150000 / 33250, 4), $calculation->roi_ratio);
        $this->assertEquals(150000.0, $calculation->justification['pv_reference']['improvement_pv_total']);
        $this->assertEquals(30000.0, $calculation->justification['pv_reference']['risk_cost_pv_total']);
        $this->assertSame('improvement_pv_total / suggested_mid', $calculation->justification['roi_ratio_basis']);
    }

    public function test_entrepreneur_method_uses_distinct_lower_entry_structure(): void
    {
        $client = $this->client(EngagementType::ENTREPRENEUR_MODULE);

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::Entrepreneur, [
            'stage' => 'idea',
        ]);

        $this->assertSame(FeeMethod::Entrepreneur, $calculation->method);
        $this->assertSame(750.0, $calculation->suggested_low);
        $this->assertSame(1500.0, $calculation->suggested_mid);
        $this->assertSame(2250.0, $calculation->suggested_high);
        $this->assertTrue($calculation->justification['lower_entry_path']);
        $this->assertSame('proposal_only_no_payment_collection', $calculation->justification['payment_scope']);
    }

    public function test_fee_calculations_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Fee calculation RLS assertions require Postgres.');
        }

        $clientA = $this->client();
        $clientB = $this->client();
        $calculationA = $this->storedCalculation($clientA, 1000);
        $calculationB = $this->storedCalculation($clientB, 2000);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('fee_calculations')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($calculationA->id, $visibleIds);
        $this->assertNotContains($calculationB->id, $visibleIds);
    }

    private function client(EngagementType $engagementType = EngagementType::STANDARD_ADVISORY): Client
    {
        return Client::query()->create([
            'engagement_type' => $engagementType,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => 'Fee Calculator Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
    }

    private function improvement(Client $client, float $pv): ImprovementOpportunity
    {
        return ImprovementOpportunity::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::ImprovementOpportunity, $pv)->getKey(),
            'title' => 'Margin improvement',
            'annual_benefit' => $pv,
            'duration_years' => 1,
            'pv_of_impact' => $pv,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Improvement PV fixture', 'source_reference' => 'test:improvement-pv'],
            ],
        ]);
    }

    private function risk(Client $client, float $pv): RiskCost
    {
        return RiskCost::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::RiskCost, $pv)->getKey(),
            'title' => 'Risk mitigation value',
            'financial_impact' => $pv,
            'probability' => 1,
            'duration_years' => 1,
            'applied_impact' => $pv,
            'annual_expected_cost' => $pv,
            'pv_of_cost' => $pv,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Risk PV fixture', 'source_reference' => 'test:risk-pv'],
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
            'discount_rate_rationale' => 'Fixture rate.',
            'inputs' => ['cash_flows' => []],
            'result' => ['present_value' => $presentValue],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture PV', 'source_reference' => 'test:pv'],
            ],
        ]);
    }

    private function storedCalculation(Client $client, float $mid): FeeCalculation
    {
        app(RequestContext::class)->apply('system', []);

        return FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::HoursBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => $mid,
            'suggested_mid' => $mid,
            'suggested_high' => $mid,
            'improvement_pv_total' => 0,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 0,
            'justification' => ['fixture' => true],
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
            GRANT SELECT ON fee_calculations TO %1$s;
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
            DB::statement('SAVEPOINT fee_calculations_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT fee_calculations_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT fee_calculations_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
