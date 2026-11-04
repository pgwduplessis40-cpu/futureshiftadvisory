<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\BudgetFacts;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\FundingPresentation;
use App\Services\Entrepreneurs\ReleaseGate\PlanFacts;
use App\Services\Entrepreneurs\ReleaseGate\SourceRecords;
use App\Services\Entrepreneurs\ReleaseGate\SourceReference;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;
use Tests\TestCase;

final class SourceAdaptersTest extends TestCase
{
    private function source(array $records): SourceRecords
    {
        $hash = CanonicalJson::hash($records);

        return new SourceRecords(new SourceVersion('synthetic', $hash, $hash, [], 12, '2026-11-01', CanonicalJson::encode($records)));
    }

    public function test_budget_adapter_preserves_observable_numbers_without_inventing_provenance(): void
    {
        $source = $this->source(['budget' => [
            'forecast_years' => 1, 'expected_runway_months' => 3,
            'assumptions' => ['forecast_start_month' => '2026-11', 'funding_position' => 'self_funded', 'funding_position_confirmed' => true],
            'monthly_fixed_costs' => [['label' => 'Owner pay', 'amount' => 550, 'quantity' => 1, 'cadence' => 'weekly']],
            'future_costs' => [['label' => 'Vehicle', 'amount' => 18000, 'quantity' => 1, 'year' => 3, 'classification' => 'operating']],
            'funding_sources' => [['amount' => 200]],
            'computed' => ['opening_cash_balance' => 1000, 'total_funding' => 200, 'total_launch_costs' => 0, 'monthly_fixed_costs' => 100,
                'assumptions' => ['company_tax_rate_percent' => 28], 'break_even_year' => 1,
                'scenarios' => [['key' => 'base', 'monthly_detail' => [['month' => 1, 'net_cash_flow' => -1100, 'cumulative_cash' => -100, 'cash_collected' => 0, 'revenue' => 0]],
                    'annual_totals' => [['year' => 1, 'net_cash_flow' => -1100, 'ending_cash' => -100, 'net_profit_before_tax' => 1000, 'tax' => 280, 'net_profit_before_tax_percent' => 57.1]]]],
            ],
        ]]);
        $facts = new FactSet((new BudgetFacts)->capture($source));
        self::assertSame('1200', $facts->one('model.available_funds')->value);
        self::assertSame('100', $facts->one('model.cash_deficit')->value);
        self::assertSame('0.00', $facts->one('model.reserve_gap')->value);
        self::assertSame('2383.33333333', $facts->one('model.owner_pay')->value);
        self::assertSame('2026-11-01', $facts->one('model.owner_pay')->asOf);
        self::assertSame(28, $facts->one('tax.rate_display', period: 'year:1')->value);
        self::assertNull($facts->one('funding.identity', entity: 'funding:0'));
        self::assertNull($facts->one('ledger.coverage', period: 'month:1'));
        self::assertTrue($facts->one('cost.asset_like', entity: 'future:0', period: 'year:3')->value);
    }

    public function test_current_claim_binding_retains_conflicting_prose_and_expires_after_source_edit(): void
    {
        $body = 'Starting cash is $1000. We currently pay $550 per week. Break-even is Year 2. NPBT target 20-30%.';
        $reference = new SourceReference('plan_section', 'section-1', 'finance', CanonicalJson::hash($body));
        $fact = new Fact('plan.opening_cash', '1000', unit: 'NZD', basis: 'bank-A', asOf: '2026-11-01', confirmed: true, sources: [$reference]);
        $records = ['sections' => [['id' => 'section-1', 'key' => 'finance', 'title' => 'Finance', 'body' => $body], ['id' => 'section-2', 'key' => 'summary', 'title' => 'Summary', 'body' => 'Opening cash is $1200.']],
            'claims' => [['claim_key' => 'cash', 'state' => 'confirmed', 'fact' => json_decode(CanonicalJson::encode($fact), true)]]];
        $adapter = new PlanFacts;
        $facts = new FactSet($adapter->capture($this->source($records)));
        self::assertCount(2, $facts->all('plan.opening_cash'));
        self::assertCount(1, array_filter($facts->all('plan.opening_cash'), fn (Fact $f): bool => $f->confirmed));
        self::assertSame(24, $facts->one('scope.required_horizon')->value);
        self::assertSame('550', $facts->one('plan.owner_pay')->value);
        self::assertSame('20', $facts->one('plan.margin_min', period: 'unspecified')->value);
        self::assertCount(4, $adapter->blocks($this->source($records)));
        $records['sections'][0]['body'] = 'Opening cash is $1500.';
        $current = $adapter->capture($this->source($records));
        self::assertCount(0, array_filter($current, fn (Fact $f): bool => $f->confirmed));
    }

    public function test_funding_presentation_keeps_cash_reserve_and_request_separate(): void
    {
        $decision = ['cash_deficit' => 0, 'reserve_target_shortfall' => 232.73, 'external_funding_request' => null];
        $text = FundingPresentation::headline($decision);
        self::assertStringContainsString('Forecast cash stays above zero.', $text);
        self::assertStringContainsString('Reserve-target shortfall: $232.73', $text);
        self::assertStringContainsString('Proposed external request: Not recorded', $text);
        $rows = FundingPresentation::rows($decision);
        self::assertSame(0.0, $rows[5]['amount']);
        self::assertSame(232.73, $rows[6]['amount']);
        self::assertNull($rows[7]['amount']);
        self::assertStringContainsString('cash deficit is $100.00', FundingPresentation::headline(['cash_deficit' => 100]));
        self::assertStringContainsString('cannot yet be verified', FundingPresentation::headline([]));
    }
}
