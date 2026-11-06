<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelCodec;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use PHPUnit\Framework\TestCase;

final class ForecastModelCodecTest extends TestCase
{
    public function test_a_full_reconstruction_survives_a_round_trip(): void
    {
        $inputs = $this->reconstruction();

        $restored = $this->reload($inputs);

        self::assertSame($this->encoded($inputs), $this->encoded($restored));
    }

    public function test_an_absent_driver_stays_absent_rather_than_becoming_zero(): void
    {
        $inputs = new ForecastModelInputs(
            new ForecastCalendar('2026-01', 12),
            streams: [new RevenueStream('s1', 'Intensive', 'day', null, null, null, null, null, [
                '2026-01' => new StreamMonth(null, null, null, null, null),
            ])],
        );

        $stream = $this->reload($inputs)->streams[0];

        self::assertNull($stream->price, 'A price that was never supplied must not load as a price.');
        self::assertNull($stream->schedule['2026-01']->deliveryUnits);
        self::assertNull($stream->schedule['2026-01']->founderHours);
    }

    public function test_an_explicit_zero_is_not_confused_with_an_absent_value(): void
    {
        $inputs = new ForecastModelInputs(
            new ForecastCalendar('2026-01', 12),
            streams: [new RevenueStream('s1', 'Intensive', 'day', '0', null, null, null, null, [
                '2026-01' => new StreamMonth('0', null, null, null, null),
            ])],
        );

        $stream = $this->reload($inputs)->streams[0];

        self::assertSame('0', $stream->price);
        self::assertSame('0', $stream->schedule['2026-01']->bookedUnits);
        self::assertNull($stream->schedule['2026-01']->deliveryUnits);
    }

    public function test_an_omitted_nested_object_is_distinct_from_one_stored_empty(): void
    {
        $withNothing = $this->reload(new ForecastModelInputs(new ForecastCalendar('2026-01', 12)));
        self::assertNull($withNothing->cashOpening);
        self::assertNull($withNothing->taxPolicy);
        self::assertNull($withNothing->reservePolicy);

        $withEmpty = $this->reload(new ForecastModelInputs(
            new ForecastCalendar('2026-01', 12),
            cashOpening: new CashOpening(null, null, null),
        ));
        self::assertInstanceOf(CashOpening::class, $withEmpty->cashOpening);
        self::assertNull($withEmpty->cashOpening->amount);
    }

    public function test_a_stored_value_of_the_wrong_type_is_a_load_failure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not text');

