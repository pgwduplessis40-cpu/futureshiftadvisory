<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use PHPUnit\Framework\TestCase;

final class ForecastRunTest extends TestCase
{
    public function test_a_complete_reconstruction_projects_every_month(): void
    {
        $run = (new ForecastRun)->revenue(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 2),
            streams: [$this->stream(['2026-01' => $this->month(), '2026-02' => $this->month()])],
            founderMonths: ['2026-01' => $this->capacity(), '2026-02' => $this->capacity()],
        ));

        self::assertSame('pass', $run['status']);
        self::assertCount(2, $run['months']);
        // Two units at 450 each, with the founder's twenty hours covering both
        // at ten hours a unit.
        self::assertSame('900.00', $run['months'][0]['base_revenue']);
        self::assertSame('900.00', $run['months'][1]['base_revenue']);
        self::assertSame([], $run['missing']);
        self::assertSame([], $run['errors']);
        self::assertNull($run['refusal']);
    }

    public function test_a_reconstruction_with_no_streams_is_unverifiable_rather_than_empty(): void
    {
        $run = (new ForecastRun)->revenue(new ForecastModelInputs(calendar: new ForecastCalendar('2026-01', 12)));

        self::assertSame('cannot_verify', $run['status'], 'Nothing to project is not the same as a projection of nothing.');
        self::assertSame([], $run['months']);
        self::assertStringContainsString('No revenue streams', $run['missing'][0]['reason']);
    }

    public function test_gaps_are_counted_by_reason_rather_than_repeated_every_month(): void
    {
        $run = (new ForecastRun)->revenue(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            streams: [$this->stream(['2026-01' => $this->month(), '2026-02' => $this->month(), '2026-03' => $this->month()])],
            // No founder capacity recorded for any of the three months.
        ));

        self::assertSame('cannot_verify', $run['status']);
        $reasons = array_column($run['missing'], 'reason');
        self::assertSame(['founder.available_hours', 'founder.administration_hours', 'founder.business_development_hours'], $reasons,
            'Three reasons across three months must not be reported as nine separate gaps.');
        foreach ($run['missing'] as $gap) {
            self::assertSame(3, $gap['months']);
            self::assertSame('2026-01', $gap['first_period']);
        }
    }

    public function test_a_reconstruction_the_engine_refuses_is_reported_rather_than_thrown(): void
    {
        $run = (new ForecastRun)->revenue(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            // A month beyond the horizon. The projection refuses the whole
            // reconstruction rather than discarding the month, and an advisor
            // reading the screen must see that as a verdict, not a crash.
            streams: [$this->stream(['2027-05' => $this->month()])],
        ));

        self::assertSame('fail', $run['status']);
        self::assertNotNull($run['refusal']);
        self::assertStringContainsString('beyond the declared calendar', $run['refusal']);
        self::assertSame([], $run['months']);
    }

    public function test_an_upside_stream_is_left_out_of_the_base_revenue_it_was_excluded_from(): void
    {
        $base = $this->stream(['2026-01' => $this->month()]);
        $upside = new RevenueStream('retreats', 'Retreats', 'retreat', '1000', '4', '6', false, 'founder_assumption',
            ['2026-01' => $this->month()]);

        $run = (new ForecastRun)->revenue(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 1),
            streams: [$base, $upside],
            founderMonths: ['2026-01' => $this->capacity()],
        ));

        self::assertSame('pass', $run['status']);
        self::assertSame('900.00', $run['months'][0]['base_revenue'], 'Upside is a separate potential line, not part of the base case.');
    }

    public function test_cash_cannot_be_run_without_an_opening_position(): void
    {
        $run = (new ForecastRun)->cash(new ForecastModelInputs(calendar: new ForecastCalendar('2026-01', 3)));

        self::assertSame('cannot_verify', $run['status']);
        self::assertStringContainsString('No opening cash position', $run['missing'][0]['reason']);
        self::assertNull($run['lowest_cash']);
    }

    public function test_settlements_and_asset_purchases_landing_on_one_day_both_reach_the_ledger(): void
    {
        // The ledger positions a movement by its date and sequence together
        // and refuses two in one position. Settlements arrive carrying the
        // numbers the trade form gave them, so an asset numbered from zero
        // would collide with the first settlement whenever they share a date.
        $run = (new ForecastRun)->cash(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            tradeItems: [$this->receivable('2026-02-10')],
            assets: [$this->asset('2026-02-10')],
        ));

        self::assertNull($run['refusal'], 'Two movements on one day must hold different positions.');
        self::assertSame(2, $run['movements_counted']);
        // 5000 opening, 900 received, 2400 paid for the asset.
        self::assertSame('3500.00', $run['lowest_cash']);
    }

    public function test_cash_already_in_the_opening_balance_is_counted_out_rather_than_counted_twice(): void
    {
        $run = (new ForecastRun)->cash(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            tradeItems: [$this->receivable('2025-12-20')],
        ));

        self::assertSame(1, $run['settled_before_opening']);
        self::assertSame(0, $run['movements_counted']);
        self::assertNull($run['refusal'], 'The ledger refuses cash dated before it opens, so it must not be handed any.');
        self::assertSame('5000.00', $run['lowest_cash']);
    }

    public function test_owner_pay_without_its_date_and_source_is_reported_rather_than_guessed(): void
    {
        $steps = [new OwnerPayStep('2026-01-01', '800', 'base')];
        $run = (new ForecastRun)->cash(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            ownerPaySteps: $steps,
        ));

        self::assertSame('cannot_verify', $run['status']);
        self::assertSame(0, $run['movements_counted'], 'A pay schedule with no start date must not invent one.');
        self::assertContains('owner_pay.source_and_first_payment_date', array_column($run['missing'], 'reason'));
    }

    public function test_owner_pay_with_its_date_and_source_reaches_the_ledger_weekly(): void
    {
        $run = (new ForecastRun)->cash(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 2),
            cashOpening: new CashOpening('50000', 'Bank statement to 31 December 2025', true),
            ownerPaySteps: [new OwnerPayStep('2026-01-01', '800', 'base')],
            reservePolicy: new ReservePolicy('1000', 3, true, '500', '0.10', 'Monthly fixed costs including owner pay'),
            ownerPaySource: 'Signed owner drawings policy',
            ownerPayFirstPaymentOn: '2026-01-02',
        ));

        self::assertNull($run['refusal']);
        self::assertSame(9, $run['movements_counted'], 'Nine Fridays fall in the two months from 2 January 2026.');
        self::assertNotNull($run['reserve_target'], 'A complete reserve policy produces a target to measure against.');
        self::assertSame('42800.00', $run['lowest_cash']);
    }

    public function test_the_run_carries_each_month_its_own_receipts_payments_and_positioned_lines(): void
    {
        $run = (new ForecastRun)->cash(new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 3),
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            tradeItems: [$this->receivable('2026-02-10')],
            assets: [$this->asset('2026-02-10')],
        ));

        // The month's own receipts and payments, not a difference taken back
        // off the two balances. A caller reconciling opening plus movement
        // against closing needs the ledger's arithmetic, not its own.
        self::assertSame(['2026-01', '0.00', '0.00'],
            [$run['months'][0]['period'], $run['months'][0]['receipts'], $run['months'][0]['payments']],
            'A month nothing moved in received nothing and paid nothing, which is not the same as unknown.');
        self::assertSame('900.00', $run['months'][1]['receipts']);
        self::assertSame('2400.00', $run['months'][1]['payments']);
        self::assertSame('5000.00', $run['months'][1]['opening_cash']);
        self::assertSame('3500.00', $run['months'][1]['closing_cash']);

        // The positioned lines themselves, so a caller can report the dated
        // and classified movement behind a month and not only its total.
        self::assertSame(['invoice-1.payment', 'laptop.purchase'],
            array_map(fn (CashMovement $movement): string => $movement->id, $run['movements']));
    }

    private function receivable(string $settledOn): TradeItem
    {
        return new TradeItem('invoice-1', 'Sales ledger export', 'receivable', '2026-01-05', '900', [
            new CashMovement('invoice-1.payment', 'Bank statement', $settledOn, '900', 'in', 'operating_receipt', 0, true),
        ]);
    }

    private function asset(string $paidOn): AssetInput
    {
        return new AssetInput('laptop', 'Supplier invoice 8831', '2400', 'capital', 'Capitalisation policy v2',
            true, $paidOn, '2026-02-01', 36, '200', 'straight_line_full_start_month', '0');
    }

    /** @param array<string,StreamMonth> $schedule */
    private function stream(array $schedule): RevenueStream
    {
        return new RevenueStream('workshops', 'Weekend workshops', 'workshop', '450', '4', '6', true, 'own_record', $schedule);
    }

    private function month(): StreamMonth
    {
        return new StreamMonth('2', '2', '2', '20', '0');
    }

    private function capacity(): FounderMonth
    {
        return new FounderMonth('120', '20', '15');
    }
}
