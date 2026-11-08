<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
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
