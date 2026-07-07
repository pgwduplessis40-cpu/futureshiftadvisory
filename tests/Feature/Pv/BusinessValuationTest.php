<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\PvType;
use App\Models\AccountingConnection;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;
use App\Models\PvCalculation;
use App\Models\SuccessionPlan;
use App\Models\ValuationMultiple;
use App\Services\Pv\BusinessValuation as BusinessValuationService;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BusinessValuationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
        $this->valuationMultiple('M6962', ValuationMultiple::METRIC_SDE, 2.0, 3.0, 4.0);
        $this->valuationMultiple('M6962', ValuationMultiple::METRIC_EBITDA, 3.0, 4.0, 5.0);
    }

    public function test_business_valuation_computes_three_methods_and_reconciles_adjustments(): void
    {
        $client = $this->client();
        $this->snapshot($client, ebitda: 100000, sde: 120000, operatingCashFlow: 90000);

        $valuation = app(BusinessValuationService::class)->calculate($client, [
            'industry_code' => 'M6962',
            'growth_rate' => 0.0,
            'terminal_growth_rate' => 0.02,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'Advisor valuation rate for test.',
            ],
            'adjustments' => [
                ['label' => 'Surplus cash', 'amount' => 10000, 'rationale' => 'Normalise surplus cash.'],
            ],
        ]);

        $this->assertInstanceOf(BusinessValuation::class, $valuation);
        $this->assertEqualsWithDelta(240000.0, (float) $valuation->sde_value['low'], 0.01);
        $this->assertEqualsWithDelta(360000.0, (float) $valuation->sde_value['mid'], 0.01);
        $this->assertEqualsWithDelta(480000.0, (float) $valuation->sde_value['high'], 0.01);
        $this->assertEqualsWithDelta(300000.0, (float) $valuation->ebitda_value['low'], 0.01);
        $this->assertEqualsWithDelta(400000.0, (float) $valuation->ebitda_value['mid'], 0.01);
        $this->assertEqualsWithDelta(500000.0, (float) $valuation->ebitda_value['high'], 0.01);
        $this->assertSame('dcf', $valuation->dcf_value['method']);
        $this->assertGreaterThan(0, $valuation->dcf_value['mid']);
        $this->assertEqualsWithDelta(1 / 3, (float) $valuation->method_weights['sde'], 0.0001);
        $this->assertSame('sde', $valuation->method_rationale['selected_method']);
        $this->assertSame('Valuation reconciles SDE, EBITDA, and DCF using advisor-confirmed method weights.', $valuation->method_rationale['rationale']);
        $this->assertCount(9, $valuation->dcf_sensitivity['rows']);
        $this->assertStringStartsWith('pv_calculation:', $valuation->dcf_sensitivity['source_attributions'][0]['source_reference']);

        $expectedMid = round((
            $valuation->sde_value['mid']
            + $valuation->ebitda_value['mid']
            + $valuation->dcf_value['mid']
        ) / 3 + 10000, 2);

        $this->assertEqualsWithDelta($expectedMid, $valuation->reconciled_mid, 0.01);
        $this->assertEqualsWithDelta($expectedMid, $valuation->equity_bridge['enterprise_range']['mid'], 0.01);
        $this->assertEqualsWithDelta($expectedMid, $valuation->equity_bridge['equity_range']['mid'], 0.01);
        $this->assertSame('Surplus cash', $valuation->adjustments[0]['label']);
        $this->assertNull($valuation->data_quality_disclaimer);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'business_valuation.created',
            'subject_type' => BusinessValuation::class,
            'subject_id' => $valuation->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_business_valuation_uses_questionnaire_inputs_with_disclaimer_when_no_snapshot_exists(): void
    {
        $client = $this->client();

        $valuation = app(BusinessValuationService::class)->calculate($client, [
            'industry_code' => 'M6962',
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'Advisor valuation rate for test.',
            ],
            'questionnaire_financials' => [
                'ebitda' => 80000,
                'sde' => 95000,
                'cash_flows' => [75000, 78000, 81000],
                'source_reference' => 'questionnaire:financial-summary',
            ],
        ]);

        $this->assertNotNull($valuation->data_quality_disclaimer);
        $this->assertStringContainsString('questionnaire financial inputs', $valuation->data_quality_disclaimer);
        $this->assertEqualsWithDelta(190000.0, (float) $valuation->sde_value['low'], 0.01);
        $this->assertContains('questionnaire:financial-summary', collect($valuation->source_attributions)->pluck('source_reference')->all());
    }

    public function test_business_valuation_records_method_weights_rationale_and_equity_bridge(): void
    {
        $client = $this->client();
        $this->snapshot($client, ebitda: 100000, sde: 120000, operatingCashFlow: 90000);

        $valuation = app(BusinessValuationService::class)->calculate($client, [
            'industry_code' => 'M6962',
            'growth_rate' => 0.0,
            'terminal_growth_rate' => 0.02,
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'Advisor valuation rate for test.',
            ],
            'method_weights' => [
                'sde' => 0.2,
                'ebitda' => 0.5,
                'dcf' => 0.3,
            ],
            'method_rationale' => [
                'selected_method' => 'ebitda',
                'rationale' => 'EBITDA gets the primary weight because the owner adjustment is less reliable than audited earnings.',
            ],
            'equity_bridge' => [
                'debt' => 50000,
                'surplus_cash' => 12000,
                'normalised_working_capital' => 3000,
                'other_advisor_adjustments' => [
                    ['label' => 'Minor settlement adjustment', 'amount' => 5000, 'rationale' => 'Advisor normalisation.'],
                ],
            ],
        ]);

        $expectedEnterpriseMid = round(
            ($valuation->sde_value['mid'] * 0.2)
            + ($valuation->ebitda_value['mid'] * 0.5)
            + ($valuation->dcf_value['mid'] * 0.3),
            2,
        );
        $expectedBridge = -30000.0;

        $this->assertEqualsWithDelta(0.2, (float) $valuation->method_weights['sde'], 0.0001);
        $this->assertSame('ebitda', $valuation->method_rationale['selected_method']);
        $this->assertStringContainsString('primary weight', $valuation->method_rationale['rationale']);
        $this->assertEqualsWithDelta($expectedEnterpriseMid, $valuation->equity_bridge['enterprise_range']['mid'], 0.01);
        $this->assertEqualsWithDelta($expectedBridge, $valuation->equity_bridge['bridge_total'], 0.01);
        $this->assertEqualsWithDelta($expectedEnterpriseMid + $expectedBridge, $valuation->reconciled_mid, 0.01);
        $baseSensitivity = collect($valuation->dcf_sensitivity['rows'])
            ->first(fn (array $row): bool => (float) $row['discount_rate'] === 0.12 && (float) $row['terminal_growth_rate'] === 0.02);
        $this->assertIsArray($baseSensitivity);
        $this->assertEqualsWithDelta($valuation->dcf_value['mid'], (float) $baseSensitivity['value'], 0.01);
    }

    public function test_stale_snapshot_and_ebitda_fallback_trigger_valuation_disclosures(): void
    {
        $client = $this->client();
        $this->snapshot(
            $client,
            ebitda: null,
            sde: 120000,
            operatingCashFlow: 90000,
            periodEnd: now()->subDays(240)->toDateString(),
        );

        $valuation = app(BusinessValuationService::class)->calculate($client, [
            'industry_code' => 'M6962',
            'snapshot_stale_after_days' => 180,
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'Advisor valuation rate for test.',
            ],
        ]);

        $types = collect($valuation->valuation_disclosures)->pluck('type')->all();

        $this->assertContains('stale_snapshot', $types);
        $this->assertContains('ebitda_fallback', $types);
        $this->assertEqualsWithDelta(85000.0, (float) $valuation->ebitda_value['input'], 0.01);
        $this->assertStringContainsString('net profit was used as a temporary EBITDA proxy', $valuation->data_quality_disclaimer);
    }

    public function test_valuation_documents_succession_terminal_value_treatment(): void
    {
        $client = $this->client();
        $this->snapshot($client, ebitda: 100000, sde: 120000, operatingCashFlow: 90000);
        $targetExitPv = PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Succession target fixture.',
            'inputs' => ['cash_flows' => [['period' => 1, 'amount' => 100000.0]]],
            'result' => ['present_value' => 89285.71],
            'source_attributions' => [[
                'claim' => 'Succession target fixture.',
                'source_reference' => 'test:succession-target',
            ]],
            'as_at' => now(),
        ]);
        SuccessionPlan::query()->create([
            'client_id' => $client->getKey(),
            'exit_readiness_score' => 7,
            'options' => [],
            'owner_dependency_plan' => [],
            'target_exit_pv_calculation_id' => $targetExitPv->getKey(),
            'target_exit_pv' => 89285.71,
            'owner_readiness_is_primary_constraint' => false,
        ]);

        $valuation = app(BusinessValuationService::class)->calculate($client, [
            'industry_code' => 'M6962',
            'terminal_growth_rate' => 0.025,
            'discount_options' => [
                'rate' => 0.12,
                'rationale' => 'Advisor valuation rate for test.',
            ],
        ]);

        $this->assertSame('documented_difference', $valuation->succession_comparison['status']);
        $this->assertSame($targetExitPv->id, $valuation->succession_comparison['target_exit_pv_calculation_id']);
        $this->assertStringContainsString('Business valuation DCF includes an explicit terminal value', $valuation->succession_comparison['explanation']);
        $this->assertStringContainsString('Succession target-exit PV discounts the owner target cash-flow plan only', $valuation->succession_comparison['explanation']);
    }

    private function client(): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'Business Valuation Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
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

    private function snapshot(Client $client, ?float $ebitda, float $sde, float $operatingCashFlow, string $periodEnd = '2026-04-30'): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-business-valuation',
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
            'period_end' => $periodEnd,
            'source' => 'xero',
            'source_badge' => 'stub',
            'degraded' => false,
            'profit_and_loss' => [
                'revenue' => 500000,
                'net_profit' => 85000,
            ],
            'balance_sheet' => [
                'assets' => 900000,
                'liabilities' => 300000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => $operatingCashFlow,
            ],
            'metrics' => array_filter([
                'ebitda' => $ebitda,
                'sde' => $sde,
            ], static fn (mixed $value): bool => $value !== null),
            'pulled_at' => now(),
        ]);
    }
}
