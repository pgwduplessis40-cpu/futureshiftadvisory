<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CollectionSchedule;
use App\Services\Entrepreneurs\Forecast\CollectionTerms;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use PHPUnit\Framework\TestCase;

/**
 * The cash leg forecast revenue never had.
 *
 * Before this the ledger's operating receipts came only from trade items —
 * opening debtors and one-offs typed as amounts — so revenue the model
 * projected never became cash, a revenue downside barely moved the cash
 * position, and a funding ask sized against that position understated what
 * the business needed.
 */
final class CollectionScheduleTest extends TestCase
{
    public function test_revenue_billed_in_a_month_is_collected_on_the_stated_terms(): void
    {
        // 4 units at 1200 is 4800 billed at the end of January; a fifth of it
        // on the invoice, the rest thirty days later.
        $result = $this->calculate(new CollectionTerms(30, '20', 'Engagement letter'));

        self::assertSame(['2026-01-31', '2026-03-02'], array_column($result['movements'], 'date'));
        self::assertSame(['960.00', '3840.00'], array_column($result['movements'], 'amount'));
        self::assertSame(['in', 'in'], array_column($result['movements'], 'direction'));
        self::assertSame(['operating_receipt', 'operating_receipt'], array_column($result['movements'], 'category'));
    }

    /** The two parts add back to the amount billed however the share rounds,
     * because the balance is the remainder rather than a second percentage of
     * the bill.
     */
    public function test_the_parts_add_back_to_the_amount_billed(): void
    {
        $result = $this->calculate(new CollectionTerms(14, '33', 'Engagement letter'));
        $total = array_sum(array_map('floatval', array_column($result['movements'], 'amount')));

        self::assertEqualsWithDelta(4800.0, $total, 0.005, 'A third collected at invoice loses or invents cents.');
    }

    /** Billing falls at month end, so a thirty day term in a thirty-one day
     * month lands in the month after next rather than the next one.
     */
    public function test_billing_falls_at_month_end(): void
    {
        $result = $this->calculate(new CollectionTerms(0, '100', 'Engagement letter'));

        self::assertSame(['2026-01-31'], array_column($result['movements'], 'date'));
    }

    /** Nothing is assumed about a model that has not said how it collects.
     * Before these terms every reconstruction was in this position, and the
     * schedule leaves it there rather than collecting revenue the day it is
     * earned.
     */
    public function test_unstated_terms_collect_nothing(): void
    {
        $result = $this->calculate(null);

        self::assertSame([], $result['movements']);
        self::assertSame([], $result['missing'], 'Unstated is not the same as incomplete.');
    }

    /** Partly stated terms schedule nothing and say which half is missing. A
     * day count with no invoice share leaves the split unknown; a share with
     * no day count leaves the balance undated.
     */
    public function test_partly_stated_terms_schedule_nothing_and_report_the_gap(): void
    {
        $result = $this->calculate(new CollectionTerms(30, null, 'Engagement letter'));

        self::assertSame([], $result['movements']);
        self::assertSame(['on_invoice_percent'], $result['missing'],
            'The gap names its field; the caller names the subject once.');
    }

    /** A stream month nobody has billed is not a month billed nothing, and a
     * priced stream with no units states no revenue to collect.
     */
    public function test_a_month_with_no_invoiced_units_is_reported_rather_than_collected(): void
    {
        $calendar = new ForecastCalendar('2026-01', 1);
        $stream = new RevenueStream('intensive', 'Intensive', 'day', '1200', '2', '6', true, 'own_record', [
            '2026-01' => new StreamMonth('4', '4', null, '24', '0'),
        ]);

        $result = (new CollectionSchedule)->calculate($calendar, [$stream],
            new CollectionTerms(30, '20', 'Engagement letter'));

        self::assertSame([], $result['movements']);
        self::assertSame(['intensive.2026-01.invoiced_units'], $result['missing']);
    }

    /** Each receipt is identifiable once. The ledger refuses two movements
     * claiming one position, and a reconciliation counts a repeated source
     * line as the same entry twice.
     */
    public function test_each_receipt_is_identified_separately(): void
    {
        $calendar = new ForecastCalendar('2026-01', 2);
        $schedule = [
            '2026-01' => new StreamMonth('4', '4', '4', '24', '0'),
            '2026-02' => new StreamMonth('4', '4', '4', '24', '0'),
        ];
        $streams = [
            new RevenueStream('intensive', 'Intensive', 'day', '1200', '2', '6', true, 'own_record', $schedule),
            new RevenueStream('retainer', 'Retainer', 'month', '1200', '2', '6', true, 'own_record', $schedule),
        ];

        $result = (new CollectionSchedule)->calculate($calendar, $streams,
            new CollectionTerms(30, '20', 'Engagement letter'));
        $ids = array_column($result['movements'], 'id');

        self::assertCount(8, $ids);
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    /** @return array{movements:list<CashMovement>,invoiced:array<string,string>,missing:list<string>} */
    private function calculate(?CollectionTerms $terms): array
    {
        $calendar = new ForecastCalendar('2026-01', 1);
        $stream = new RevenueStream('intensive', 'Intensive', 'day', '1200', '2', '6', true, 'own_record', [
            '2026-01' => new StreamMonth('4', '4', '4', '24', '0'),
        ]);

        return (new CollectionSchedule)->calculate($calendar, [$stream], $terms);
    }
}
