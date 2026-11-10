<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\MonthlyTaxChain;
use PHPUnit\Framework\TestCase;

final class MonthlyTaxChainTest extends TestCase
{
    private const MONTHS = ['2026-01', '2026-02', '2026-03'];

    public function test_a_loss_raises_the_carried_balance_rather_than_refunding_tax(): void
    {
        $result = (new MonthlyTaxChain)->calculate(
            self::MONTHS,
            ['2026-01' => '-2000', '2026-02' => '1000', '2026-03' => '8000'],
            array_fill_keys(self::MONTHS, '0.00'),
            '28', '4500', true,
        );

        self::assertSame('pass', $result['status']);
        // The loss is carried, not paid back: no month states a negative
        // expense, and the balance rises by the whole loss.
        self::assertSame(['0.00', '0.00', '700.00'], array_column($result['months'], 'expense'));
        self::assertSame(['6500.00', '5500.00', '0.00'], array_column($result['months'], 'loss_closing'));
    }

    public function test_losses_are_not_carried_where_the_policy_says_they_are_not_eligible(): void
    {
        $result = (new MonthlyTaxChain)->calculate(
            self::MONTHS,
            ['2026-01' => '-2000', '2026-02' => '1000', '2026-03' => '1000'],
            array_fill_keys(self::MONTHS, '0.00'),
            '28', '0', false,
        );

        // The January loss raises nothing, so February and March are taxed in
        // full. Nothing is refunded for the loss either.
        self::assertSame(['0.00', '280.00', '280.00'], array_column($result['months'], 'expense'));
        self::assertSame(['0.00', '0.00', '0.00'], array_column($result['months'], 'loss_closing'));
    }

    public function test_an_adjustment_changes_the_taxable_profit_it_is_stated_against(): void
    {
        $result = (new MonthlyTaxChain)->calculate(
            ['2026-01'], ['2026-01' => '1000'], ['2026-01' => '500'], '28', '0', true,
        );

        self::assertSame('1500.00', $result['months'][0]['taxable']);
        self::assertSame('420.00', $result['months'][0]['expense']);
    }

    public function test_a_month_that_cannot_be_established_stops_the_chain_rather_than_reading_as_zero(): void
    {
        $result = (new MonthlyTaxChain)->calculate(
            self::MONTHS,
            ['2026-01' => '1000', '2026-03' => '1000'],
            array_fill_keys(self::MONTHS, '0.00'),
            '28', '0', true,
        );

        self::assertSame('cannot_verify', $result['status']);
        self::assertContains('2026-02.profit', $result['missing']);
        // March is left unstated too. Its balance would otherwise be carried
        // forward from a month nobody recorded.
        self::assertSame(['280.00', null, null], array_column($result['months'], 'expense'));
    }

    public function test_an_unstated_rate_or_opening_balance_establishes_no_month_at_all(): void
    {
        $chain = new MonthlyTaxChain;
        $profits = ['2026-01' => '1000'];

        foreach ([[null, '0'], ['28', null]] as [$rate, $opening]) {
            $result = $chain->calculate(['2026-01'], $profits, ['2026-01' => '0.00'], $rate, $opening, true);
            self::assertSame('cannot_verify', $result['status']);
            self::assertNull($result['months'][0]['expense']);
            // The profit it was given is still reported, so the gap names the
            // policy rather than looking like a missing month.
            self::assertSame('1000.00', $result['months'][0]['profit']);
        }
    }
}
