<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\StreamProjection;
use PHPUnit\Framework\TestCase;

final class StreamProjectionTest extends TestCase
{
    public function test_requested_units_are_capped_and_provider_cost_is_charged_only_for_additional_delivery(): void
    {
        $input = new StreamMonth('12', '10', '8', '32', '2', 'Provider A', '300');
        $result = (new StreamProjection)->calculate($this->stream(), $input);
        self::assertSame('6.00000000', $result['delivered_units']);
        self::assertSame('2.00000000', $result['contractor_units']);
        self::assertSame('32.00000000', $result['founder_hours_used']);
        self::assertSame('9000.00', $result['revenue']);
        self::assertSame('600.00', $result['contractor_cost']);
        $underCapacity = (new StreamProjection)->calculate($this->stream(), new StreamMonth('2', '2', '2', '32', '2', 'Provider A', '300'));
        self::assertSame('3000.00', $underCapacity['revenue']);
        self::assertSame('0.00', $underCapacity['contractor_cost']);
    }

    public function test_additional_capacity_needs_identity_rate_and_an_explicit_basis_for_zero_cost(): void
    {
        foreach ([
            new StreamMonth('5', '5', '5', '32', '2', null, '300'),
            new StreamMonth('5', '5', '5', '32', '2', 'Provider', null),
            new StreamMonth('5', '5', '5', '32', '2', 'Provider', '0'),
        ] as $input) {
            $result = (new StreamProjection)->calculate($this->stream(), $input);
            self::assertNotEmpty($result['missing']);
            self::assertNull($result['total_capacity_units']);
            self::assertNull($result['revenue']);
        }
        $documented = (new StreamProjection)->calculate($this->stream(),
            new StreamMonth('5', '5', '5', '32', '2', 'Provider', '0', 'Two units supplied under a documented volunteer agreement'));
        self::assertSame([], $documented['missing']);
        self::assertSame('7500.00', $documented['revenue']);
        self::assertSame('0.00', $documented['contractor_cost']);
    }

    public function test_missing_conversion_and_zero_conversion_have_distinct_outcomes(): void
    {
        $month = new StreamMonth('1', '1', '1', '8', '0');
        $unknown = (new StreamProjection)->calculate($this->stream(prep: null), $month);
        self::assertContains('preparation_hours_per_unit', $unknown['missing']);
        self::assertSame([], $unknown['errors']);
        self::assertNull($unknown['revenue']);
        $zero = (new StreamProjection)->calculate($this->stream(prep: '0', delivery: '0'), $month);
        self::assertSame([], $zero['missing']);
        self::assertSame(['hours_per_unit_must_be_positive'], $zero['errors']);
        self::assertNull($zero['revenue']);
        $unitless = (new StreamProjection)->calculate($this->stream(unit: null), $month);
        self::assertContains('unit', $unitless['missing']);
        self::assertNull($unitless['revenue']);
    }

    public function test_absent_month_is_not_a_zero_sales_month(): void
    {
        $result = (new StreamProjection)->calculate($this->stream(), null);
        self::assertNull($result['booked_units']);
        self::assertNull($result['delivered_units']);
        self::assertNull($result['revenue']);
        self::assertNull($result['contractor_cost']);
        self::assertContains('delivery_units', $result['missing']);
    }

    public function test_fractional_capacity_rounds_down_without_exceeding_allocated_hours(): void
    {
        $result = (new StreamProjection)->calculate($this->stream(price: '3', prep: '1', delivery: '2'),
            new StreamMonth('1', '1', '1', '1', '0'));
        self::assertSame('0.33333333', $result['delivered_units']);
        self::assertSame('0.99999999', $result['founder_hours_used']);
        self::assertSame('1.00', $result['revenue']);
    }

    public function test_line_money_uses_decimal_half_up_rounding(): void
    {
        $result = (new StreamProjection)->calculate($this->stream(price: '0.335'), new StreamMonth('3', '3', '3', '0', '3', 'Provider', '0.005'));
        self::assertSame('1.01', $result['revenue']);
        self::assertSame('0.02', $result['contractor_cost']);
    }

    private function stream(?string $price = '1500', ?string $prep = '2', ?string $delivery = '6', ?string $unit = 'day'): RevenueStream
    {
        return new RevenueStream('a', 'Service A', $unit, $price, $prep, $delivery, true, 'founder_assumption', []);
    }
}
