<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\MonthlyProfit;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use PHPUnit\Framework\TestCase;

final class MonthlyProfitTest extends TestCase
{
    private const PERIODS = ['2026-01', '2026-02', '2026-03'];

    public function test_streams_and_trade_items_are_added_rather_than_reconciled(): void
    {
        // Trade items are opening debtors and one-offs, separate from the
        // streams. A one-off invoice raised in a month is revenue on top of
        // what the streams earned, not a restatement of it.
        $result = $this->calculate(items: [$this->receivable('invoice-1', '2026-02', '900')]);

        self::assertSame('pass', $result['status']);
        self::assertSame(['0.00', '900.00', '0.00'], array_column($result['months'], 'one_off_income'));
        self::assertSame(['900.00', '1800.00', '900.00'], array_column($result['months'], 'profit'));
    }

    public function test_an_item_raised_before_the_forecast_opens_is_an_opening_debtor(): void
    {
        // Its cash arrives inside the horizon, but the revenue was earned
        // before it, so no month's profit changes.
        $result = $this->calculate(items: [$this->receivable('invoice-1', '2025-11', '900')]);

        self::assertSame(['900.00', '900.00', '900.00'], array_column($result['months'], 'profit'));
    }

    public function test_a_capital_purchase_is_not_charged_to_the_month_it_was_invoiced_in(): void
    {
        // It reaches profit as depreciation. Charging the invoice as well
        // would charge the same spend twice.
        $capital = new TradeItem('laptop', 'Invoice 8831', 'payable', '2026-02-15', '2400',
            [new CashMovement('laptop.payment', 'Invoice 8831', '2026-03-01', '2400', 'out', 'capex', 0)]);
        $operating = new TradeItem('repair', 'Invoice 9001', 'payable', '2026-02-20', '300',
            [new CashMovement('repair.payment', 'Invoice 9001', '2026-03-01', '300', 'out', 'operating_payment', 1)]);

        $result = $this->calculate(items: [$capital, $operating]);

        self::assertSame(['0.00', '300.00', '0.00'], array_column($result['months'], 'one_off_costs'));
    }

    /**
     * Interest is a financing charge, not a cost of trading.
     *
     * It is charged to the month either way, so the profit is the same; what
     * changes is that a decomposition reading revenue less costs, depreciation
     * and interest can now state it, which it cannot do from a figure with the
     * interest buried inside it.
     */
    public function test_interest_is_charged_to_profit_but_reported_apart_from_trading_costs(): void
    {
        $interest = new TradeItem('loan-interest', 'Statement 12', 'payable', '2026-02-10', '250',
            [new CashMovement('loan-interest.payment', 'Bank', '2026-02-20', '250', 'out', 'interest', 0)]);
        $operating = new TradeItem('repair', 'Invoice 9001', 'payable', '2026-02-20', '300',
            [new CashMovement('repair.payment', 'Invoice 9001', '2026-03-01', '300', 'out', 'operating_payment', 1)]);

        $result = $this->calculate(items: [$interest, $operating]);

        self::assertSame(['0.00', '250.00', '0.00'], array_column($result['months'], 'interest'));
        self::assertSame(['0.00', '300.00', '0.00'], array_column($result['months'], 'one_off_costs'),
            'Interest counted among the trading costs as well would charge it twice.');
        // 1000 revenue less 100 contractor cost, less 300 operating and 250
        // interest in the middle month.
        self::assertSame(['900.00', '350.00', '900.00'], array_column($result['months'], 'profit'));
    }

    public function test_an_item_whose_terms_nobody_recorded_withholds_its_month(): void
    {
        // It is known to be money owed and not known to be an operating cost
        // rather than a capital purchase, and the two reach profit in
        // entirely different ways.
        $result = $this->calculate(items: [new TradeItem('unknown', 'Invoice 1', 'payable', '2026-02-10', '500', null)]);

        self::assertSame('cannot_verify', $result['status']);
        self::assertContains('unknown.category', $result['missing']);
    }

    public function test_owner_pay_withholds_only_the_months_that_carry_it(): void
    {
        $pay = ['2026-03' => '3200'];

        // Nobody has said whether it is an expense or a drawing, so March is
        // withheld. January and February charge nothing under either answer.
        $unstated = $this->calculate(ownerPay: $pay, deductible: null);
        self::assertSame(['900.00', '900.00', null], array_column($unstated['months'], 'profit'));
        self::assertContains('owner_pay.deductible', $unstated['missing']);

        // A drawing is not an expense, so it charges nothing anywhere.
        $drawing = $this->calculate(ownerPay: $pay, deductible: false);
        self::assertSame(['900.00', '900.00', '900.00'], array_column($drawing['months'], 'profit'));

        $expense = $this->calculate(ownerPay: $pay, deductible: true);
        self::assertSame(['900.00', '900.00', '-2300.00'], array_column($expense['months'], 'profit'));
    }

    public function test_a_month_the_projection_cannot_value_states_no_profit(): void
    {
        $result = $this->calculate(revenue: ['2026-01' => ['base_revenue' => null, 'base_contractor_cost' => '0.00']]);

        self::assertSame(null, $result['months'][0]['profit']);
        self::assertContains('2026-01.revenue', $result['missing']);
        self::assertSame('900.00', $result['months'][1]['profit'], 'A later month is unaffected.');
    }

    private function receivable(string $id, string $period, string $amount): TradeItem
    {
        return new TradeItem($id, 'Sales ledger', 'receivable', $period.'-15', $amount,
            [new CashMovement($id.'.payment', 'Bank', $period.'-28', $amount, 'in', 'operating_receipt', 0)]);
    }

    /**
     * @param  array<string,array{base_revenue:string|null,base_contractor_cost:string|null}>  $revenue
     * @param  list<TradeItem>  $items
     * @param  array<string,string>  $ownerPay
     * @return array<string,mixed>
     */
    private function calculate(array $revenue = [], array $items = [], array $ownerPay = [], ?bool $deductible = true): array
    {
        $base = array_fill_keys(self::PERIODS, ['base_revenue' => '1000.00', 'base_contractor_cost' => '100.00']);

        return (new MonthlyProfit)->calculate(
            new ForecastCalendar('2026-01', 3),
            [...$base, ...$revenue],
            array_fill_keys(self::PERIODS, '0.00'),
            $items,
            array_fill_keys(self::PERIODS, ['depreciation' => '0.00', 'expense' => '0.00']),
            $ownerPay,
            $deductible,
        );
    }
}