        ForecastModelCodec::decode(ForecastEngineVersion::CURRENT, [
            'calendar' => ['start_month' => '2026-01', 'months' => 12],
            'streams' => [['id' => 's1', 'name' => 'Intensive', 'price' => 1200]],
        ], null, null);
    }

    public function test_a_missing_required_field_is_a_load_failure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"name" is missing');

        ForecastModelCodec::decode(ForecastEngineVersion::CURRENT, [
            'calendar' => ['start_month' => '2026-01', 'months' => 12],
            'streams' => [['id' => 's1']],
        ], null, null);
    }

    public function test_a_payload_that_breaks_an_engine_rule_is_refused_on_load(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // 'in' is not a direction an operating payment can take. The engine
        // rejects it at construction, and a trip through the database must
        // not make it acceptable.
        ForecastModelCodec::decode(ForecastEngineVersion::CURRENT, [
            'calendar' => ['start_month' => '2026-01', 'months' => 12],
            'trade_items' => [[
                'id' => 't1', 'source' => 'invoice', 'kind' => 'payable', 'raised_on' => '2026-01-15', 'amount' => '500',
                'settlements' => [['id' => 'm1', 'source' => 'bank', 'date' => '2026-02-01', 'amount' => '500',
                    'direction' => 'in', 'category' => 'operating_payment', 'sequence' => 0]],
            ]],
        ], null, null);
    }

    public function test_a_model_from_an_unknown_engine_version_is_not_guessed_at(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be read by engine version');

        ForecastModelCodec::decode('fsa-forecast.1a.9', ['calendar' => ['start_month' => '2026-01', 'months' => 12]], null, null);
    }

    public function test_the_reserve_target_is_recomputed_rather_than_trusted_from_storage(): void
    {
        $stored = (new ReservePolicy('1000', 3, true, '500', '0.10', 'monthly fixed costs including owner pay'))->jsonSerialize();
        // A stored target that disagrees with the policy behind it must not win.
        $stored['target'] = '999999';

        $policy = ForecastModelCodec::reservePolicy($stored);

        // Base 1000 x 3 = 3000; contingency (3000 + 500) x 0.10 = 350.
        self::assertSame('3350.00', $policy->target()?->toScale(2)->__toString());
    }

    public function test_the_engine_version_names_every_contributing_slice(): void
    {
        $components = ForecastEngineVersion::components();

        self::assertArrayHasKey('revenue', $components);
        self::assertArrayHasKey('cash', $components);
        self::assertArrayHasKey('actuals', $components);
        self::assertArrayHasKey('funding', $components);
        self::assertNotContains('', $components);
    }

    private function reload(ForecastModelInputs $inputs): ForecastModelInputs
    {
        return ForecastModelCodec::decode(
            ForecastEngineVersion::CURRENT,
            $this->plain($inputs->driversPayload()),
            $this->plain($inputs->actualsPayload()),
            $inputs->reservePolicyPayload() === null ? null : $this->plain($inputs->reservePolicyPayload()->jsonSerialize()),
        );
    }

    private function encoded(ForecastModelInputs $inputs): string
    {
        return (string) json_encode([
            'drivers' => $inputs->driversPayload(),
            'actuals' => $inputs->actualsPayload(),
            'reserve_policy' => $inputs->reservePolicyPayload(),
        ], JSON_UNESCAPED_SLASHES);
    }

    private function plain(mixed $payload): mixed
    {
        return json_decode((string) json_encode($payload, JSON_UNESCAPED_SLASHES), true);
    }

    private function reconstruction(): ForecastModelInputs
    {
        return new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 24),
            streams: [new RevenueStream('intensive', 'Intensive day', 'day', '1200', '2', '6', true, 'own_record', [
                '2026-01' => new StreamMonth('4', '4', '4', '24', '0', null, null, null),
                '2026-02' => new StreamMonth('5', '4', '5', '24', '1', 'Associate', '400', null),
            ])],
            founderMonths: [
                '2026-01' => new FounderMonth('80', '16', '8'),
                '2026-02' => new FounderMonth('80', '16', null),
            ],
            cashOpening: new CashOpening('100', 'bank statement 2025-12-31', true),
            bankOpening: new BankOpening('100', '0', '100', '0', 'bank statement', true),
            tradeItems: [new TradeItem('inv-1', 'invoice', 'receivable', '2026-01-15', '1200', [
                new CashMovement('rec-1', 'bank', '2026-02-14', '1200', 'in', 'operating_receipt', 0, true),
            ])],
            ownerPaySteps: [
                new OwnerPayStep('2026-03-01', '500', 'base', null),
                new OwnerPayStep('2026-07-01', '900', 'trailing_three_month_average_revenue', '12000'),
            ],
            assets: [new AssetInput('laptop', 'invoice', '3200', 'capital', 'fixed asset policy', true, '2026-01-10', '2026-02-01', 36, '200', 'straight_line_full_start_month', '0')],
            taxPolicy: new TaxPolicy('company', '28', true, 'IR policy note', 'advisor review', '2026.1'),
            taxPeriods: [new TaxPeriod('2026-04-01', '2027-03-31', '14000', '0')],
            actualMonths: [new ActualMonth(
                '2025-12',
                'trading',
                new ActualSource('entity-1', 'xero', 'reviewed', false, '2025-12-01', '2025-12-31', 'v3', 'NZD',
                    ['bank' => ['090-1234']], 'advisor@example.test', '2026-01-05', 'review-1', true),
                new ActualProfit('8000', '6000', '120', '0', null, '1880', '520'),
                new ActualBalances('5000', '0', '1200', '800', null, null),
                new ActualBalances('6000', '0', '900', '700', null, null),
                [new CashMovement('mv-1', 'bank', '2025-12-10', '8000', 'in', 'operating_receipt', 0, true)],
                ['mv-1' => '090-1234'],
                [new TradeItem('inv-0', 'invoice', 'receivable', '2025-12-20', '900', null)],
                null,
            )],
            reservePolicy: new ReservePolicy('4000', 3, true, '2000', '0.10', 'monthly fixed costs including owner pay'),
        );
    }
}
