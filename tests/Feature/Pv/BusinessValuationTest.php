<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\FinancialSnapshot;
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

        $expectedMid = round((
            $valuation->sde_value['mid']
            + $valuation->ebitda_value['mid']
            + $valuation->dcf_value['mid']
        ) / 3 + 10000, 2);

        $this->assertEqualsWithDelta($expectedMid, $valuation->reconciled_mid, 0.01);
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

    private function snapshot(Client $client, float $ebitda, float $sde, float $operatingCashFlow): FinancialSnapshot
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
            'period_end' => '2026-04-30',
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
            'metrics' => [
                'ebitda' => $ebitda,
                'sde' => $sde,
            ],
            'pulled_at' => now(),
        ]);
    }
}
