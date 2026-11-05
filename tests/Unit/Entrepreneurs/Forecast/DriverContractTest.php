<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\DriverNumbers;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DriverContractTest extends TestCase
{
    public function test_default_calendar_has_thirty_six_explicit_months_across_year_and_leap_boundaries(): void
    {
        $calendar = new ForecastCalendar('2027-12');
        self::assertCount(36, $calendar->periods);
        self::assertSame(['2027-12', '2028-01', '2028-02', '2028-03'], array_slice($calendar->periods, 0, 4));
        self::assertSame('2030-11', $calendar->periods[35]);
        self::assertSame(['start_month' => '2027-12', 'months' => 36], $calendar->jsonSerialize());
    }

    #[DataProvider('invalidCalendars')]
    public function test_invalid_calendar_input_does_not_fall_back_to_today(string $start, int $months): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ForecastCalendar($start, $months);
    }

    public static function invalidCalendars(): array
    {
        return [['', 36], ['2027-13', 36], ['2027-1', 36], ['2027-01-01', 36], ['2027-01', 0], ['2027-01', 61]];
    }

    #[DataProvider('invalidNumbers')]
    public function test_bad_driver_values_are_rejected_instead_of_coerced_to_zero(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DriverNumbers::read($value);
    }

    public static function invalidNumbers(): array
    {
        return [[''], [' '], ['-1'], ['NaN'], ['1e3'], ['1,000'], ['1.123456789'], ['1000000000000000000']];
    }

    public function test_unknown_and_explicit_zero_survive_the_input_contract(): void
    {
        self::assertNull(DriverNumbers::read(null));
        self::assertSame('0', (string) DriverNumbers::read('0'));
        self::assertSame('0.00000001', (string) DriverNumbers::read('0.00000001'));
        self::assertSame('999999999999999999', (string) DriverNumbers::read('999999999999999999'));
        $founder = new FounderMonth(null, '0', null);
        self::assertNull($founder->jsonSerialize()['available_hours']);
        self::assertSame('0', $founder->jsonSerialize()['administration_hours']);
    }

    #[DataProvider('invalidStreamIdentities')]
    public function test_stream_identity_and_evidence_vocabulary_are_explicit(string $id, string $name, ?string $evidence): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RevenueStream($id, $name, 'day', '10', '1', '7', true, $evidence, []);
    }

    public static function invalidStreamIdentities(): array
    {
        return [['', 'A', null], ['a', ' ', null], ['a', 'A', 'confirmed-by-ai']];
    }

    public function test_negative_provider_cost_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StreamMonth('1', '1', '1', '8', '1', 'Provider', '-20');
    }
}
