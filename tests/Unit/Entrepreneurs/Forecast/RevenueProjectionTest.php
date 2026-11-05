<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueProjection;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use PHPUnit\Framework\TestCase;

final class RevenueProjectionTest extends TestCase
{
    public function test_independent_fixture_shares_founder_capacity_and_separates_upside_and_invoice_volumes(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/revenue-drivers.json'), true, flags: JSON_THROW_ON_ERROR);
        $streams = array_map(fn (array $row): RevenueStream => new RevenueStream(
            $row['id'], $row['name'], $row['unit'], $row['price'], $row['preparation_hours'], $row['delivery_hours'],
            $row['in_base_case'], 'founder_assumption', ['2027-01' => new StreamMonth(...array_values($row['month']))],
        ), $fixture['streams']);
        $result = (new RevenueProjection)->calculate(new ForecastCalendar(...array_values($fixture['calendar'])), $streams,
            ['2027-01' => new FounderMonth(...array_values($fixture['founder']))]);
        $month = $result['months'][0];

        foreach (['base_revenue', 'base_contractor_cost', 'founder_hours_allocated', 'status'] as $key) {
            self::assertSame($fixture['expected'][$key], $month[$key]);
        }
        self::assertSame('revenue_drivers_only', $result['scope']);
        self::assertSame('NZD', $result['currency']);
        self::assertSame('GST exclusive', $result['tax_basis']);
        self::assertSame('4.00000000', $month['streams'][0]['founder_capacity_units']);
        self::assertSame('3.00000000', $month['streams'][1]['founder_capacity_units']);
        self::assertSame('5', $month['streams'][0]['booked_units']);
        self::assertSame('5', $month['streams'][0]['requested_delivery_units']);
        self::assertSame('4', $month['streams'][0]['invoiced_units']);
        self::assertSame($fixture['expected']['upside_potential_revenue'], $month['streams'][2]['revenue']);
        self::assertSame([], $month['missing']);
        self::assertSame([], $month['errors']);
        self::assertSame([], $fixture['enabled_rule_ids']);
    }

    public function test_overallocated_shared_hours_fail_without_silently_prioritising_a_stream(): void
    {
        $result = $this->calculate([
            $this->stream('a', month: new StreamMonth('1', '1', '1', '40', '0')),
            $this->stream('b', month: new StreamMonth('1', '1', '1', '40', '0')),
        ], new FounderMonth('80', '16', '8'));
        self::assertSame('fail', $result['status']);
        self::assertSame(['founder.shared_hours_exceeded'], $result['errors']);
        self::assertNull($result['base_revenue']);
        self::assertNull($result['base_contractor_cost']);
        self::assertSame('104', $result['founder_hours_allocated']);
        self::assertSame('100.00', $result['streams'][0]['revenue']);
    }

    public function test_explicit_zero_capacity_and_zero_price_are_not_missing_or_unlimited(): void
    {
        $result = $this->calculate([$this->stream(month: new StreamMonth('5', '5', '0', '0', '0'))], new FounderMonth('0', '0', '0'));
        self::assertSame('pass', $result['status']);
        self::assertSame('0.00', $result['base_revenue']);
        self::assertSame('0.00', $result['base_contractor_cost']);
        $free = $this->calculate([$this->stream(price: '0')]);
        self::assertSame('pass', $free['status']);
        self::assertSame('0.00', $free['base_revenue']);
    }

    public function test_unknown_capacity_membership_and_price_remain_unknown(): void
    {
        foreach ([$this->stream(price: null), $this->stream(membership: null),
            $this->stream(month: new StreamMonth('1', '1', '1', null, '0')),
            $this->stream(month: new StreamMonth('1', '1', '1', '8', null))] as $stream) {
            $result = $this->calculate([$stream]);
            self::assertSame('cannot_verify', $result['status']);
            self::assertNull($result['base_revenue']);
            self::assertNotEmpty($result['missing']);
        }
        $unknownFounder = $this->calculate([$this->stream()], new FounderMonth(null, '0', '0'));
        self::assertNull($unknownFounder['base_revenue']);
        self::assertContains('founder.available_hours', $unknownFounder['missing']);
    }

    public function test_missing_bookings_and_invoice_schedules_do_not_erase_known_delivery_arithmetic(): void
    {
        $result = $this->calculate([$this->stream(month: new StreamMonth(null, '1', null, '8', '0'))]);
        self::assertSame('cannot_verify', $result['status']);
        self::assertSame('100.00', $result['base_revenue']);
        self::assertContains('a.booked_units', $result['missing']);
        self::assertContains('a.invoiced_units', $result['missing']);
        self::assertNull($result['streams'][0]['booked_units']);
    }

