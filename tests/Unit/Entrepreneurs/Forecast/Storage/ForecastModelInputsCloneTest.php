<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entrepreneurs\Forecast\ActualsFixture as F;

/**
 * Guards the hand-written copy a scenario run depends on.
 *
 * `withCostInputs()` replaces the three sections a shock can reach and carries
 * the rest across by hand, because the class is readonly and `clone with` is
 * not available on the PHP version this runs on. A field added to the
 * constructor later and forgotten there would not fail to compile and would
 * not fail any existing test: it would quietly arrive empty in every shocked
 * scenario, so a downside would run without the owner's pay, or without the
 * tax it owes, and report a cash position better than the base case's.
 */
final class ForecastModelInputsCloneTest extends TestCase
{
    /** The three sections a cost or volume shock reaches. */
    private const REPLACED = ['streams', 'fixedCosts', 'assets'];

    public function test_every_section_a_shock_does_not_reach_survives_the_copy(): void
    {
        $inputs = $this->populated();
        $copy = $inputs->withCostInputs([], [], []);

        foreach ($this->properties() as $name => $property) {
            if (in_array($name, self::REPLACED, true)) {
                continue;
            }
            self::assertSame(
                $property->getValue($inputs),
                $property->getValue($copy),
                sprintf('withCostInputs() dropped "%s"; add it to the copy.', $name),
            );
        }
    }

    public function test_the_three_shocked_sections_are_replaced(): void
    {
        $copy = $this->populated()->withCostInputs([], [], []);

        self::assertSame([], $copy->streams);
        self::assertSame([], $copy->fixedCosts);
        self::assertSame([], $copy->assets);
    }

    /**
     * The fixture has to differ from the constructor's own defaults in every
     * field, or a dropped one would land on the same value it was given and
     * the comparison above would pass while the section was lost. Failing here
     * when a new field appears is the point: whoever adds it has to populate it
     * and thread it through the copy.
     */
    public function test_the_fixture_states_every_field(): void
    {
        $inputs = $this->populated();

        foreach ($this->properties() as $name => $property) {
            $value = $property->getValue($inputs);
            self::assertNotNull($value, sprintf('"%s" is unstated, so the copy guard cannot see it.', $name));
            if (is_array($value)) {
                self::assertNotSame([], $value, sprintf('"%s" is empty, so the copy guard cannot see it.', $name));
            }
        }
    }

    /** @return array<string,\ReflectionProperty> */
    private function properties(): array
    {
        $properties = [];
        foreach ((new \ReflectionClass(ForecastModelInputs::class))->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        return $properties;
    }

    private function populated(): ForecastModelInputs
    {
        return new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 24),
            streams: [new RevenueStream('intensive', 'Intensive day', 'day', '1200', '2', '6', true, 'own_record', [
                '2026-01' => new StreamMonth('4', '4', '4', '24', '0', 'Associate', '400', null),
            ])],
            founderMonths: ['2026-01' => new FounderMonth('80', '16', '8')],
            cashOpening: new CashOpening('100', 'bank statement 2025-12-31', true),
            bankOpening: new BankOpening('100', '0', '100', '0', 'bank statement', true),
            tradeItems: [new TradeItem('inv-1', 'invoice', 'receivable', '2026-01-15', '1200', [
                new CashMovement('rec-1', 'bank', '2026-02-14', '1200', 'in', 'operating_receipt', 0, true),
            ])],
            ownerPaySteps: [new OwnerPayStep('2026-03-01', '500', 'base', null)],
            assets: [new AssetInput('laptop', 'invoice', '3200', 'capital', 'fixed asset policy', true, '2026-01-10',
                '2026-02-01', 36, '200', 'straight_line_full_start_month', '0')],
            taxPolicy: new TaxPolicy('company', '28', true, 'IR policy note', 'advisor review', '2026.1'),
            taxPeriods: [new TaxPeriod('2026-04-01', '2027-03-31', '14000', '0')],
            actualMonths: F::twoMonths(),
            reservePolicy: new ReservePolicy('4000', 3, true, '2000', '0.10', 'monthly fixed costs including owner pay'),
            ownerPaySource: 'approved owner pay policy',
            ownerPayFirstPaymentOn: '2026-03-01',
            openingEligibleLosses: '1500',
            fundingSources: [new FundingSource('loan', 'term sheet', 'Bank', 'debt', '20000', 'committed',
                '2026-01-01', '2026-01-05', 'unrestricted', true)],
            fundingInventoryComplete: true,
            fixedCosts: [new FixedCost('rent', 'lease', '1000', '1', 'monthly', '2026-01-10', '2026-12-10', 7, true)],
            fixedCostScheduleComplete: true,
            taxCashPlan: new TaxCashPlan('none_due', 'none_due', 'IR account review', 'IR account review', []),
            ownerPayDeductible: true,
        );
    }
}
