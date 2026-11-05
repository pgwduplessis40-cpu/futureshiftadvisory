<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TaxProjection;
use PHPUnit\Framework\TestCase;

final class TaxProjectionTest extends TestCase
{
    public function test_eligible_losses_roll_forward_across_periods_without_cash_refunds(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/assets-tax-actuals.json'), true, flags: JSON_THROW_ON_ERROR);
        $periods = [];
        foreach ($fixture['tax']['profit'] as $index => $profit) {
            $year = 2027 + $index;
            $periods[] = new TaxPeriod($year.'-01-01', $year.'-12-31', $profit, $fixture['tax']['adjustments'][$index]);
        }
        $policy = new TaxPolicy('synthetic-company', $fixture['tax']['rate_percent'], true, 'synthetic-policy', 'reviewed-eligibility', 'v1');
        $result = (new TaxProjection)->calculate($policy, $fixture['tax']['opening_losses'], $periods);
        self::assertSame('pass', $result['status']);
        foreach (['opening', 'generated', 'used', 'closing'] as $field) {
            self::assertSame($fixture['expected_tax'][$field], array_column($result['periods'], 'loss_'.$field));
        }
        self::assertSame($fixture['expected_tax']['expense'], array_column($result['periods'], 'tax_expense'));
        self::assertSame('indicative_planning_tax_expense_only', $result['scope']);
        self::assertArrayNotHasKey('cash_refund', $result);
        self::assertEquals($result, (new TaxProjection)->calculate($policy, '300', array_reverse($periods)));
    }

    public function test_known_ineligibility_does_not_generate_eligible_losses(): void
    {
        $result = (new TaxProjection)->calculate($this->policy(false), '0', [new TaxPeriod('2027-01-01', '2027-12-31', '-100', '0')]);
        self::assertSame('pass', $result['status']);
        self::assertSame('0.00', $result['periods'][0]['loss_generated']);
        self::assertSame('0.00', $result['periods'][0]['tax_expense']);
        self::assertSame('fail', (new TaxProjection)->calculate($this->policy(false), '100', [new TaxPeriod('2027-01-01', '2027-12-31', '100', '0')])['status']);
    }

    public function test_missing_policy_or_inputs_never_assume_zero_or_eligibility(): void
    {
        $periods = [new TaxPeriod('2027-01-01', '2027-01-31', null, '0'), new TaxPeriod('2027-02-01', '2027-02-28', '100', '0')];
        $result = (new TaxProjection)->calculate($this->policy(), '0', $periods);
        self::assertSame('cannot_verify', $result['status']);
        self::assertNull($result['periods'][0]['tax_expense']);
        self::assertNull($result['periods'][1]['loss_opening']);
        self::assertNull($result['periods'][1]['tax_expense']);
        $unknown = (new TaxProjection)->calculate(new TaxPolicy(null, null, null, null, null, null), null, [new TaxPeriod('2027-01-01', '2027-01-31', '0', null)]);
        self::assertContains('policy.losses_eligible', $unknown['missing']);
        self::assertContains('opening_eligible_losses', $unknown['missing']);
        self::assertNull($unknown['periods'][0]['taxable_before_losses']);
        self::assertSame('cannot_verify', (new TaxProjection)->calculate($this->policy(), '0', [])['status']);
    }

    public function test_money_lines_round_before_rollforward_and_signed_adjustments_reduce_tax(): void
    {
        $result = (new TaxProjection)->calculate($this->policy(), '0.005', [new TaxPeriod('2027-01-01', '2027-12-31', '10.005', '-0.005')]);
        self::assertSame('10.00', $result['periods'][0]['taxable_before_losses']);
        self::assertSame('0.01', $result['periods'][0]['loss_used']);
        self::assertSame('9.99', $result['periods'][0]['taxable_after_losses']);
        self::assertSame('2.00', $result['periods'][0]['tax_expense']);
    }

    public function test_period_gaps_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TaxProjection)->calculate($this->policy(), '0', [new TaxPeriod('2027-01-01', '2027-01-31', '0', '0'), new TaxPeriod('2027-03-01', '2027-03-31', '0', '0')]);
    }

    private function policy(?bool $eligible = true): TaxPolicy
    {
        return new TaxPolicy('synthetic-company', '20', $eligible, 'policy', 'eligibility-review', 'v1');
    }
}