    public function test_missing_upside_inputs_do_not_become_base_inputs_or_hide_base_missing_membership(): void
    {
        $upside = new RevenueStream('upside', 'Excluded offer', null, null, null, null, false, null, []);
        $result = $this->calculate([$this->stream(), $upside]);
        self::assertSame('pass', $result['status']);
        self::assertSame('100.00', $result['base_revenue']);
        self::assertNotEmpty($result['streams'][1]['missing']);
        self::assertNull($result['streams'][1]['revenue']);
    }

    public function test_explicit_ramp_and_month_thirteen_are_not_filled_from_growth_or_month_twelve(): void
    {
        $calendar = new ForecastCalendar('2027-01', 14);
        $schedule = [];
        $founder = [];
        foreach ($calendar->periods as $index => $period) {
            $units = (string) ($index + 1);
            $schedule[$period] = new StreamMonth($units, $units, $units, '160', '0');
            $founder[$period] = new FounderMonth('160', '0', '0');
        }
        $stream = new RevenueStream('a', 'A', 'day', '1500', '2', '6', true, 'founder_assumption', $schedule);
        $result = (new RevenueProjection)->calculate($calendar, [$stream], $founder);
        self::assertSame(['1500.00', '3000.00', '4500.00'], array_column(array_slice($result['months'], 0, 3), 'base_revenue'));
        self::assertSame('18000.00', $result['months'][11]['base_revenue']);
        self::assertSame('19500.00', $result['months'][12]['base_revenue']);
        self::assertSame('2028-01', $result['months'][12]['period']);
        $missing = (new RevenueProjection)->calculate($calendar, [$this->stream()], ['2027-01' => new FounderMonth('80', '0', '0')]);
        self::assertSame('cannot_verify', $missing['months'][12]['status']);
        self::assertNull($missing['months'][12]['base_revenue']);
    }

    public function test_input_hash_is_order_independent_but_changes_with_schedule_and_evidence(): void
    {
        $calendar = new ForecastCalendar('2027-01', 2);
        $first = new FounderMonth('80', '0', '0');
        $founder = ['2027-01' => $first, '2027-02' => $first];
        $a = $this->stream('a');
        $b = $this->stream('b');
        $engine = new RevenueProjection;
        $original = $engine->calculate($calendar, [$a, $b], $founder);
        self::assertSame($original, $engine->calculate($calendar, [$b, $a], array_reverse($founder, true)));
        $changed = $engine->calculate($calendar, [$a, $this->stream('b', price: '101')], $founder);
        self::assertNotSame($original['input_hash'], $changed['input_hash']);
        $evidence = new RevenueStream('b', 'Service b', 'session', '100', '2', '6', true, 'own_record', $b->schedule);
        self::assertNotSame($original['input_hash'], $engine->calculate($calendar, [$a, $evidence], $founder)['input_hash']);
        self::assertSame(1, $original['schema_version']);
        self::assertSame(RevenueProjection::VERSION, $original['engine_version']);
    }

    public function test_duplicate_streams_are_rejected_instead_of_counted_twice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculate([$this->stream(), $this->stream()]);
    }

    public function test_out_of_horizon_schedule_is_rejected_instead_of_discarded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RevenueProjection)->calculate(new ForecastCalendar('2027-02', 1), [$this->stream()], []);
    }

    public function test_no_streams_can_have_explicit_zero_revenue_but_unknown_founder_data_stays_visible(): void
    {
        self::assertSame('0.00', $this->calculate([])['base_revenue']);
        $result = (new RevenueProjection)->calculate(new ForecastCalendar('2027-01', 1), [], []);
        self::assertSame('cannot_verify', $result['months'][0]['status']);
        self::assertNull($result['months'][0]['base_revenue']);
    }

    public function test_invalid_conversion_still_fails_when_other_inputs_are_missing(): void
    {
        $stream = new RevenueStream('a', 'Service A', 'session', '100', '0', '0', true, 'founder_assumption',
            ['2027-01' => new StreamMonth(null, '1', '1', '8', '0')]);
        $result = $this->calculate([$stream], new FounderMonth(null, '0', '0'));
        self::assertSame('fail', $result['status']);
        self::assertContains('a.hours_per_unit_must_be_positive', $result['errors']);
        self::assertContains('founder.available_hours', $result['missing']);
        self::assertContains('a.booked_units', $result['missing']);
        self::assertNull($result['base_revenue']);
    }

    private function stream(string $id = 'a', ?string $price = '100', ?bool $membership = true, ?StreamMonth $month = null): RevenueStream
    {
        return new RevenueStream($id, 'Service '.$id, 'session', $price, '2', '6', $membership, 'founder_assumption',
            ['2027-01' => $month ?? new StreamMonth('1', '1', '1', '8', '0')]);
    }

    private function calculate(array $streams, ?FounderMonth $founder = null): array
    {
        return (new RevenueProjection)->calculate(new ForecastCalendar('2027-01', 1), $streams,
            ['2027-01' => $founder ?? new FounderMonth('80', '0', '0')])['months'][0];
    }
}
