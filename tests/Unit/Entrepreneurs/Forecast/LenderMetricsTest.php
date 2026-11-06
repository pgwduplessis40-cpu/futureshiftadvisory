<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\DebtService;
use App\Services\Entrepreneurs\Forecast\LenderMetrics;
use PHPUnit\Framework\TestCase;

final class LenderMetricsTest extends TestCase
{
    /** The specification's conditional example: $4,335 of monthly fixed costs
     * against a $1,500 day at an 89.1% margin is about 3.2 days a month.
     */
    public function test_break_even_reproduces_the_specifications_conditional_example(): void
    {
        $result = (new LenderMetrics)->breakEven('4335', '1500', '0.891', '6', 'Intensive day');

        self::assertSame(LenderMetrics::STATUS_OK, $result['status']);
        self::assertSame('3.2435', $result['value'], 'the specification describes this as about 3.2 days');
        self::assertSame('1336.50', $result['detail']['contribution_per_unit']);
        self::assertSame('0.5406', $result['detail']['utilisation'], 'about 54% of a six-day capacity');
    }

    public function test_a_non_positive_contribution_has_no_finite_break_even_rather_than_zero_units(): void
    {
        $result = (new LenderMetrics)->breakEven('4335', '1500', '0', '6', 'Intensive day');

        self::assertSame(LenderMetrics::STATUS_NO_FINITE_RESULT, $result['status']);
        self::assertNull($result['value'], 'zero units would say the business needs no sales');
        self::assertSame('contribution per unit is not positive', $result['basis']);
    }

    public function test_break_even_without_a_declared_unit_cannot_verify(): void
    {
        $result = (new LenderMetrics)->breakEven('4335', '1500', '0.891', '6', null);

        self::assertSame(LenderMetrics::STATUS_CANNOT_VERIFY, $result['status']);
        self::assertContains('break_even.unit', $result['detail']['missing']);
    }

    public function test_multi_stream_break_even_requires_an_explicit_sales_mix(): void
    {
        $metrics = new LenderMetrics;

        $noMix = $metrics->blendedBreakEven('4335', [], []);
        self::assertSame(LenderMetrics::STATUS_CANNOT_VERIFY, $noMix['status']);
        self::assertContains('break_even.sales_mix', $noMix['detail']['missing']);

        $badMix = $metrics->blendedBreakEven('4335', ['a' => '0.5', 'b' => '0.2'], [
            'a' => ['price' => '1500', 'gross_margin' => '0.9'],
            'b' => ['price' => '600', 'gross_margin' => '0.8'],
        ]);
        self::assertSame(LenderMetrics::STATUS_CANNOT_VERIFY, $badMix['status']);
        self::assertContains('break_even.sales_mix_sums_to_one', $badMix['detail']['missing']);
    }

    public function test_a_blended_mix_solves_against_its_weighted_contribution(): void
    {
        // 0.5 x (1500 x 0.9) + 0.5 x (600 x 0.8) = 675 + 240 = 915.
        $result = (new LenderMetrics)->blendedBreakEven('4575', ['a' => '0.5', 'b' => '0.5'], [
            'a' => ['price' => '1500', 'gross_margin' => '0.9'],
            'b' => ['price' => '600', 'gross_margin' => '0.8'],
        ]);

        self::assertSame(LenderMetrics::STATUS_OK, $result['status']);
        self::assertSame('5.0000', $result['value']);
        self::assertSame('blended unit', $result['detail']['unit']);
    }

    public function test_expense_cover_reports_essential_cover_excluding_owner_pay_separately(): void
    {
        $result = (new LenderMetrics)->expenseCover('12000', '4000', '1500');

        self::assertSame('3.00', $result['value']);
        self::assertSame('2500.00', $result['detail']['essential_basis_excluding_owner_pay']);
        self::assertSame('4.80', $result['detail']['essential_months_cover']);
    }

    public function test_runway_reports_the_first_negative_date(): void
    {
        $result = (new LenderMetrics)->runway([
            ['date' => '2027-01-01', 'movement_id' => null, 'balance' => '100.00'],
            ['date' => '2027-02-10', 'movement_id' => 'spend', 'balance' => '-50.00'],
        ], '2027-12-31');

        self::assertSame(LenderMetrics::STATUS_OK, $result['status']);
        self::assertSame('2027-02-10', $result['value']);
    }

    public function test_cash_that_never_goes_negative_reports_horizon_coverage_not_an_indefinite_runway(): void
    {
        $result = (new LenderMetrics)->runway([
            ['date' => '2027-01-01', 'movement_id' => null, 'balance' => '100.00'],
            ['date' => '2027-02-10', 'movement_id' => 'spend', 'balance' => '50.00'],
        ], '2027-12-31');

        self::assertSame(LenderMetrics::STATUS_OK, $result['status']);
        self::assertNull($result['value'], 'no negative date exists to report');
        self::assertTrue($result['detail']['covers_horizon']);
        self::assertSame('2027-12-31', $result['detail']['horizon_end']);
    }

    public function test_no_debt_service_is_not_applicable_rather_than_zero_or_infinity(): void
    {
        $result = (new LenderMetrics)->debtServiceCover('5000', '400', new DebtService('2027-03', '0', '0'));

        self::assertSame(LenderMetrics::STATUS_NOT_APPLICABLE, $result['status']);
        self::assertNull($result['value']);
        self::assertSame('no debt service in the stated period', $result['basis']);
    }

    public function test_dscr_uses_the_same_period_for_both_sides_and_records_its_terms(): void
    {
        $debt = new DebtService('2027-03', '1000', '250', '50000', '0.0895', ['2027-01-15'], 60, '500', 'monthly principal and interest', 6);

        // (5000 + 250 + 400) / (1000 + 250) = 5650 / 1250 = 4.52
        $result = (new LenderMetrics)->debtServiceCover('5000', '400', $debt);

        self::assertSame(LenderMetrics::STATUS_OK, $result['status']);
        self::assertSame('4.5200', $result['value']);
        self::assertSame('2027-03', $result['detail']['period']);
        self::assertSame('1250.00', $result['detail']['denominator']);
        self::assertStringContainsString('not a lender covenant definition', (string) $result['basis']);
        self::assertSame('monthly principal and interest', $result['detail']['terms']->repaymentSchedule);
    }

    public function test_dscr_without_its_facility_terms_cannot_verify(): void
    {
        $result = (new LenderMetrics)->debtServiceCover('5000', '400', new DebtService('2027-03', '1000', '250'));

        self::assertSame(LenderMetrics::STATUS_CANNOT_VERIFY, $result['status']);
        self::assertNull($result['value']);
        self::assertContains('debt.facility_amount', $result['detail']['missing']);
        self::assertContains('debt.draw_dates', $result['detail']['missing']);
    }

    public function test_owner_pay_ratio_needs_revenue_to_express_itself_against(): void
    {
        $metrics = new LenderMetrics;

        self::assertSame('0.2500', $metrics->ownerPayRatio('1500', '6000')['value']);
        self::assertSame(LenderMetrics::STATUS_NO_FINITE_RESULT, $metrics->ownerPayRatio('1500', '0')['status']);
    }
}
