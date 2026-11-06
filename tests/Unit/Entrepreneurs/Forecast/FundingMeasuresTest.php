<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashLedger;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\DriverNumbers;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FundingMeasures;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use PHPUnit\Framework\TestCase;

final class FundingMeasuresTest extends TestCase
{
    /** The specification's first mandated case: $100 opening against $150 of
     * expenditure is a $50 deficit, not a negative requirement produced by
     * subtracting the same opening balance twice.
     */
    public function test_one_hundred_opening_against_one_hundred_and_fifty_spend_is_a_fifty_dollar_deficit(): void
    {
        $ledger = $this->ledger('100', [
            new CashMovement('spend', 'plan', '2027-01-20', '150', 'out', 'operating_payment', 0),
        ]);

        $result = (new FundingMeasures)->calculate($ledger, $this->policy());

        self::assertSame('pass', $result['status']);
        self::assertSame('-50.00', $result['lowest_cash']);
        self::assertSame('50.00', $result['cash_deficit']);
    }

    /** The second mandated case: a draw that arrives after a payment cannot
     * fund it, so the earlier dip still sizes the requirement.
     */
    public function test_a_later_draw_cannot_fund_an_earlier_payment(): void
    {
        $ledger = $this->ledger('100', [
            new CashMovement('spend', 'plan', '2027-01-10', '150', 'out', 'operating_payment', 0),
            new CashMovement('draw', 'lender', '2027-01-25', '500', 'in', 'funding', 1, true),
        ]);

        $result = (new FundingMeasures)->calculate($ledger, $this->policy());

        self::assertSame('-50.00', $result['lowest_cash'], 'the dip before the draw is the one that needs funding');
        self::assertSame('2027-01-10', $result['lowest_cash_date']);
        self::assertSame('50.00', $result['cash_deficit']);
    }

    public function test_a_positive_lowest_balance_needs_no_cash_and_never_reports_a_negative_requirement(): void
    {
        $ledger = $this->ledger('5000', [
            new CashMovement('spend', 'plan', '2027-01-20', '150', 'out', 'operating_payment', 0),
        ]);

        $result = (new FundingMeasures)->calculate($ledger, $this->policy());

        self::assertSame('0.00', $result['cash_deficit']);
    }

    /** The shortfall already contains the deficit: a balance that went
     * negative is further below the target than any positive balance, so the
     * two measures are never summed.
     */
    public function test_reserve_shortfall_contains_the_cash_deficit_rather_than_adding_to_it(): void
    {
        $ledger = $this->ledger('100', [
            new CashMovement('spend', 'plan', '2027-01-20', '150', 'out', 'operating_payment', 0),
        ]);

        // B = 1000 x 3 = 3000; Q = 10% x (3000 + 500) = 350; target = 3350.
        $policy = new ReservePolicy('1000', 3, false, '500', '0.10', 'three months of fixed costs excluding owner pay');
        $result = (new FundingMeasures)->calculate($ledger, $policy);

        self::assertSame('3350.00', $result['reserve_target']);
        self::assertSame('3400.00', $result['reserve_shortfall'], 'target 3350 less the -50 trough');
        self::assertSame('2027-01-20', $result['reserve_shortfall_date']);

        $sum = DriverNumbers::readSigned($result['cash_deficit'])->plus(DriverNumbers::readSigned($result['reserve_shortfall']));
        self::assertSame('3450.00', DriverNumbers::money($sum), 'adding the two would overstate the requirement by the deficit');
    }

    public function test_cash_ahead_of_the_reserve_target_reports_no_shortfall(): void
    {
        $ledger = $this->ledger('20000', []);
        $policy = new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay');

        $result = (new FundingMeasures)->calculate($ledger, $policy);

        self::assertSame('3300.00', $result['reserve_target']);
        self::assertSame('0.00', $result['reserve_shortfall']);
        self::assertNull($result['reserve_shortfall_date']);
    }

    public function test_unverified_funding_is_a_missing_input_not_a_balancing_plug(): void
    {
        $ledger = $this->ledger('100', [
            new CashMovement('spend', 'plan', '2027-01-10', '150', 'out', 'operating_payment', 0),
            new CashMovement('hoped', 'investor', '2027-01-05', '500', 'in', 'funding', 1),
        ]);

        $result = (new FundingMeasures)->calculate($ledger, $this->policy());

        self::assertSame('cannot_verify', $result['status']);
        self::assertNull($result['cash_deficit'], 'an unverified source cannot close a gap it has not proven');
        self::assertContains('cash.hoped.verification', $result['missing']);
    }

    public function test_an_incomplete_reserve_policy_withholds_the_target(): void
    {
        $result = (new FundingMeasures)->calculate(
            $this->ledger('100', []),
            new ReservePolicy('1000', 3, null, null, '0.10', null),
        );

        self::assertSame('cannot_verify', $result['status']);
        self::assertNull($result['reserve_target']);
        self::assertSame(['reserve.basis', 'reserve.includes_owner_pay'], $result['missing']);
    }

    public function test_the_policy_records_its_basis_and_whether_owner_pay_is_included(): void
    {
        $policy = new ReservePolicy('1000', 3, true, '500', '0.10', 'three months of fixed costs including owner pay');
        $payload = $policy->jsonSerialize();

        self::assertSame('3000.00', DriverNumbers::money($policy->baseReserve()));
        self::assertSame('350.00', DriverNumbers::money($policy->contingency()));
        self::assertTrue($payload['includes_owner_pay']);
        self::assertSame('three months of fixed costs including owner pay', $payload['basis']);
    }

    /** @param list<CashMovement> $movements */
    private function ledger(string $opening, array $movements): array
    {
        return (new CashLedger)->calculate(
            new ForecastCalendar('2027-01', 2),
            new CashOpening($opening, 'reviewed bank statement', true),
            $movements,
        );
    }

    private function policy(): ReservePolicy
    {
        return new ReservePolicy('1000', 3, false, null, '0.10', 'three months of fixed costs excluding owner pay');
    }
}
