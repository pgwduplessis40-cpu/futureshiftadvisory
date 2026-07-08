<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\ExchangeRate;
use App\Models\FinancialSnapshot;
use App\Models\User;
use App\Models\ValuationMultiple;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\Valuation as DdValuationService;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class DdValuationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $this->valuationMultiple('M6962', ValuationMultiple::METRIC_SDE, 2.0, 3.0, 4.0);
        $this->valuationMultiple('M6962', ValuationMultiple::METRIC_EBITDA, 3.0, 4.0, 5.0);
    }

    public function test_dd_valuation_reuses_business_valuation_and_normalises_foreign_currency_to_nzd(): void
    {
        [$advisor, $engagement] = $this->ddEngagement();
        $rate = $this->exchangeRate('USD', 0.5);

        $valuation = app(DdValuationService::class)->calculate($engagement, $advisor, [
            'source_currency' => 'USD',
            'industry_code' => 'M6962',
            'asking_price' => 550000,
            'growth_rate' => 0.0,
            'terminal_growth_rate' => 0.02,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'DD valuation test rate.',
            ],
            'financials' => [
                'ebitda' => 100000,
                'sde' => 120000,
                'cash_flows' => [90000, 90000, 90000],
                'source_reference' => 'dd_data_room_item:financial-target',
            ],
            'precedent_transactions' => [
                ['label' => 'Comparable target', 'amount' => 525000, 'rationale' => 'Recent sector sale.'],
            ],
            'deal_structure_adjustments' => [
                ['label' => 'Vendor finance discount', 'amount' => -25000],
            ],
            'synergy_adjustments' => [
                ['label' => 'Procurement upside', 'amount' => 40000],
            ],
        ]);

        $this->assertInstanceOf(DdValuation::class, $valuation);
        $this->assertSame('USD', $valuation->source_currency);
        $this->assertSame('NZD', $valuation->normalised_currency);
        $this->assertSame($rate->id, $valuation->exchange_rate_id);
        $this->assertEqualsWithDelta(2.0, $valuation->source_to_nzd_rate, 0.0001);
        $this->assertNotNull($valuation->rate_timestamp);
        $this->assertInstanceOf(BusinessValuation::class, $valuation->businessValuation);
        $this->assertSame($valuation->businessValuation->pv_calculation_id, $valuation->pv_calculation_id);
        $this->assertSame('dcf', $valuation->businessValuation->dcf_value['method']);
        $this->assertEqualsWithDelta(
            $valuation->businessValuation->reconciled_mid * 2,
            $valuation->normalised_values['reconciled']['mid'],
            0.01,
        );
        $this->assertGreaterThan(
            $valuation->sensitivity['base_rate']['reconciled']['mid'],
            $valuation->sensitivity['plus_10_percent_rate']['reconciled']['mid'],
        );
        $this->assertSame('within_range', $valuation->buyer_position['position']);
        $this->assertSame('standalone_reconciled_equity_value_nzd', $valuation->buyer_position['position_basis']);
        $this->assertSame('dcf', $valuation->buyer_position['valuation_basis']['primary_method']);
        $this->assertSame('Comparable target', $valuation->buyer_position['precedent_transactions'][0]['label']);
        $this->assertEqualsWithDelta(-25000.0, $valuation->buyer_position['deal_structure_adjustments'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(40000.0, $valuation->buyer_position['synergy_adjustments'][0]['amount'], 0.01);
        $valueWalk = $valuation->buyer_position['value_walk'];
        $this->assertSame('standalone_value_separated_from_buyer_specific_synergies', $valueWalk['basis']);
        $this->assertEqualsWithDelta($valuation->normalised_values['reconciled']['mid'], $valueWalk['standalone_value_range_nzd']['mid'], 0.01);
        $this->assertEqualsWithDelta(-25000.0, $valueWalk['deal_structure_adjustment_nzd'], 0.01);
        $this->assertEqualsWithDelta(40000.0, $valueWalk['synergy_adjustment_nzd'], 0.01);
        $this->assertEqualsWithDelta($valuation->normalised_values['reconciled']['mid'] + 15000.0, $valueWalk['buyer_specific_value_range_nzd']['mid'], 0.01);
        $this->assertStringContainsString('Standalone value', $valueWalk['disclosure']);
        $this->assertContains(
            "exchange_rate:{$rate->id}:NZD/USD",
            collect($valuation->source_attributions)->pluck('source_reference')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.valuation_created',
            'subject_id' => $valuation->id,
        ]);
    }

    public function test_dd_valuation_forces_target_financials_instead_of_buyer_snapshot(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('target-isolation-dd-advisor@example.test');
        $this->snapshot($engagement->client, ebitda: 900000, sde: 950000, operatingCashFlow: 900000);

        $valuation = app(DdValuationService::class)->calculate($engagement, $advisor, [
            'source_currency' => 'NZD',
            'financials' => [
                'ebitda' => 100000,
                'sde' => 120000,
                'cash_flows' => [90000, 90000, 90000],
            ],
        ]);

        $this->assertEqualsWithDelta(100000.0, $valuation->businessValuation->ebitda_value['input'], 0.01);
        $this->assertEqualsWithDelta(120000.0, $valuation->businessValuation->sde_value['input'], 0.01);
        $this->assertLessThan(1000000, $valuation->businessValuation->reconciled_mid);
        $this->assertSame('NZD', $valuation->source_currency);
        $this->assertNull($valuation->exchange_rate_id);
    }

    public function test_missing_foreign_exchange_rate_rolls_back_dd_valuation(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('missing-fx-dd-advisor@example.test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No RBNZ exchange rate exists for NZD/EUR.');

        try {
            app(DdValuationService::class)->calculate($engagement, $advisor, [
                'source_currency' => 'EUR',
                'financials' => [
                    'ebitda' => 100000,
                    'sde' => 120000,
                    'cash_flows' => [90000, 90000, 90000],
                ],
            ]);
        } finally {
            $this->assertDatabaseCount('dd_valuations', 0);
            $this->assertDatabaseCount('business_valuations', 0);
        }
    }

    public function test_nzd_dd_valuation_does_not_require_an_exchange_rate(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('nzd-dd-advisor@example.test');

        $valuation = app(DdValuationService::class)->calculate($engagement, $advisor, [
            'source_currency' => 'NZD',
            'financials' => [
                'ebitda' => 100000,
                'sde' => 120000,
                'cash_flows' => [90000, 90000, 90000],
            ],
        ]);

        $this->assertNull($valuation->exchange_rate_id);
        $this->assertEqualsWithDelta(1.0, $valuation->source_to_nzd_rate, 0.0001);
        $this->assertSame($valuation->businessValuation->reconciled_mid, $valuation->normalised_values['reconciled']['mid']);
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail = 'valuation-dd-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => '9429000000111',
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Supplies Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'industry' => 'Distribution',
                'currency' => 'USD',
                'asking_price' => 550000,
            ],
        );

        return [$advisor, $engagement];
    }

    private function valuationMultiple(string $industryCode, string $metric, float $low, float $mid, float $high): ValuationMultiple
    {
        return ValuationMultiple::query()->create([
            'industry_code' => $industryCode,
            'industry_label' => 'Management advice and related consulting services',
            'metric' => $metric,
            'multiple_low' => $low,
            'multiple_mid' => $mid,
            'multiple_high' => $high,
            'source' => $metric === ValuationMultiple::METRIC_SDE
                ? ValuationMultiple::SOURCE_NZ_BUSINESS_BROKERS
                : ValuationMultiple::SOURCE_MBIE,
            'source_badge' => 'stub',
            'degraded' => false,
            'quarter' => '2026Q2',
            'fetched_at' => now(),
            'record_hash' => hash('sha256', $industryCode.$metric.$low.$mid.$high),
            'payload' => ['test' => true],
        ]);
    }

    private function exchangeRate(string $quoteCurrency, float $rate): ExchangeRate
    {
        return ExchangeRate::query()->create([
            'base_currency' => 'NZD',
            'quote_currency' => $quoteCurrency,
            'rate' => $rate,
            'rate_date' => '2026-05-22',
            'source' => 'rbnz',
            'source_badge' => 'stub',
            'degraded' => false,
            'fetched_at' => now(),
        ]);
    }

    private function snapshot(Client $client, float $ebitda, float $sde, float $operatingCashFlow): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-dd-valuation',
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => app(KeyEnvelope::class)->encrypt('{"access_token":"test"}'),
            'token_envelope_meta' => ['version' => 1],
            'scopes' => ['accounting.reports.read'],
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'source' => 'xero',
            'source_badge' => 'stub',
            'degraded' => false,
            'profit_and_loss' => [
                'revenue' => 5000000,
                'net_profit' => 850000,
            ],
            'balance_sheet' => [
                'assets' => 9000000,
                'liabilities' => 3000000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => $operatingCashFlow,
            ],
            'metrics' => [
                'ebitda' => $ebitda,
                'sde' => $sde,
            ],
            'pulled_at' => now(),
        ]);
    }
}
